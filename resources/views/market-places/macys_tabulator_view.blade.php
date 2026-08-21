@extends('layouts.vertical', ['title' => 'Macys - Analytics', 'sidenav' => 'condensed'])

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

        /* Sku Link LMP badges (same as amazon/reverb tabulator) */
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
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'macys', 'channelPromoHideCvrCpn' => true, 'channelPromoShowZeroSoldRules' => true, 'channelPromoShowGtSoldRules' => true])
        .sprice-lmp-alert {
            color: #dc3545;
            font-size: 11px;
            line-height: 1;
            margin-left: 4px;
            cursor: help;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Macys - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <!-- Summary Stats + filters (single wrapping row) -->
                <div id="summary-stats" class="d-flex align-items-center flex-wrap gap-1">
                    <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold; display: none;">PFT: $0</span>
                    <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Sales: $0</span>
                    <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;" title="GPFT% from visible rows (same aggregate as /macys/daily-sales).">GPFT: 0%</span>
                    <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: white; font-weight: bold;" title="GROI% / ROI% from visible rows.">GROI: 0%</span>
                    <span class="badge fs-6 p-2" id="ads-percent-badge" style="background-color: #d63384; color: white; font-weight: bold;" title="Macys has no ads — Ads%/TACOS is always 0% (same as /all-marketplace-master).">Ads: 0%</span>
                    <span class="badge fs-6 p-2" id="npft-percent-badge" style="background-color: #0f766e; color: white; font-weight: bold;" title="NPFT% = GPFT% (Macys has no ads — same as /all-marketplace-master N PFT).">NPFT: 0%</span>
                    <span class="badge fs-6 p-2" id="nroi-percent-badge" style="background-color: #6f42c1; color: white; font-weight: bold;" title="NROI% = GROI% (Macys has no ads — same as /all-marketplace-master N ROI).">NROI: 0%</span>
                    <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold; display: none;">Price: $0</span>
                    <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: 0</span>
                    @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'macys-lmp-missing-badge', 'lmpChannelKey' => 'macys'])
                    @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'macys-price-gt-lmp-badge', 'pglChannelKey' => 'macys', 'pglPriceField' => 'MC Price'])
                    @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'macys-price-lt80-lmp-badge', 'pltChannelKey' => 'macys', 'pltPriceField' => 'MC Price'])
                    <span class="badge fs-6 p-2" id="zero-sold-rule-badge" style="background-color: #4f46e5; color: white; font-weight: bold; cursor: pointer;" title="0 Sold Rule — apply Amazon Price to S PRC for MC L30 = 0. Selected SKUs if checked; otherwise all visible. Skips INV = 0 and missing A Price.">0 Sold Rule: 0</span>
                    <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter items with sales">&gt; 0 Sold: 0</span>
                    <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amz">&lt; Amz: 0</span>
                    <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amz">&gt; Amz: 0</span>
                    <span class="badge bg-danger fs-6 p-2" id="missing-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing prices">Miss: 0</span>
                    <span class="badge bg-danger fs-6 p-2" id="mapping-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter inventory mapping issues">N Map: 0</span>

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All INV</option>
                        <option value="zero">INV = 0</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Status</option>
                        <option value="REQ" selected>REQ</option>
                        <option value="NR">NR</option>
                    </select>

                    <div class="d-flex gap-1" style="width: auto;" title="CVR = MC L30 ÷ OV L30">
                        <select id="gpft-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="all">CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    {{-- Sold dropdown (mirrors Amazon tabulator + /doba + /shopify-b2c-pricing).
                         Backed by `MC L30`:
                           all  → no filter
                           sold → MC L30 > 0
                           zero → MC L30 = 0
                         The dropdown is the single source of truth — the existing
                         #zero-sold-count-badge / #more-sold-count-badge click handlers
                         just write into this dropdown so badges + dropdown can never disagree. --}}
                    <select id="sold-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;"
                            title="Filter by MC L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm"
                        style="width: 90px; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            title="Columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>

                    <button id="export-btn" class="btn btn-sm btn-info" title="Export CSV">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'macys', 'channelPromoHideCvrCpn' => true, 'channelPromoShowZeroSoldRules' => true, 'channelPromoShowGtSoldRules' => true])

                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadPriceModal" title="Upload Price">
                        <i class="fa fa-upload"></i> Prc
                    </button>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = 0.80 for Macys) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 px-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / 0.80 on every selected row (back-solves so SROI column equals the target)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <i class="fas fa-bullseye text-danger"></i> ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S PRC'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / 0.80 for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100 (else denominator ≤ 0). --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 px-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / (0.80 − Target GPFT%/100) on every selected row (back-solves so SGPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            <i class="fas fa-bullseye text-danger"></i> GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="30" step="0.1" style="width: 56px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S PRC'. Must be less than the Macys take-home margin (< 80%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP + Ship) / (0.80 − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (always visible) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold">0 SKUs selected</span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-copy"></i> Sugg Amz Prc
                        </button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="macys-table-wrapper" style="height: calc(100vh - 160px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="px-2 py-1 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                    </div>
                    <!-- Table body -->
                    <div id="macys-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Price Modal -->
    <div class="modal fade" id="uploadPriceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa fa-dollar-sign me-2"></i>Upload Price Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadPriceForm" action="{{ route('macys.upload.price') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv,.tsv" required>
                            <small class="text-muted">Supported formats: Excel (.xlsx, .xls), CSV, TSV</small>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadPriceForm" class="btn btn-success"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal (MacySkuCompetitor source) -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> Macy's Competitors for SKU: <span id="lmpSku"></span>
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
                                        <label class="form-label">Macy Item ID *</label>
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id"
                                            required placeholder="e.g., product id">
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
                                            placeholder="https://www.macys.com/shop/product/...">
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
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'macys', 'channelPromoHideCvrCpn' => true, 'channelPromoShowZeroSoldRules' => true, 'channelPromoShowGtSoldRules' => true])
@endsection

@section('script-bottom')
<script>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'macys', 'channelPromoHideCvrCpn' => true, 'channelPromoShowZeroSoldRules' => true, 'channelPromoShowGtSoldRules' => true])
    const COLUMN_VIS_KEY = "macys_tabulator_column_visibility";
    let table = null;
    let lmpMissingFilterActive = false;
    let priceGtLmpFilterActive = false;
    let priceLt80LmpFilterActive = false;
    let allTableData = []; // Full dataset for ParentExpand
    let decreaseModeActive = true;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    // Must match MarketplacePercentage for Macys (and row.percentage from API) — NOT a hardcoded 0.80.
    // Using 0.80 here while GPFT uses 0.75 made SPFT ≠ GPFT when SPRICE === MC Price.
    const MACYS_DEFAULT_MARGIN = {{ number_format(((float) ($macysPercentage ?? 80)) / 100, 4, '.', '') }};

    /** Listed = uploaded sheet has a price (row.is_missing_macy from API). */
    function isMacysListed(rowData) {
        if (!rowData || rowData.is_parent_summary) return false;
        if (typeof rowData.is_missing_macy !== 'undefined') {
            return !rowData.is_missing_macy;
        }
        return (parseFloat(rowData['MC Price']) || 0) > 0;
    }

    /** Take-home margin for a row (same value used for GPFT% on the server). */
    function getMacysMargin(rowData) {
        const m = parseFloat(rowData && rowData.percentage);
        if (isFinite(m) && m > 0) {
            return m > 1 ? m / 100 : m;
        }
        return MACYS_DEFAULT_MARGIN;
    }

    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function macysStdPrcChangeDotMeta(stdPrc, comparePrice) {
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
        return { kind: 'hold', color: '#ffc107', title: 'Hold (matches Amz price)' };
    }

    function macysStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = macysStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyMacysStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            if (!d || d.is_parent_summary || (d.Parent && String(d.Parent).startsWith('PARENT'))) return;
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
        applyMacysStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
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

    /** Same aggregation as macys_daily_sales_data.blade.php updateSummary() — uses /macys/daily-sales-data */
    function applyMacysDailySalesSummary(rows) {
        if (!Array.isArray(rows)) {
            rows = [];
        }
        let totalOrders = 0;
        let totalQuantity = 0;
        let totalRevenue = 0;
        let totalPft = 0;
        let totalL30Sales = 0;
        let totalWeightedPrice = 0;
        let totalQuantityForPrice = 0;
        let totalCogs = 0;

        rows.forEach(row => {
            if (!row.sku || row.sku === '' || !row.order_id || row.order_id === '') {
                return;
            }

            totalOrders++;
            const quantity = parseInt(row.quantity, 10) || 0;
            const unitPrice = parseFloat(row.unit_price) || 0;

            if (quantity === 0) {
                return;
            }

            totalQuantity += quantity;
            totalRevenue += unitPrice * quantity;

            if (quantity > 0 && unitPrice > 0) {
                totalWeightedPrice += unitPrice * quantity;
                totalQuantityForPrice += quantity;
            }

            const pft = parseFloat(row.pft) || 0;
            const cogs = parseFloat(row.cogs) || 0;

            totalPft += pft;
            totalCogs += cogs;

            totalL30Sales += quantity * unitPrice;
        });

        const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
        const pftPercentage = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
        const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

        function setMacysDsRole(role, text) {
            $('.macys-ds-aggregate[data-role="' + role + '"]').text(text);
        }

        setMacysDsRole('orders', 'Orders: ' + totalOrders.toLocaleString());
        setMacysDsRole('quantity', 'Quantity: ' + totalQuantity.toLocaleString());
        setMacysDsRole('sales', 'Sales: $' + Math.round(totalRevenue).toLocaleString());
        setMacysDsRole('gpft-pct', 'GPFT %: ' + Math.round(pftPercentage) + '%');
        setMacysDsRole('roi-pct', 'ROI %: ' + Math.round(roiPercentage) + '%');
        setMacysDsRole('avg-price', 'Avg Price: $' + Math.round(avgPrice).toLocaleString());
        setMacysDsRole('pft-total', 'GPFT Total: $' + Math.round(totalPft).toLocaleString());
        setMacysDsRole('cogs', 'Total COGS: $' + Math.round(totalCogs).toLocaleString());

        $('.macys-ds-aggregate[data-role="pft-total"]').each(function() {
            const el = $(this);
            if (totalPft >= 0) {
                el.removeClass('bg-danger').addClass('bg-dark');
            } else {
                el.removeClass('bg-dark').addClass('bg-danger');
            }
        });
    }

    function loadMacysDailySalesSummary() {
        $.getJSON('/macys/daily-sales-data')
            .done(function(rows) {
                applyMacysDailySalesSummary(rows);
            })
            .fail(function() {
                const roles = ['orders', 'quantity', 'sales', 'gpft-pct', 'roi-pct', 'avg-price', 'pft-total', 'cogs'];
                const labels = ['Orders', 'Quantity', 'Sales', 'GPFT %', 'ROI %', 'Avg Price', 'GPFT Total', 'Total COGS'];
                roles.forEach(function(role, i) {
                    $('.macys-ds-aggregate[data-role="' + role + '"]').text(labels[i] + ': —');
                });
                showToast('Could not load L30 daily sales summary', 'error');
            });
    }

    $(document).ready(function() {
        // Aggregate daily-sales badge row was removed (badges now match BestBuy); no fetch needed.
        // loadMacysDailySalesSummary();

        // Mode button visual resets — keep each in their idle styling.
        function resetDecreaseBtn() {
            $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning')
                .html('<i class="fas fa-arrow-down"></i> Decrease Mode');
        }
        function resetIncreaseBtn() {
            $('#increase-btn').removeClass('btn-danger').addClass('btn-success')
                .html('<i class="fas fa-arrow-up"></i> Increase Mode');
        }
        function resetSamePriceBtn() {
            $('#same-price-btn').removeClass('btn-danger').addClass('btn-info')
                .html('<i class="fas fa-equals"></i> Same Price Mode');
        }

        // Swap the discount input UI between %/$ and Same Price modes.
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

        // Keep placeholder in sync when the user toggles % vs $.
        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

        // Single toggle that cycles: Prc Mode (off) → Decrease → Increase → Same Price → off
        $('#mode-toggle-btn').on('click', function() {
            const selectColumn = table.getColumn('_select');
            const $btn = $(this);

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                // off → Decrease
                decreaseModeActive = true; increaseModeActive = false; samePriceModeActive = false;
                $btn.removeClass('btn-secondary btn-success btn-info').addClass('btn-danger').text('Decrease ON');
                selectColumn.show();
            } else if (decreaseModeActive) {
                // Decrease → Increase
                decreaseModeActive = false; increaseModeActive = true; samePriceModeActive = false;
                $btn.removeClass('btn-secondary btn-danger btn-info').addClass('btn-success').text('Increase ON');
                selectColumn.show();
            } else if (increaseModeActive) {
                // Increase → Same Price
                decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = true;
                $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-info').text('Same Price ON');
                selectColumn.show();
            } else {
                // Same Price → off
                decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = false;
                $btn.removeClass('btn-danger btn-success btn-info').addClass('btn-secondary').text('Prc Mode');
                if (selectColumn) selectColumn.show();
                updateSelectedCount();
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
         * Target ROI% bulk apply (Macys, margin = row.percentage / MarketplacePercentage)
         * ---------------------------------------------
         * For every selected row with a usable LP, back-solve the sale price so the
         * resulting SROI column matches Target ROI%:
         *     SROI = ((sprice * margin − ship − lp) / lp) * 100
         *   → sprice = (lp * (1 + ROI%/100) + ship) / margin
         * Optimistic SGPFT / SPFT / SROI are written client-side and the existing
         * bulk save endpoint (/macys-save-sprice-batch) reconciles them server-side.
         * Rounding is plain 2-decimal — no .99 / .49 retail snapping — because
         * snapping would shift the achieved SROI / SGPFT off the user-typed target.
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
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (turn on Decrease / Increase / Same Price to reveal checkboxes)', 'error');
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
                if (rowData.Parent && String(rowData.Parent).startsWith('PARENT')) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const MACYS_MARGIN = getMacysMargin(rowData);

                const candidate = (lp * roiMultiplier + ship) / MACYS_MARGIN;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * MACYS_MARGIN - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft  = sgpft; // Same as SGPFT for Macys (no ads)
                const sroi  = lp > 0 ? Math.round(((newSprice * MACYS_MARGIN - lp - ship) / lp) * 100 * 100) / 100 : 0;

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
         * Target GPFT% bulk apply (Macys, margin = row.percentage / MarketplacePercentage)
         * ----------------------------------------------
         * Mirrors Target ROI but back-solves so SGPFT = Target GPFT%:
         *     SGPFT = ((sprice * margin − ship − lp) / sprice) * 100
         *   → sprice = (lp + ship) / (margin − GPFT%/100)
         * Constraint: (margin − target/100) must be > 0.
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
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (turn on Decrease / Increase / Same Price to reveal checkboxes)', 'error');
                return;
            }

            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;
            let skippedBadTarget = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (rows.length === 0) return;
                const row = rows[0];
                const rowData = row.getData();
                if (rowData.Parent && String(rowData.Parent).startsWith('PARENT')) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                const MACYS_MARGIN = getMacysMargin(rowData);
                const denom = MACYS_MARGIN - (targetGpftPct / 100);
                if (denom <= 0) { skippedBadTarget++; return; }

                const candidate = (lp + ship) / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * MACYS_MARGIN - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft  = sgpft; // Same as SGPFT for Macys (no ads)
                const sroi  = lp > 0 ? Math.round(((newSprice * MACYS_MARGIN - lp - ship) / lp) * 100 * 100) / 100 : 0;

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
                if (skippedBadTarget > 0) {
                    showToast(`Target GPFT% ${targetGpftPct}% is too high — must be < Macys take-home margin (~${Math.round(MACYS_DEFAULT_MARGIN * 100)}%).`, 'error');
                } else {
                    showToast('No selected rows have a usable LP > 0', 'warning');
                }
                return;
            }

            saveSpriceUpdates(updates);
            const notes = [];
            if (skippedNoLp > 0) notes.push(`${skippedNoLp} skipped — no LP`);
            if (skippedBadTarget > 0) notes.push(`${skippedBadTarget} skipped — target ≥ margin`);
            const note = notes.length ? ` (${notes.join('; ')})` : '';
            showToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        // Enter inside Target ROI%/GPFT% inputs triggers Apply S PRC
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

        $('#zero-sold-rule-badge').on('click', function() {
            if (typeof saveAndApplyChPromoZeroSoldAmazon === 'function') {
                saveAndApplyChPromoZeroSoldAmazon({ push: false });
                return;
            }
            applyZeroSoldAmazonToSprice();
        });

        // Clear SPRICE button
        $('#clear-sprice-btn').on('click', function() {
            clearSpriceForSelected();
        });

        // Badge clicks just toggle the #sold-filter dropdown so the dropdown stays the
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

        let lessAmzFilterActive = false;
        $('#less-amz-badge').on('click', function() {
            lessAmzFilterActive = !lessAmzFilterActive;
            moreAmzFilterActive = false;
            applyFilters();
        });

        let moreAmzFilterActive = false;
        $('#more-amz-badge').on('click', function() {
            moreAmzFilterActive = !moreAmzFilterActive;
            lessAmzFilterActive = false;
            applyFilters();
        });

        let missingFilterActive = false;
        $('#missing-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mappingFilterActive = false;
            applyFilters();
        });

        let mappingFilterActive = false;
        $('#mapping-badge').on('click', function() {
            mappingFilterActive = !mappingFilterActive;
            missingFilterActive = false;
            applyFilters();
        });

        // Upload Price Form Handler
        $('#uploadPriceForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#uploadPriceModal').modal('hide');
                    showToast(response.success || 'Price data uploaded successfully!', 'success');
                    table.setData(); // Reload table data
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Error uploading file';
                    showToast(errorMsg, 'error');
                }
            });
        });

        // Update selected count display
        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
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

        // Apply discount / same price to selected SKUs (based on MC Price).
        function applyDiscount() {
            const discountType = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
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
                    const currentPrice = parseFloat(rowData['MC Price']) || 0;

                    // Same Price applies even if MC Price is 0; %/$ modes need a positive MC Price.
                    if (samePriceModeActive || currentPrice > 0) {
                        let newSprice;

                        if (samePriceModeActive) {
                            // The ONE price the user typed, applied verbatim to every selected SKU.
                            newSprice = Math.max(0.99, discountValue);
                        } else if (discountType === 'percentage') {
                            if (decreaseModeActive) {
                                newSprice = currentPrice * (1 - discountValue / 100);
                            } else {
                                newSprice = currentPrice * (1 + discountValue / 100);
                            }
                        } else {
                            if (decreaseModeActive) {
                                newSprice = currentPrice - discountValue;
                            } else {
                                newSprice = currentPrice + discountValue;
                            }
                        }

                        // Same Price: keep the typed price exact (no .99 snap) so
                        // SPRICE === MC Price ⇒ SPFT === GPFT. Decrease/Increase still retail-round.
                        if (!samePriceModeActive) {
                            newSprice = roundToRetailPrice(newSprice);
                        } else {
                            newSprice = +Number(newSprice).toFixed(2);
                        }

                        // Ensure minimum price
                        newSprice = Math.max(0.99, newSprice);

                        // Use the same take-home margin as GPFT% (row.percentage from API)
                        const percentage = getMacysMargin(rowData);
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;

                        const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                        const spft = sgpft; // Same as SGPFT for Macys (no ads)
                        const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;

                        // Update SPRICE and metrics in table
                        row.update({
                            SPRICE: newSprice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi
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

            const action = samePriceModeActive ? 'Same Price' : (decreaseModeActive ? 'Decrease' : 'Increase');
            const suffix = samePriceModeActive ? '' : ' based on MC Price';
            showToast(`${action} applied to ${updatedCount} SKU(s)${suffix}`, 'success');
            $('#discount-percentage-input').val('');
        }

        function isMacysParentRow(rowData) {
            return !!(rowData && (rowData.is_parent_summary
                || (rowData.Parent && String(rowData.Parent).startsWith('PARENT'))));
        }

        function macysAmazonToSpricePatch(rowData, amazonPrice) {
            const percentage = getMacysMargin(rowData);
            const lp = parseFloat(rowData['LP_productmaster']) || 0;
            const ship = parseFloat(rowData['Ship_productmaster']) || 0;
            const sgpft = amazonPrice > 0
                ? Math.round(((amazonPrice * percentage - ship - lp) / amazonPrice) * 100 * 100) / 100
                : 0;
            const sroi = lp > 0
                ? Math.round(((amazonPrice * percentage - lp - ship) / lp) * 100 * 100) / 100
                : 0;
            return {
                SPRICE: amazonPrice,
                SGPFT: sgpft,
                SPFT: sgpft,
                SROI: sroi,
                has_custom_sprice: true,
            };
        }

        // 0 Sold Rule — copy Amazon Price onto S PRC for MC L30 = 0.
        function applyZeroSoldAmazonToSprice() {
            if (!table) {
                showToast('Load data first', 'error');
                return;
            }

            const selected = selectedSkus.size > 0;
            const rows = (table.getRows(selected ? undefined : 'active') || []).filter(function(row) {
                const d = row.getData() || {};
                if (isMacysParentRow(d)) return false;
                const sku = String(d['(Child) sku'] || d.sku || '').trim();
                if (!sku) return false;
                if (selected && !selectedSkus.has(sku)) return false;
                if ((parseFloat(d['MC L30']) || 0) > 0) return false;
                if ((parseFloat(d.INV) || 0) <= 0) return false;
                return (parseFloat(d['A Price']) || 0) > 0;
            });

            if (!rows.length) {
                showToast(selected
                    ? 'No selected 0 Sold SKUs with Amazon Price (INV > 0)'
                    : 'No visible 0 Sold SKUs with Amazon Price (INV > 0)', 'error');
                return;
            }

            const scope = selected ? 'selected' : 'visible';
            if (!confirm('0 Sold Rule: apply Amazon Price to S PRC for ' + rows.length + ' ' + scope + ' 0 Sold SKU(s)?')) {
                return;
            }

            const updates = [];
            rows.forEach(function(row) {
                const d = row.getData() || {};
                const sku = String(d['(Child) sku'] || d.sku || '').trim();
                const amazonPrice = parseFloat(d['A Price']) || 0;
                if (!sku || amazonPrice <= 0) return;
                row.update(macysAmazonToSpricePatch(d, amazonPrice));
                updates.push({ sku: sku, sprice: amazonPrice });
            });

            if (updates.length) {
                saveSpriceUpdates(updates, { skip_push: true });
            }
            showToast('0 Sold Rule: Amazon Price → S PRC on ' + updates.length + ' SKU(s)', 'success');
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
                        // Calculate metrics with the same margin GPFT% uses
                        const percentage = getMacysMargin(rowData);
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                        
                        const sgpft = amazonPrice > 0 ? Math.round(((amazonPrice * percentage - ship - lp) / amazonPrice) * 100 * 100) / 100 : 0;
                        const spft = sgpft; // Same as SGPFT for Macys (no ads)
                        const sroi = lp > 0 ? Math.round(((amazonPrice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                        
                        // Update the row with Amazon price and calculated metrics
                        row.update({
                            SPRICE: amazonPrice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi
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
            
            let message = `Amz price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amz price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
        }

        // Save SPRICE updates to backend (unified function for all SPRICE updates)
        function saveSpriceUpdates(updates, opts) {
            opts = opts || {};
            const data = {
                updates: updates
            };
            if (opts.skip_push) data.skip_push = 1;
            $.ajax({
                url: '/macys-save-sprice-batch',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: data,
                success: function(response) {
                    if (response.success) {
                        console.log('SPRICE updates saved successfully:', response.updated, 'records');
                        // Show subtle success notification
                        if (response.errors && response.errors.length > 0) {
                            console.warn('Some updates had errors:', response.errors);
                        }
                        if (response.price_push_success_count !== undefined || response.price_push_failed_count !== undefined) {
                            const pushOk = Number(response.price_push_success_count || 0);
                            const pushFail = Number(response.price_push_failed_count || 0);
                            if (pushFail > 0) {
                                let pushMsg = `Macy price push: ${pushOk} success, ${pushFail} failed`;
                                if (response.price_push_errors && response.price_push_errors.length > 0) {
                                    pushMsg += ` (${response.price_push_errors[0]})`;
                                }
                                showToast(pushMsg, 'warning');
                            } else if (pushOk > 0) {
                                showToast(`Macy price push successful for ${pushOk} SKU(s)`, 'success');
                            }
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

        // ========== LMP + Sku Link LMP (MacySkuCompetitor source) ==========
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

        function loadMacyCompetitorsModal(sku, linkedLmpSkus) {
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
                url: '/macy-lmp-data',
                method: 'GET',
                traditional: true,
                data: { sku: sku, linked_lmp_skus: currentLmpData.linkedLmpSkus },
                success: function(response) {
                    if (response.success && response.competitors && response.competitors.length > 0) {
                        currentLmpData.competitors = response.competitors;
                        currentLmpData.lowestPrice = response.lowest_price;
                        renderMacyCompetitorsList(response.competitors, response.lowest_price);
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

        function renderMacyCompetitorsList(competitors, lowestPrice) {
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
                const productLink = item.link || `https://www.macys.com/shop/featured/${encodeURIComponent(item.item_id || '')}`;
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
                            <a href="${escAttr(productLink)}" target="_blank" class="btn btn-sm btn-info" title="View on Macy's"><i class="fa fa-external-link"></i></a>
                            <button class="btn btn-sm btn-danger delete-macy-lmp-btn"
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
            loadMacyCompetitorsModal(sku, linkedSkus);
        });

        $('#addCompetitorForm').on('submit', function(e) {
            e.preventDefault();
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
            $.ajax({
                url: '/macy-lmp-add',
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
                        loadMacyCompetitorsModal($('#addCompSku').val(), currentLmpData.linkedLmpSkus);
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

        $(document).on('click', '.delete-macy-lmp-btn', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            const itemId = $btn.data('item-id');
            const price = $btn.data('price');
            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) return;
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: '/macy-lmp-delete',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');
                        loadMacyCompetitorsModal(currentLmpData.sku, currentLmpData.linkedLmpSkus);
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
        table = new Tabulator("#macys-table", {
            ajaxURL: "/macys-data-json",
            ajaxSorting: false,
            layout: "fitColumns",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, true],
            paginationCounter: "rows",
            columnCalcs: "both",
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            ajaxResponse: function(url, params, response) {
                var payload = (response && response.data) ? response.data : response;
                if (Array.isArray(payload)) {
                    allTableData = payload;
                    if (window.ParentExpand) ParentExpand.captureDataset(payload);
                }
                return payload;
            },
            initialSort: [{
                column: "MC L30",
                dir: "desc"
            }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "#fffef2";
                }
            },
            columns: [
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
                    field: "MC Dil%",
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
                    title: "MC L30",
                    field: "MC L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "MC SQty",
                    field: "MC Sales Qty",
                    hozAlign: "center",
                    width: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        let val = parseFloat(cell.getValue()) || 0;
                        return `<span style="font-weight: 600; color: #6f42c1;">${val}</span>`;
                    }
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
                    headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings. Dot vs Amz price.",
                    editor: "input",
                    width: 70,
                    sorter: "number",
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent_summary || (d.Parent && String(d.Parent).startsWith('PARENT'))) return false;
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary || (rowData.Parent && String(rowData.Parent).startsWith('PARENT'))) return '';
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const amzPrice = parseFloat(rowData['A Price'] || rowData.a_price || rowData.amazon_price || 0) || 0;
                        const channelPrice = parseFloat(rowData['MC Price'] || rowData.price || 0) || 0;
                        const comparePrice = amzPrice > 0 ? amzPrice : channelPrice;
                        const dot = macysStdPrcChangeDotHtml(std, comparePrice);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                            dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: "Price",
                    field: "MC Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary || (rowData.Parent && String(rowData.Parent).startsWith('PARENT'))) return '';
                        if (!isMacysListed(rowData)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        const value = parseFloat(cell.getValue() || 0);
                        const amazonPrice = parseFloat(rowData['A Price']) || 0;
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(value, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(value, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        
                        // Show red if MC Price is less than Amazon Price
                        if (amazonPrice > 0 && value < amazonPrice) {
                            return `<span style="color: #a00211; font-weight: 600;">$${value.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                        }
                        
                        // Show green if MC Price is greater than Amazon Price
                        if (amazonPrice > 0 && value > amazonPrice) {
                            return `<span style="color: #28a745; font-weight: 600;">$${value.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                        }
                        
                        return `$${value.toFixed(2)}${lmpTri}${purpleTri}`;
                    },
                    width: 70
                },
                {
                    title: "A Price",
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
                        const mcPrice = parseFloat(rowData['MC Price']) || 0;

                        let html = '<div style="display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 4px; flex-wrap: nowrap;">';

                        if (!lmpPrice && totalCompetitors === 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #999; text-decoration: none; cursor: pointer; font-size: 12px;" title="Add competitors">N/A</a>`;
                        } else if (lmpPrice) {
                            const finalPrice = parseFloat(lmpPrice) || 0;
                            const priceColor = (mcPrice > 0 && finalPrice < mcPrice) ? '#dc3545' : '#28a745';
                            html += `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">$${finalPrice.toFixed(2)}</span>`;
                        }

                        if (totalCompetitors > 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${escAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 12px; font-weight: 600;" title="View competitors">${totalCompetitors}</a>`;
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
                    title: "<span style='color: #a00211;'>Missing</span>",
                    field: "Missing",
                    hozAlign: "center",
                    sorter: "string",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const inv = parseFloat(rowData['INV']) || 0;
                        const nrReq = rowData['nr_req'] || 'REQ';
                        
                        // Don't show for NR items or INV = 0
                        if (nrReq === 'NR' || inv === 0) {
                            return '';
                        }
                        
                        if (!isMacysListed(rowData)) {
                            return '<span style="color: #a00211; font-weight: 600;">M</span>';
                        }
                        return '';
                    },
                    width: 60
                },
                {
                    title: "Mapping",
                    field: "Mapping",
                    hozAlign: "center",
                    sorter: "string",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const ourInv = parseFloat(rowData['INV']) || 0;
                        const mcInv = parseFloat(rowData['MC INV']) || 0; // Marketplace inventory from Macy's
                        const nrReq = rowData['nr_req'] || 'REQ';
                        
                        // Don't show for NR items, INV = 0, or unlisted SKUs
                        if (nrReq === 'NR' || ourInv === 0 || !isMacysListed(rowData)) {
                            return '';
                        }
                        
                        if (ourInv === mcInv || Math.abs(ourInv - mcInv) <= 3) {
                            // Stocks match (or are within tolerance <= 3) - show green MAP
                            return '<span style="color: #28a745; font-weight: 600; background-color: #d4edda; padding: 2px 6px; border-radius: 3px;">MAP</span>';
                        } else {
                            // Stocks don't match - show red N MP with qty difference
                            const diff = Math.abs(mcInv - ourInv);
                            return `<span style="color: #a00211; font-weight: 600; background-color: #f8d7da; padding: 2px 6px; border-radius: 3px;">N MP (${diff})</span>`;
                        }
                    },
                    width: 90
                },
                {
                    title: "GPFT%",
                    field: "GPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        if (!isMacysListed(cell.getRow().getData())) return '<span style="color: #6c757d;">-</span>';
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
                    sorter: "number",
                    formatter: function(cell) {
                        // Macys has no ads — NPFT% = GPFT%
                        const rowData = cell.getRow().getData();
                        if (!isMacysListed(rowData)) return '<span style="color: #6c757d;">-</span>';
                        const percent = parseFloat(rowData['GPFT%'] ?? cell.getValue());
                        if (!isFinite(percent)) return '';
                        const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                        return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                    },
                    width: 50
                },
                {
                    title: "GROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        if (!isMacysListed(cell.getRow().getData())) return '<span style="color: #6c757d;">-</span>';
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                    },
                    width: 50
                },
                {
                    title: "NROI",
                    field: "NROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Macys has no ads — NROI% = GROI% (ROI%)
                        if (!isMacysListed(cell.getRow().getData())) return '<span style="color: #6c757d;">-</span>';
                        const percent = parseFloat(cell.getRow().getData()['ROI%']);
                        if (!isFinite(percent)) return '';
                        const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
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
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                    }
                },
                // PRMT % / CPN % — macys_promo_pricing
                ...(typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : []),

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
                    title: "SNPFT",
                    field: "SPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Macys has no ads — SNPFT = SGPFT
                        const rowData = cell.getRow().getData();
                        const percent = parseFloat(rowData.SGPFT ?? cell.getValue());
                        if (!isFinite(percent)) return '';
                        const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'GPFT%', percent)) || '';
                        return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                    },
                    width: 50
                },
                {
                    title: "SNROI",
                    field: "SROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        // Macys has no ads — SNROI = gross SROI (no Ads% cut)
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        if (!isFinite(percent)) return '';
                        const _st = (window.MetricPctColors && MetricPctColors.styleForField((typeof cell !== 'undefined' && cell.getField) ? cell.getField() : 'NROI', percent)) || '';
                            return _st ? `<span style="${_st}">${percent.toFixed(0)}%</span>` : `${percent.toFixed(0)}%`;
                    },
                    width: 50
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
            const row = table.getRow($rowEl[0]);
            const rowData = row.getData();
            const sku = rowData['(Child) sku'];
            const newValue = $(this).val();
            
            $.ajax({
                url: '{{ url("/macys-update-nr-req") }}',
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

        // SPRICE cell edited - recalculate metrics only.
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
                        applyMacysStandardPriceToLinkedRows(sku, saved, response.applied_skus);
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
                
                // Recalculate SGPFT, SPFT, SROI with the same margin as GPFT%
                const percentage = getMacysMargin(rowData);
                const lp = rowData['LP_productmaster'] || 0;
                const ship = rowData['Ship_productmaster'] || 0;
                
                const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft = sgpft;
                const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                
                row.update({
                    SGPFT: sgpft,
                    SPFT: spft,
                    SROI: sroi,
                    has_custom_sprice: true
                });
                showToast(`SPRICE updated for ${sku}.`, 'info');
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
            const cvrFilter = $('#cvr-filter').val();
            const roiFilter = $('#roi-filter').val();
            const dilFilter = $('#dil-filter').val();

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

            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const ov = parseFloat(data['L30']) || 0;
                    const sold = parseFloat(data['MC L30']) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrFilter === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
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

            // DIL filter (calculated as L30 / INV * 100)
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['INV']) || 0;
                    const l30 = parseFloat(data['L30']) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    
                    if (dilFilter === 'red') return dil < 16.66;
                    if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // Sold filter (based on MC L30). Driven by the #sold-filter dropdown — the
            // legacy 0 Sold / > 0 Sold badge clicks just toggle this dropdown value so
            // there is exactly one source of truth (mirrors Amazon tabulator behavior).
            const soldFilter = $('#sold-filter').val();
            if (soldFilter === 'zero') {
                table.addFilter("MC L30", "=", 0);
            } else if (soldFilter === 'sold') {
                table.addFilter("MC L30", ">", 0);
            }

            if (lessAmzFilterActive) {
                table.addFilter(function(data) {
                    const mcPrice = parseFloat(data['MC Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && mcPrice > 0 && mcPrice < amazonPrice;
                });
            }

            if (moreAmzFilterActive) {
                table.addFilter(function(data) {
                    const mcPrice = parseFloat(data['MC Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && mcPrice > 0 && mcPrice > amazonPrice;
                });
            }

            if (missingFilterActive) {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['INV']) || 0;
                    const nrReq = data['nr_req'] || 'REQ';
                    return nrReq === 'REQ' && inv > 0 && !isMacysListed(data);
                });
            }
            if (lmpMissingFilterActive && window.LmpMissingBadge) {
                table.addFilter(function(data) {
                    return !LmpMissingBadge.isParentRow(data) && !LmpMissingBadge.hasLmp(data);
                });
            }
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'MC Price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'MC Price');
                });
            }

            if (mappingFilterActive) {
                table.addFilter(function(data) {
                    const ourInv = parseFloat(data['INV']) || 0;
                    const mcInv = parseFloat(data['MC INV']) || 0;
                    const nrReq = data['nr_req'] || 'REQ';
                    return nrReq === 'REQ' && ourInv > 0 && isMacysListed(data) && Math.abs(ourInv - mcInv) > 3;
                });
            }

            updateSummary();
        }

        if (window.LmpMissingBadge) {
            LmpMissingBadge.bind({
                badge: '#macys-lmp-missing-badge',
                getActive: function() { return lmpMissingFilterActive; },
                onToggle: function(on) {
                    lmpMissingFilterActive = on;
                    applyFilters();
                }
            });
        }
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#macys-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#macys-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    applyFilters();
                }
            });
        }

        $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #roi-filter, #dil-filter, #sold-filter').on('change', function() {
            applyFilters();
        });

        function updateSummary() {
            const data = table.getData('active').filter(row => {
                return !(row.Parent && row.Parent.startsWith('PARENT'));
            });

            let totalPft = 0, totalSales = 0, totalPrice = 0, priceCount = 0;
            let totalInv = 0, zeroSoldCount = 0, moreSoldCount = 0, totalDil = 0, dilCount = 0;
            let totalCogs = 0, totalRoi = 0, roiCount = 0;
            let missingCount = 0, mappingCount = 0;
            let lessAmzCount = 0, moreAmzCount = 0;
            let zeroSoldRuleCount = 0;

            data.forEach(row => {
                totalPft += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;

                const roi = parseFloat(row['ROI%']) || 0;
                if (roi !== 0) {
                    totalRoi += roi;
                    roiCount++;
                }

                const price = parseFloat(row['MC Price']) || 0;
                const amazonPrice = parseFloat(row['A Price']) || 0;
                if (amazonPrice > 0 && price > 0 && price < amazonPrice) {
                    lessAmzCount++;
                }
                if (amazonPrice > 0 && price > 0 && price > amazonPrice) {
                    moreAmzCount++;
                }
                const inv = parseFloat(row.INV) || 0;
                const nrReq = row['nr_req'] || 'REQ';
                const isMissing = !isMacysListed(row);

                if (!isMissing && price > 0) {
                    totalPrice += price;
                    priceCount++;
                } else {
                    if (nrReq === 'REQ' && inv > 0) {
                        missingCount++;
                    }
                }

                totalInv += inv;
                const mcL30 = parseFloat(row['MC L30']) || 0;

                if (mcL30 === 0) {
                    zeroSoldCount++;
                    if (inv > 0 && amazonPrice > 0) {
                        zeroSoldRuleCount++;
                    }
                } else if (mcL30 > 0) {
                    moreSoldCount++;
                }

                const dil = parseFloat(row['MC Dil%']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }

                const lp = parseFloat(row['LP_productmaster']) || 0;
                totalCogs += lp * mcL30;

                if (nrReq === 'REQ' && inv > 0 && !isMissing) {
                    const ourInv = inv;
                    const mcInv = parseFloat(row['MC INV']) || 0;
                    if (Math.abs(ourInv - mcInv) > 3) {
                        mappingCount++;
                    }
                }
            });

            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            // ROI% = average of per-row ROI% — same formula as the BestBuy page (per-row ROI%
            // is computed with the Normal ship: (price*margin − lp − ship) / lp * 100).
            const avgRoi = roiCount > 0 ? totalRoi / roiCount : 0;
            // GPFT% = (Total PFT / Total Sales) * 100 — same aggregate formula as the eBay/BestBuy pages.
            const avgGpft = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;
            $('#total-pft-amt-badge').text(`PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#avg-gpft-badge').text(`GPFT: ${Math.round(avgGpft)}%`);
            // GROI from dollar totals when possible (matches /macys/daily-sales + master); else avg of row ROI%
            const groiBadge = totalCogs > 0 ? (totalPft / totalCogs) * 100 : avgRoi;
            $('#roi-percent-badge').text(`GROI: ${Math.round(groiBadge)}%`);
            // Macys has no ads — Ads%=0, NPFT=GPFT, NROI=GROI (same as /all-marketplace-master).
            $('#ads-percent-badge').text('Ads: 0%');
            $('#npft-percent-badge').text('NPFT: ' + Math.round(avgGpft) + '%');
            $('#nroi-percent-badge').text('NROI: ' + Math.round(groiBadge) + '%');
            $('#avg-price-badge').text(`Price: $${Math.round(avgPrice).toLocaleString()}`);
            $('#total-inv-badge').text(`Total INV: ${Math.round(totalInv).toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
            if (window.LmpMissingBadge && table) {
                LmpMissingBadge.update('#macys-lmp-missing-badge', table.getData(), 'macys');
            }
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#macys-price-gt-lmp-badge', table.getData(), 'macys', 'MC Price');
            }
            if (window.PriceLt80LmpBadge && table) {
                PriceLt80LmpBadge.update('#macys-price-lt80-lmp-badge', table.getData(), 'macys', 'MC Price');
            }
            $('#zero-sold-rule-badge').text(`0 Sold Rule: ${zeroSoldRuleCount}`);
            $('#more-sold-count-badge').text(`> 0 Sold: ${moreSoldCount.toLocaleString()}`);
            $('#avg-dil-badge').text(`DIL%: ${Math.round(avgDil * 100)}%`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#missing-badge').text(`Miss: ${missingCount}`);
            $('#mapping-badge').text(`N Map: ${mappingCount}`);
            $('#less-amz-badge').text(`< Amz: ${lessAmzCount.toLocaleString()}`);
            $('#more-amz-badge').text(`> Amz: ${moreAmzCount.toLocaleString()}`);
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const columns = table.getColumns();
            let html = `<li>
                    <button type="button" id="show-all-columns-item" class="dropdown-item fw-bold">
                        <i class="fa fa-eye"></i> Show All Columns
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>`;
            
            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;
                if (field && field !== '_select' && title && FORCED_HIDDEN_COLUMNS.indexOf(field) === -1) {
                    const isVisible = col.isVisible();
                    html += `<li class="dropdown-item">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" class="column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                            ${title.replace(/<[^>]*>/g, '')}
                        </label>
                    </li>`;
                }
            });
            
            $('#column-dropdown-menu').html(html);
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field && field !== '_select') {
                    visibility[field] = col.isVisible();
                }
            });
            
            $.ajax({
                url: '/macys-pricing-column-visibility',
                method: 'POST',
                data: {
                    visibility: visibility,
                    _token: '{{ csrf_token() }}'
                }
            });
        }

        // Columns that must always stay hidden, regardless of saved state.
        const FORCED_HIDDEN_COLUMNS = ['Parent'];
        function enforceForcedHiddenColumns() {
            FORCED_HIDDEN_COLUMNS.forEach(field => {
                const col = table.getColumn(field);
                if (col) {
                    try { col.hide(); } catch (e) {}
                }
            });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/macys-pricing-column-visibility',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) {
                                if (visibility[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                    }
                    enforceForcedHiddenColumns();
                    buildColumnDropdown();
                }
            });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            buildColumnDropdown();
            applyColumnVisibilityFromServer();
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyFilters();
            }, 100);
        });

        table.on('renderComplete', function() {
            setTimeout(function() {
                updateSummary();
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

        // Show All Columns — now an item inside the Columns dropdown (delegated, menu is rebuilt dynamically)
        document.getElementById("column-dropdown-menu").addEventListener("click", function(e) {
            const showAll = e.target.closest('#show-all-columns-item');
            if (!showAll) return;
            e.preventDefault();
            table.getColumns().forEach(col => {
                if (col.getField() !== '_select' && FORCED_HIDDEN_COLUMNS.indexOf(col.getField()) === -1) {
                    col.show();
                }
            });
            enforceForcedHiddenColumns();
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export Sheet button
        document.getElementById("export-btn").addEventListener("click", function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible() && col.getField() !== '_select');
            
            // Get headers
            const headers = visibleColumns.map(col => col.getDefinition().title || col.getField());
            exportData.push(headers);
            
            // Get filtered data
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
            link.setAttribute('download', 'macys_pricing_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });
    });
</script>
@endsection

