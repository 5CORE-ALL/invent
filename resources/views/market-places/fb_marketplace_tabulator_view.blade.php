@extends('layouts.vertical', ['title' => 'Fb Marketplace - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Toolbar: compact controls, wrap to next row if needed (matches ebay2 tabulator). */
        .ebay2-toolbar-row {
            row-gap: 4px;
        }
        .ebay2-toolbar-row > .form-select,
        .ebay2-toolbar-row > .btn,
        .ebay2-toolbar-row > .dropdown > .btn {
            padding: 3px 10px;
            font-size: 0.8125rem;
            line-height: 1.3;
            min-height: 30px;
        }
        .ebay2-toolbar-row .form-select {
            padding-right: 24px;
            background-position: right 6px center;
        }

        /* Summary badges wrap like Temu / TikTok so the full metric set stays visible */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 0 1 auto;
            min-width: 0;
            font-size: clamp(0.65rem, 0.4rem + 0.55vw, 0.85rem);
            padding: clamp(0.15rem, 0.3vw, 0.3rem) clamp(0.35rem, 0.6vw, 0.55rem);
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }
        #summary-stats .ebay2-summary-badge-row > .badge.is-active {
            outline: 3px solid #ffc107;
            outline-offset: 2px;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'fb_marketplace'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'fb_marketplace'])
        @include('partials.analytics-column-visibility', ['colVisPart' => 'css'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Fb Marketplace - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="d-flex align-items-center flex-wrap gap-2 ebay2-toolbar-row">
                    {{-- INV filter (0 INV / INV > 0) --}}
                    <select id="inv-filter" class="form-select form-select-sm" style="width: 120px;"
                        title="Filter rows by inventory">
                        <option value="all">All INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    {{-- DIL% slab filter — same color thresholds the Dil column already uses
                         (red &lt;25, green 25–50, pink ≥50). DIL = L30 / INV × 100. --}}
                    <select id="dil-filter" class="form-select form-select-sm" style="width: 120px;"
                        title="Filter rows by Dil% color band (L30 / INV × 100)">
                        <option value="all">DIL%</option>
                        <option value="red">Red (&lt;25%)</option>
                        <option value="green">Green (25–50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    {{-- Sold dropdown (mirrors Amazon tabulator + Mercari w/Ship + every other /pricing page).
                         Backed by the `sold` field (Mercari w/o Ship L30 sold qty — shown in the
                         "L30" column on this page; OV L30 lives in the `L30` field). --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width: 120px;"
                            title="Filter by FB L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <!-- Increase / Decrease / Same Price S Price controls -->
                    <button id="price-mode-btn" type="button" class="btn btn-sm btn-secondary"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>
                    <div id="discount-input-container" class="align-items-center gap-2" style="display: none;">
                        <span id="adjust-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="adjust-type-select-wrap">
                        <select id="adjust-type-select" class="form-select form-select-sm" style="width: 130px;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="adjust-amount-input" class="form-control form-control-sm"
                            placeholder="e.g. 10 or 2.50" step="0.1" min="0" style="width: 160px;">
                        <button id="apply-adjust-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="adjust-selected-count" class="text-muted small"></span>
                    </div>

                    {{-- Target ROI% bulk control — back-solves S Price for selected rows so SROI = Target ROI%.
                         Mercari W/O Ship's SPFT / SROI do NOT include shipping (matches the inline cellEdited handler
                         and the Apply % button below). Formula: sprice = LP × (1 + ROI%/100) / factor --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S Price = LP × (1 + Target ROI%/100) / factor on every selected row (back-solves so SROI column equals the target)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S Price'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S Price = LP × (1 + Target ROI%/100) / factor for every selected row">
                            <i class="fas fa-calculator"></i> Apply S Price
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S Price for selected rows so SPFT = Target GPFT%.
                         Formula: sprice = LP / (factor − GPFT%/100). Target GPFT% must be < factor*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S Price = LP / (factor − Target GPFT%/100) on every selected row (back-solves so SPFT column equals the target)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S Price'. Must be less than each row's take-home factor.">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S Price = LP / (factor − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i> Apply S Price
                        </button>
                    </div>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Show / hide columns">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" id="column-dropdown-menu"
                            aria-labelledby="columnVisibilityDropdown"></ul>
                    </div>
                    <button type="button" id="export-btn" class="btn btn-sm btn-warning ms-auto"
                        title="Export current (filtered) rows to CSV">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'buttons', 'ebaySprcDilChannel' => 'fb_marketplace'])
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'fb_marketplace'])
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#priceSoldUploadModal" title="Upload Price">
                        <i class="fas fa-upload"></i>
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-1 p-2 bg-light rounded">
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <span class="badge bg-dark fs-6 p-2" id="rows-count-badge"
                            style="color: #fff; font-weight: bold;"
                            title="Number of rows currently shown after filters">Rows: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                            style="color: #fff; font-weight: bold; cursor: pointer;"
                            title="INV &gt; 0 and FB L30 = 0. Click to filter.">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge"
                            style="background-color: #7dd3fc; color: #fff; font-weight: bold; cursor: pointer;"
                            title="INV &gt; 0 and FB L30 &gt; 0. Click to filter.">&gt; 0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="total-sales-amt-badge"
                            style="background-color: #14b8a6; color: #fff; font-weight: bold;"
                            title="Sales = Σ(Price × FB L30 sold)">Sales: $0</span>
                        <span class="badge fs-6 p-2" id="total-recovery-badge"
                            style="background-color: #38bdf8; color: #fff; font-weight: bold;"
                            title="Recovery = Sales × take-home factor (marketplace fee already out)">Recovery: $0</span>
                        <span class="badge fs-6 p-2" id="total-spend-badge"
                            style="background-color: #a78bfa; color: #fff; font-weight: bold;"
                            title="FB Marketplace has no listing ads API — Spend stays $0">Spend: $0</span>
                        <span class="badge fs-6 p-2" id="qty-sold-badge"
                            style="background-color: #6f42c1; color: #fff; font-weight: bold;"
                            title="Σ FB L30 units sold">Qty: 0</span>
                        <span class="badge fs-6 p-2" id="avg-pft-badge"
                            style="background-color: #3b82f6; color: #fff; font-weight: bold;"
                            title="GPFT% = Σ((Price × factor − LP) × Qty) / Sales × 100 (no ship)">GPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="avg-roi-badge"
                            style="background-color: #6c757d; color: #fff; font-weight: bold;"
                            title="GROI% = Σ((Price × factor − LP) × Qty) / Σ(LP × Qty) × 100 (no ship)">GROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ads-percent-badge"
                            style="background-color: #d63384; color: #fff; font-weight: bold;"
                            title="Ads% = Spend / Sales. FB Marketplace listing ads API is not wired — 0%.">Ads: 0%</span>
                        <span class="badge fs-6 p-2" id="avg-npft-badge"
                            style="background-color: #2563eb; color: #fff; font-weight: bold;"
                            title="NPFT% = GPFT% − Ads%">NPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="avg-nroi-badge"
                            style="background-color: #5b21b6; color: #fff; font-weight: bold;"
                            title="NROI% = GROI% − Ads%">NROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge"
                            style="color: #fff; font-weight: bold;"
                            title="Average Price of listed rows (Price &gt; 0)">Prc: $0.00</span>
                        <span class="badge fs-6 p-2" id="avg-cvr-badge"
                            style="background-color: #b91c1c; color: #fff; font-weight: bold;"
                            title="CVR = Σ FB L30 sold ÷ Σ views">CVR: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="missing-l-badge" style="color: #fff; font-weight: bold; cursor: pointer;" title="Click to filter: Price = 0 and NR/REQ = REQ">Missing L: 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'fbmarketplace-price-gt-lmp-badge', 'pglChannelKey' => 'fbmarketplace', 'pglPriceField' => 'price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'fbmarketplace-price-lt80-lmp-badge', 'pltChannelKey' => 'fbmarketplace', 'pltPriceField' => 'price'])
                        <span class="badge fs-6 p-2" id="fbmarketplace-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                    </div>
                </div>

                <!-- Price & Sold Upload Modal -->
                <div class="modal fade" id="priceSoldUploadModal" tabindex="-1"
                    aria-labelledby="priceSoldUploadModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('fb.marketplace.price-sold.import') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="priceSoldUploadModalLabel">Upload Price</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="woshipPriceSoldFile" class="form-label">Select file (.xlsx, .xls, .csv)</label>
                                        <input type="file" id="woshipPriceSoldFile" name="excel_file"
                                            class="form-control" accept=".xlsx,.xls,.csv" required>
                                    </div>
                                    <a href="{{ route('fb.marketplace.price-sold.sample') }}"
                                        class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-download"></i> Download Sample
                                    </a>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <input type="text" id="sku-search" class="form-control form-control-sm mt-2 mb-2"
                    placeholder="Search by Parent or SKU..." style="width: 100%;">

                <div id="fb-marketplace-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="fb-marketplace-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'fb_marketplace'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'modals', 'ebaySprcDilChannel' => 'fb_marketplace'])
@endsection

@section('script-bottom')
    <script>
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'fb_marketplace'])
        let table;
        let allTableData = [];
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        const TABULATOR_COLUMN_CHANNEL = 'fb_marketplace_tabulator';
        const FB_MP_COL_VIS_KEY = 'fb_marketplace_tabulator_column_visibility';
        const FB_MP_COL_SKIP = ['_select', '_parent_expand', 'missing_l'];
        let fbMpColumnVisibilityMap = {};
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'script', 'ebaySprcDilChannel' => 'fb_marketplace'])
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;
        let blueTriangleFilterActive = false;

        function fbMpRoundSprice(n) {
            const x = Number(n);
            return x > 0 ? Math.round(x) : 0;
        }
        function fbMpRowSpriceForAlert(data) {
            if (!data) return 0;
            let sprice = 0;
            if (typeof chPromoSavedOrLiveSprice === 'function') {
                sprice = Number(chPromoSavedOrLiveSprice(data)) || 0;
            } else {
                sprice = parseFloat(data.SPRICE != null ? data.SPRICE : data.sprice) || 0;
            }
            return fbMpRoundSprice(sprice);
        }
        function fbMpSpriceMetrics(d) {
            const sprice = fbMpRowSpriceForAlert(d);
            const lp = parseFloat(d && d.lp) || 0;
            const factor = parseFloat(d && d.factor) || 1;
            if (!(sprice > 0)) return { sprice: 0, spft: null, sroi: null };
            const pft = (sprice * factor) - lp;
            return {
                sprice: sprice,
                spft: (pft / sprice) * 100,
                sroi: lp > 0 ? (pft / lp) * 100 : 0,
            };
        }
        function fbMpHasBlueTriangle(data) {
            if (!data) return false;
            const sprice = fbMpRowSpriceForAlert(data);
            const price = parseFloat(data.price) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function syncFbMpTriangleBadgeState() {
            $('#fbmarketplace-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

    /** Std Prc vs channel price: reduce / hold / increase → red / yellow / green. */
    function fbMpStdPrcChangeDotMeta(stdPrc, comparePrice) {
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

    function fbMpStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = fbMpStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyFbMpStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            if (!d) return;
            const rowSku = String(d.sku || d['(Child) sku'] || d.SKU).trim();
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
        applyFbMpStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });


        document.addEventListener('DOMContentLoaded', function() {
            table = new Tabulator("#fb-marketplace-table", {
                ajaxURL: "{{ route('fb.marketplace.tabulator.data') }}",
                ajaxResponse: function(url, params, response) {
                    const payload = response.data || response;
                    allTableData = Array.isArray(payload) ? payload : [];
                    window.allTableData = allTableData;
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    updateBadges(payload);
                    return payload;
                },
                dataLoaded: function() {
                    // Apply the default filters (INV > 0) once data is present.
                    applyAllFilters();
                },
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                placeholder: "No Data Available",
                selectableRows: true,
                columns: [
                    {
                        field: "_select",
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        titleFormatterParams: { rowRange: "active" },
                        headerSort: false,
                        hozAlign: "center",
                        width: 40,
                        frozen: true,
                        visible: true
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
                        visible: false,
                        formatter: function(cell) {
                            const val = cell.getValue();
                            return (val != null && String(val).trim() !== '') ? String(val).trim() : '—';
                        }
                    },
                    ParentExpand.columnDef(),
                    {
                        title: "Image",
                        field: "image_path",
                        hozAlign: "center",
                        width: 80,
                        headerSort: false,
                        formatter: function(cell) {
                            const imagePath = cell.getValue();
                            if (imagePath) {
                                return `<img src="${imagePath}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" />`;
                            }
                            return '';
                        }
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        frozen: true,
                        width: 250,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            if (sku === null || sku === undefined || String(sku).trim() === '') return '';
                            const safe = String(sku).replace(/"/g, '&quot;');
                            return `<span class="sku-text">${safe}</span>` +
                                `<i class="fas fa-copy sku-copy-btn" data-sku="${safe}" title="Copy SKU" ` +
                                `style="cursor:pointer;margin-left:8px;color:#6c757d;"></i>`;
                        },
                        cellClick: function(e, cell) {
                            const btn = e.target.closest('.sku-copy-btn');
                            if (!btn) return;
                            e.stopPropagation();
                            const sku = btn.getAttribute('data-sku');
                            const done = function() {
                                btn.classList.remove('fa-copy');
                                btn.classList.add('fa-check');
                                btn.style.color = '#28a745';
                                setTimeout(function() {
                                    btn.classList.remove('fa-check');
                                    btn.classList.add('fa-copy');
                                    btn.style.color = '#6c757d';
                                }, 1000);
                            };
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(sku).then(done).catch(function() {});
                            } else {
                                const ta = document.createElement('textarea');
                                ta.value = sku;
                                document.body.appendChild(ta);
                                ta.select();
                                try { document.execCommand('copy'); done(); } catch (err) {}
                                document.body.removeChild(ta);
                            }
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0);
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0);
                        }
                    },
                    {
                        title: "Dil",
                        field: "Dil",
                        hozAlign: "center",
                        width: 60,
                        sorter: function(a, b, aRow, bRow) {
                            const calcDil = (row) => {
                                const inv = parseFloat(row.INV) || 0;
                                const l30 = parseFloat(row.L30) || 0;
                                return inv === 0 ? 0 : (l30 / inv) * 100;
                            };
                            return calcDil(aRow.getData()) - calcDil(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData.L30) || 0;

                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                            const dil = (OVL30 / INV) * 100;
                            let color = '';
                            if (dil < 25) color = '#dc3545';
                            else if (dil < 50) color = '#28a745';
                            else color = '#e83e8c';

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: "L30",
                        field: "sold",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell) {
                            return Math.round(parseFloat(cell.getValue()) || 0);
                        }
                    },
                    {
                        title: "CVR%",
                        field: "CVR%",
                        hozAlign: "center",
                        width: 64,
                        sorter: "number",
                        headerTooltip: "CVR = FB L30 sold ÷ views",
                        formatter: function(cell) {
                            const d = cell.getRow().getData() || {};
                            const value = (typeof chPromoCvr === 'function')
                                ? Number(chPromoCvr(d))
                                : (parseFloat(cell.getValue()) || 0);
                            if (!(value > 0)) return '<span style="color:#6c757d;">0%</span>';
                            return '<span style="font-weight:600;">' + (Math.round(value * 10) / 10) + '%</span>';
                        }
                    },
                    {
                        title: "Std Prc",
                        field: "STANDARD_PRICE",
                        hozAlign: "center",
                        headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view. Editable; saves to all Sku Link LMP siblings. Dot vs channel price.",
                        editor: "input",
                        width: 70,
                        sorter: "number",
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            const sku = String(d.sku || d['(Child) sku'] || d.SKU || '');
                            return !!sku;
                        },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const value = cell.getValue();
                            const std = parseFloat(value) || 0;
                            if (!value || std <= 0) return '';
                            const comparePrice = parseFloat(d.price || 0) || 0;
                            const dot = fbMpStdPrcChangeDotHtml(std, comparePrice);

                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' + dot + ('$' + std.toFixed(2)) + '</span>';
                        }
                    },
                    ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                    {
                        title: "Sprc Dil",
                        field: "SPRC_DIL",
                        hozAlign: "center",
                        headerSort: true,
                        sorter: function(a, b, aRow, bRow) {
                            const val = function(row) {
                                return (typeof ebaySprcDilForRow === 'function')
                                    ? (ebaySprcDilForRow(row) || 0)
                                    : 0;
                            };
                            return val(aRow.getData()) - val(bRow.getData());
                        },
                        headerTooltip: "S PRC from Dil → Target GROI% slabs. 0 Sold (FB L30 = 0, INV > 0) uses the lowest Target GROI in the table. Formula: LP × (1 + GROI%/100) / margin (no ship). CVR% still fills S PRC when Dil does not match a slab.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(rowData)) return '';
                            if (typeof ebayDilGroiMetaForRow !== 'function') return '';
                            const meta = ebayDilGroiMetaForRow(rowData);
                            if (!meta || !(meta.sprc > 0)) return '';
                            const sprc = fbMpRoundSprice(meta.sprc);
                            const tip = 'Dil ' + (isFinite(meta.dil) ? meta.dil.toFixed(1) : '0') + '%'
                                + ' → ' + meta.label
                                + ' → GROI ' + meta.groi + '%'
                                + ' → $' + sprc.toFixed(2);
                            return '<span title="' + String(tip).replace(/"/g, '&quot;') + '" style="font-weight:600;color:#6f42c1;">$'
                                + sprc.toFixed(2) + '</span>';
                        },
                        width: 78
                    },
                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "center",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const rowData = cell.getRow().getData();
                            const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(value, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                            const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(value, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                            return '$' + value.toFixed(2) + lmpTri + purpleTri;
                        }
                    },
                    {
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        width: 92,
                        sorter: "number",
                        headerTooltip: "S PRC from Sprc Dil when Dil matches and FB L30 > 0; 0 Sold uses the lowest Target GROI. Otherwise Std × (1 − CVR%/100). S PRC = LP × (1 + GROI%/100) / margin (no ship). Blue triangle = S PRC ≠ Price. Red text = S PRC ≥ LMP.",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            let value = (typeof chPromoSavedOrLiveSprice === 'function')
                                ? Number(chPromoSavedOrLiveSprice(d))
                                : parseFloat(cell.getValue() || d.sprice || 0);
                            value = fbMpRoundSprice(value);
                            if (!(value > 0)) return '';
                            const live = parseFloat(d.price) || 0;
                            const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                            const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(d, value) : null;
                            const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                            const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC ≥ LMP"></i>') : '';
                            const formatted = '$' + value.toFixed(2);
                            const priceHtml = overLmp
                                ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                                : '<span style="font-weight:600;">' + formatted + '</span>';
                            const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">' + priceHtml + redTri + blueTri + '</span>';
                        }
                    },
                    {
                        title: "Missing L",
                        field: "missing_l",
                        hozAlign: "center",
                        headerSort: false,
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const price = parseFloat(row.price) || 0;
                            const nr = row.nr_req || '';
                            if (price === 0 && nr === 'REQ') {
                                return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "GPFT",
                        field: "PFT",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        headerTooltip: "GPFT% from listing Price: (Price × factor − LP) / Price (no ship)",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const color = value < 0 ? '#dc3545' : (value < 10 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "GROI",
                        field: "ROI",
                        hozAlign: "center",
                        width: 70,
                        sorter: "number",
                        headerTooltip: "GROI% from listing Price: (Price × factor − LP) / LP (no ship)",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const color = value < 0 ? '#dc3545' : (value < 40 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "Status",
                        field: "approved",
                        hozAlign: "center",
                        headerSort: false,
                        width: 80,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            // Default (null/undefined) is "checked" (approved). Only an explicit 0 means rejected.
                            const isNo = v === 0 || v === '0' || v === false;
                            return isNo
                                ? `<i class="fas fa-times mc-toggle" title="Rejected — click to approve" style="cursor:pointer;font-size:16px;color:#dc3545;"></i>`
                                : `<i class="fas fa-check mc-toggle" title="Approved — click to reject" style="cursor:pointer;font-size:16px;color:#28a745;"></i>`;
                        },
                        cellClick: function(e, cell) {
                            if (!e.target.classList.contains('mc-toggle')) return;
                            const row = cell.getRow();
                            const d = row.getData();
                            const isNo = d.approved === 0 || d.approved === '0' || d.approved === false;
                            const newVal = isNo ? 1 : 0;
                            row.update({ approved: newVal });
                            saveMercariStatus(d.sku, { approved: newVal });
                        }
                    },
                    {
                        title: "S GPFT",
                        field: "SPFT",
                        hozAlign: "center",
                        width: 70,
                        sorter: function(a, b, aRow, bRow) {
                            const av = fbMpSpriceMetrics(aRow.getData()).spft;
                            const bv = fbMpSpriceMetrics(bRow.getData()).spft;
                            return (av == null ? -9999 : av) - (bv == null ? -9999 : bv);
                        },
                        headerTooltip: "S GPFT from S PRC: (S PRC × factor − LP) / S PRC (no ship)",
                        formatter: function(cell) {
                            const m = fbMpSpriceMetrics(cell.getRow().getData());
                            if (m.spft == null) return '—';
                            const color = m.spft < 0 ? '#dc3545' : (m.spft < 10 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(m.spft)}%</span>`;
                        }
                    },
                    {
                        title: "S GROI",
                        field: "SROI",
                        hozAlign: "center",
                        width: 70,
                        sorter: function(a, b, aRow, bRow) {
                            const av = fbMpSpriceMetrics(aRow.getData()).sroi;
                            const bv = fbMpSpriceMetrics(bRow.getData()).sroi;
                            return (av == null ? -9999 : av) - (bv == null ? -9999 : bv);
                        },
                        headerTooltip: "S GROI from S PRC: (S PRC × factor − LP) / LP (no ship)",
                        formatter: function(cell) {
                            const m = fbMpSpriceMetrics(cell.getRow().getData());
                            if (m.sroi == null) return '—';
                            const color = m.sroi < 0 ? '#dc3545' : (m.sroi < 40 ? '#ffc107' : '#28a745');
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(m.sroi)}%</span>`;
                        }
                    },
                    {
                        title: "NR/REQ",
                        field: "nr_req",
                        hozAlign: "center",
                        width: 90,
                        editor: "list",
                        editorParams: { values: { "REQ": "REQ", "NR": "NR" } },
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '—';
                            const color = v === 'REQ' ? '#28a745' : '#dc3545';
                            return `<span title="${v}" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${color};"></span>`;
                        },
                        cellEdited: function(cell) {
                            const d = cell.getRow().getData();
                            saveMercariStatus(d.sku, { nr_req: cell.getValue() });
                        }
                    },
                    {
                        title: "B/S",
                        field: "buyer_link",
                        hozAlign: "center",
                        headerSort: false,
                        width: 90,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            let html = '';
                            if (row.buyer_link) {
                                html += `<a href="${row.buyer_link}" target="_blank" style="color:#007bff;text-decoration:underline;margin-right:6px;">B</a>`;
                            }
                            if (row.seller_link) {
                                html += `<a href="${row.seller_link}" target="_blank" style="color:#28a745;text-decoration:underline;">S</a>`;
                            }
                            return html || '—';
                        }
                    }
                ],
            });

            table.on('cellEdited', function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const data = row.getData();
                const value = cell.getValue();

                if (field === 'STANDARD_PRICE') {
                    
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
                            applyFbMpStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                            const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                            if (typeof showToast === 'function') showToast(n > 1 ? ('Std Prc saved for ' + n + ' linked SKUs') : 'Std Prc saved', 'success');
                        },
                        error: function() {
                            if (typeof showToast === 'function') showToast('Failed to save Std Prc', 'error');
                        }
                    });
                    return;
                }
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'Parent',
                    skuField: 'sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    onAfterExpand: () => { if (typeof updateBadges === 'function') updateBadges(); },
                    onCollapse: () => { if (typeof applyAllFilters === 'function') applyAllFilters(); },
                });
                ParentExpand.bind();
            }

            function fbMpColumnField(col) {
                if (!col) return '';
                const def = (typeof col.getDefinition === 'function') ? (col.getDefinition() || {}) : {};
                return def.field || (typeof col.getField === 'function' ? col.getField() : '') || '';
            }
            function fbMpVisibilityIsOn(v) {
                return v === true || v === 1 || v === '1' || v === 'true';
            }
            function readFbMpColumnVisibilityLocal() {
                try {
                    const raw = localStorage.getItem(FB_MP_COL_VIS_KEY);
                    const parsed = raw ? JSON.parse(raw) : {};
                    return (parsed && typeof parsed === 'object') ? parsed : {};
                } catch (e) {
                    return {};
                }
            }
            function writeFbMpColumnVisibilityLocal(map) {
                try { localStorage.setItem(FB_MP_COL_VIS_KEY, JSON.stringify(map || {})); } catch (e) {}
            }
            function applyFbMpColumnVisibilityMap(map) {
                if (!table || !map || typeof map !== 'object') return;
                fbMpColumnVisibilityMap = map;
                table.getColumns().forEach(function(col) {
                    const field = fbMpColumnField(col);
                    if (!field || FB_MP_COL_SKIP.indexOf(field) !== -1) return;
                    if (!Object.prototype.hasOwnProperty.call(map, field)) return;
                    if (fbMpVisibilityIsOn(map[field])) col.show();
                    else col.hide();
                });
            }
            function collectFbMpColumnVisibility() {
                const visibility = {};
                if (!table) return visibility;
                table.getColumns().forEach(function(col) {
                    const field = fbMpColumnField(col);
                    if (!field || FB_MP_COL_SKIP.indexOf(field) !== -1) return;
                    visibility[field] = !!col.isVisible();
                });
                return visibility;
            }
            function buildColumnDropdown(savedVisibility) {
                if (window.AnalyticsColVis) {
                    window.AnalyticsColVis.install({
                        getTable: function() { return table; },
                        menuId: 'column-dropdown-menu',
                        storageKey: 'fb_marketplace_col_cats_v1',
                        skipFields: FB_MP_COL_SKIP.slice(),
                        onSave: function() {
                            if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
                        }
                    });
                    window.AnalyticsColVis.rebuild(savedVisibility || fbMpColumnVisibilityMap || null);
                    return;
                }
                const menu = document.getElementById('column-dropdown-menu');
                if (!menu || !table) return;
                const map = (savedVisibility && typeof savedVisibility === 'object')
                    ? savedVisibility
                    : fbMpColumnVisibilityMap;
                menu.innerHTML = '';
                table.getColumns().forEach(function(col) {
                    const field = fbMpColumnField(col);
                    const title = (col.getDefinition() || {}).title;
                    if (!field || FB_MP_COL_SKIP.indexOf(field) !== -1 || !title) return;
                    const isVisible = Object.prototype.hasOwnProperty.call(map, field)
                        ? fbMpVisibilityIsOn(map[field])
                        : col.isVisible();
                    const li = document.createElement('li');
                    li.className = 'dropdown-item';
                    li.innerHTML = '<label style="cursor:pointer;display:flex;align-items:center;gap:8px;margin:0;">'
                        + '<input type="checkbox" class="column-toggle" data-field="' + field + '"'
                        + (isVisible ? ' checked' : '') + '> '
                        + String(title).replace(/<[^>]*>/g, '') + '</label>';
                    menu.appendChild(li);
                });
            }
            function saveColumnVisibilityToServer() {
                if (!table) return;
                const visibility = collectFbMpColumnVisibility();
                fbMpColumnVisibilityMap = visibility;
                writeFbMpColumnVisibilityLocal(visibility);
                fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        visibility: visibility
                    })
                }).catch(function(err) { console.error('Column visibility save failed:', err); });
            }
            function fbMpNormalizeVisibilityMap(raw) {
                if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return {};
                return raw;
            }
            function applyColumnVisibilityFromServer() {
                if (!table) return Promise.resolve();
                return fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function(response) { return response.json(); })
                    .then(function(savedVisibility) {
                        const serverMap = fbMpNormalizeVisibilityMap(savedVisibility);
                        const localMap = readFbMpColumnVisibilityLocal();
                        const map = Object.keys(serverMap).length ? serverMap : localMap;
                        applyFbMpColumnVisibilityMap(map);
                        buildColumnDropdown(map);
                    })
                    .catch(function(err) {
                        console.error('Column visibility load failed:', err);
                        const localMap = readFbMpColumnVisibilityLocal();
                        applyFbMpColumnVisibilityMap(localMap);
                        buildColumnDropdown(localMap);
                    });
            }
            applyColumnVisibilityFromServer();
            $(document).on('change', '#column-dropdown-menu .column-toggle', function(e) {
                if (!table || !e.target) return;
                const field = e.target.getAttribute('data-field');
                if (!field) return;
                const col = table.getColumn(field);
                if (col) e.target.checked ? col.show() : col.hide();
                saveColumnVisibilityToServer();
            });

            // Update the adjust panel whenever row selection changes
            table.on("rowSelectionChanged", function(data, rows) {
                updateAdjustPanel();
            });

            const searchInput = document.getElementById('sku-search');
            if (searchInput) {
                // Funnel into applyAllFilters() so SKU search stacks with the Missing L
                // badge and the Sold dropdown (used to overwrite them with setFilter).
                searchInput.addEventListener('keyup', applyAllFilters);
            }

            // INV dropdown change — same funnel, stacks with the other filters.
            const invFilterEl = document.getElementById('inv-filter');
            if (invFilterEl) invFilterEl.addEventListener('change', applyAllFilters);

            // DIL% dropdown change — same funnel, stacks with the other filters.
            const dilFilterEl = document.getElementById('dil-filter');
            if (dilFilterEl) dilFilterEl.addEventListener('change', applyAllFilters);

            // Sold dropdown change — same funnel, stacks with the other two filters.
            const soldFilterEl = document.getElementById('sold-filter');
            if (soldFilterEl) soldFilterEl.addEventListener('change', applyAllFilters);

            // Export current (filtered) rows to CSV
            const exportBtn = document.getElementById('export-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    if (typeof table !== 'undefined' && table.download) {
                        table.download('csv', 'fb-marketplace.csv');
                    }
                });
            }

            // Price % toggle — cycle Off → Decrease → Increase → Off
            const priceModeBtn = document.getElementById('price-mode-btn');
            if (priceModeBtn) {
                priceModeBtn.addEventListener('click', function() {
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
            }

            // Increase / Decrease / Same Price S Price — apply to selected rows
            const applyBtn = document.getElementById('apply-adjust-btn');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    const mode = samePriceModeActive ? 'same' : (increaseModeActive ? 'increase' : 'decrease');
                    const type = document.getElementById('adjust-type-select').value;
                    const amount = parseFloat(document.getElementById('adjust-amount-input').value);

                    const selectedRows = table.getSelectedRows();
                    if (selectedRows.length === 0) {
                        alert('Please select at least one row.');
                        return;
                    }
                    if (isNaN(amount) || amount <= 0) {
                        alert(samePriceModeActive ? 'Please enter a valid price.' : 'Please enter a valid amount.');
                        return;
                    }

                    selectedRows.forEach(function(row) {
                        const d = row.getData();
                        const basePrice = parseFloat(d.price) || 0;

                        // Decrease / Increase need a positive base Price; Same Price applies regardless.
                        if (mode !== 'same' && basePrice <= 0) return;

                        let newPrice;
                        if (mode === 'same') {
                            newPrice = Math.max(0.01, amount);
                        } else if (type === 'percentage') {
                            const decimal = amount / 100;
                            newPrice = mode === 'decrease' ? basePrice * (1 - decimal) : basePrice * (1 + decimal);
                        } else {
                            newPrice = mode === 'decrease' ? Math.max(0.01, basePrice - amount) : basePrice + amount;
                        }
                        newPrice = Math.max(0.01, newPrice);
                        newPrice = roundToRetailPrice(newPrice);
                        // Auto-bump to .49 only when Decrease/Increase would equal the source.
                        // Same Price honors the typed value exactly.
                        if (mode !== 'same' && newPrice.toFixed(2) === basePrice.toFixed(2)) {
                            newPrice = roundToRetailPrice49(newPrice);
                        }
                        newPrice = fbMpRoundSprice(newPrice);

                        // recompute SPFT/SROI from new sprice
                        const lp = parseFloat(d.lp) || 0;
                        const factor = parseFloat(d.factor) || 1;
                        const spft = newPrice > 0 ? ((newPrice * factor - lp) / newPrice) * 100 : 0;
                        const sroi = lp > 0 ? ((newPrice * factor - lp) / lp) * 100 : 0;

                        row.update({
                            SPRICE: newPrice,
                            sprice: newPrice,
                            SPFT: Math.round(spft * 100) / 100,
                            SROI: Math.round(sroi * 100) / 100
                        });
                        saveMercariStatus(d.sku, { sprice: newPrice });
                    });
                });
            }

            /*
             * Target ROI% / Target GPFT% bulk apply (Mercari W/O Ship, margin = per-row `factor`, default 1)
             * ----------------------------------------------------------------------------------------------
             * Back-solves S Price so the resulting SROI / SPFT column matches the entered target.
             * Mercari W/O Ship's SPFT / SROI formulas (used in the inline cellEdited handler and
             * the Apply % / Same Price button above) do NOT include shipping:
             *     SPFT% = ((sprice * factor − lp) / sprice) * 100
             *     SROI% = ((sprice * factor − lp) / lp)     * 100
             *   → sprice = lp * (1 + ROI%/100) / factor
             *   → sprice = lp / (factor − GPFT%/100)
             * Selection uses Tabulator's native getSelectedRows() (matches the existing Apply %
             * flow). Each row is persisted via saveMercariStatus(sku, { sprice }) so the same
             * /mercari-without-ship-tabulator/save-status endpoint stores the new S Price.
             * Plain 2-decimal rounding — no .99 / .49 retail snapping — because snapping would
             * shift the achieved SROI / SPFT off the user-typed target.
             */
            function applyMercariWoShipTargetBackSolve(computeFn, labelPrefix) {
                const selectedRows = table.getSelectedRows();
                if (selectedRows.length === 0) {
                    alert('Please select at least one row first (turn on Price % to reveal checkboxes).');
                    return;
                }

                let updatedCount  = 0;
                let skippedNoLp   = 0;
                let skippedHigh   = 0;

                selectedRows.forEach(function (row) {
                    const d  = row.getData();
                    const lp = parseFloat(d.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const factor = parseFloat(d.factor) || 1;

                    const computed = computeFn(lp, factor);
                    if (computed == null) { skippedHigh++; return; }
                    const newPrice = fbMpRoundSprice(computed);
                    if (!isFinite(newPrice) || newPrice <= 0) return;

                    const spft = newPrice > 0 ? ((newPrice * factor - lp) / newPrice) * 100 : 0;
                    const sroi = lp > 0       ? ((newPrice * factor - lp) / lp)     * 100 : 0;

                    row.update({
                        SPRICE: newPrice,
                        sprice: newPrice,
                        SPFT: Math.round(spft * 100) / 100,
                        SROI: Math.round(sroi * 100) / 100
                    });
                    saveMercariStatus(d.sku, { sprice: newPrice });
                    updatedCount++;
                });

                if (updatedCount === 0) {
                    if (skippedHigh > 0) {
                        alert(labelPrefix + ' too high — must be less than each row\'s take-home factor.');
                    } else {
                        alert('No selected rows have a usable LP > 0.');
                    }
                    return;
                }

                let note = '';
                if (skippedNoLp > 0) note += ' (' + skippedNoLp + ' skipped — no LP)';
                if (skippedHigh > 0) note += ' (' + skippedHigh + ' skipped — target ≥ factor)';
                if (window.toastr) {
                    toastr.success(labelPrefix + ' applied to ' + updatedCount + ' SKU(s)' + note);
                } else {
                    console.info(labelPrefix + ' applied to ' + updatedCount + ' SKU(s)' + note);
                }
            }

            const applyTargetRoiBtn = document.getElementById('apply-target-roi-btn');
            if (applyTargetRoiBtn) {
                applyTargetRoiBtn.addEventListener('click', function () {
                    const rawInput = document.getElementById('target-roi-input').value;
                    const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

                    if (rawInput === '' || rawInput == null) { alert('Please enter a Target ROI%.'); return; }
                    if (!isFinite(targetRoiPct))             { alert('Target ROI% must be a number.'); return; }

                    const roiMultiplier = 1 + (targetRoiPct / 100);
                    applyMercariWoShipTargetBackSolve(function (lp, factor) {
                        return (lp * roiMultiplier) / factor;
                    }, 'Target ROI ' + targetRoiPct + '%');
                });
            }

            const applyTargetGpftBtn = document.getElementById('apply-target-gpft-btn');
            if (applyTargetGpftBtn) {
                applyTargetGpftBtn.addEventListener('click', function () {
                    const rawInput = document.getElementById('target-gpft-input').value;
                    const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

                    if (rawInput === '' || rawInput == null) { alert('Please enter a Target GPFT%.'); return; }
                    if (!isFinite(targetGpftPct))            { alert('Target GPFT% must be a number.'); return; }

                    const targetFraction = targetGpftPct / 100;
                    applyMercariWoShipTargetBackSolve(function (lp, factor) {
                        const denom = factor - targetFraction;
                        if (denom <= 0) return null; // signals "target ≥ factor" skip
                        return lp / denom;
                    }, 'Target GPFT ' + targetGpftPct + '%');
                });
            }

            const targetRoiInput = document.getElementById('target-roi-input');
            if (targetRoiInput) {
                targetRoiInput.addEventListener('keypress', function (e) {
                    if (e.which === 13 || e.keyCode === 13) applyTargetRoiBtn && applyTargetRoiBtn.click();
                });
            }
            const targetGpftInput = document.getElementById('target-gpft-input');
            if (targetGpftInput) {
                targetGpftInput.addEventListener('keypress', function (e) {
                    if (e.which === 13 || e.keyCode === 13) applyTargetGpftBtn && applyTargetGpftBtn.click();
                });
            }

            // Missing L badge — click to toggle. Filter logic now lives in applyAllFilters()
            // so Missing L stacks with the SKU search and the Sold dropdown (used to
            // overwrite them via direct setFilter/clearFilter calls).
            const missingLBadge = document.getElementById('missing-l-badge');
            if (missingLBadge) {
                missingLBadge.addEventListener('click', function() {
                    missingLFilterActive = !missingLFilterActive;
                    const mCol = table.getColumn('missing_l');
                    if (missingLFilterActive) {
                        if (mCol) mCol.show();
                        missingLBadge.classList.remove('bg-secondary');
                        missingLBadge.classList.add('bg-dark');
                    } else {
                        if (mCol) mCol.hide();
                        missingLBadge.classList.remove('bg-dark');
                        missingLBadge.classList.add('bg-secondary');
                    }
                    applyAllFilters();
                });
            }
        });

        // Round to retail pricing (same as ebay-tabulator-view)
        function roundToRetailPrice(price) {
            price = parseFloat(price) || 0;
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.01).toFixed(2);
        }
        // .49 endings fallback — used when .99 would match the current price
        function roundToRetailPrice49(price) {
            price = parseFloat(price) || 0;
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.51).toFixed(2);
        }

        let missingLFilterActive = false;
        let decreaseModeActive = false;
        let increaseModeActive = false;
        let samePriceModeActive = false;

        // Show the adjust panel only when a mode is active AND rows are selected
        function updateAdjustPanel() {
            const container = document.getElementById('discount-input-container');
            const countEl = document.getElementById('adjust-selected-count');
            const selectedCount = (typeof table !== 'undefined' && table.getSelectedRows)
                ? table.getSelectedRows().length
                : 0;
            const modeOn = decreaseModeActive || increaseModeActive || samePriceModeActive;
            if (countEl) countEl.textContent = selectedCount ? (selectedCount + ' selected') : '';
            if (container) container.style.display = (modeOn && selectedCount > 0) ? 'flex' : 'none';
        }

        // Swap the adjust-input panel between %/$ and Same Price modes.
        function syncAdjustInputUi() {
            const wrap = document.getElementById('adjust-type-select-wrap');
            const label = document.getElementById('adjust-input-label');
            const input = document.getElementById('adjust-amount-input');
            const applyBtn = document.getElementById('apply-adjust-btn');
            if (samePriceModeActive) {
                if (wrap) wrap.style.display = 'none';
                if (label) label.classList.remove('d-none');
                if (input) {
                    input.setAttribute('placeholder', 'Enter price (e.g. 19.99)');
                    input.setAttribute('step', '0.01');
                }
                if (applyBtn) applyBtn.innerHTML = '<i class="fas fa-check"></i> Apply Same Price';
            } else {
                if (wrap) wrap.style.display = '';
                if (label) label.classList.add('d-none');
                if (input) {
                    input.setAttribute('placeholder', 'e.g. 10 or 2.50');
                    input.setAttribute('step', '0.1');
                }
                if (applyBtn) applyBtn.innerHTML = '<i class="fas fa-check"></i> Apply';
            }
        }

        function syncPriceModeUi() {
            const btn = document.getElementById('price-mode-btn');
            const selectCol = (typeof table !== 'undefined' && table.getColumn) ? table.getColumn('_select') : null;

            btn.classList.remove('btn-secondary', 'btn-danger', 'btn-success', 'btn-info');

            if (decreaseModeActive) {
                btn.classList.add('btn-danger');
                btn.innerHTML = '<i class="fas fa-arrow-down"></i> Decrease ON';
                if (selectCol) selectCol.show();
            } else if (increaseModeActive) {
                btn.classList.add('btn-success');
                btn.innerHTML = '<i class="fas fa-arrow-up"></i> Increase ON';
                if (selectCol) selectCol.show();
            } else if (samePriceModeActive) {
                btn.classList.add('btn-info');
                btn.innerHTML = '<i class="fas fa-equals"></i> Same Price ON';
                if (selectCol) selectCol.show();
            } else {
                btn.classList.add('btn-secondary');
                btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Price %';
                // Keep the selection checkbox column always visible (do not hide or clear selection).
                if (selectCol) selectCol.show();
            }
            syncAdjustInputUi();
            updateAdjustPanel();
        }

        function missingLFilter(row) {
            const price = parseFloat(row.price) || 0;
            const nr = row.nr_req || '';
            return price === 0 && nr === 'REQ';
        }

        function fbMpFilterState() {
            const searchEl = document.getElementById('sku-search');
            const invEl = document.getElementById('inv-filter');
            const dilEl = document.getElementById('dil-filter');
            const soldEl = document.getElementById('sold-filter');
            return {
                skuSearch: (searchEl ? (searchEl.value || '') : '').trim().toLowerCase(),
                invFilter: invEl ? invEl.value : 'all',
                dilFilter: dilEl ? dilEl.value : 'all',
                soldFilter: soldEl ? soldEl.value : 'all',
            };
        }

        function fbMpRowPassesFilters(row) {
            if (!row) return false;
            const st = fbMpFilterState();

            if (st.skuSearch) {
                const sku = String(row.sku || '').toLowerCase();
                const parent = String(row.Parent || '').toLowerCase();
                if (sku.indexOf(st.skuSearch) === -1 && parent.indexOf(st.skuSearch) === -1) return false;
            }

            if (missingLFilterActive && !missingLFilter(row)) return false;
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge && !PriceGtLmpBadge.hasRedTriangle(row, 'price')) {
                return false;
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge && !PriceLt80LmpBadge.hasPurpleTriangle(row, 'price')) {
                return false;
            }
            if (blueTriangleFilterActive && !fbMpHasBlueTriangle(row)) {
                return false;
            }

            if (st.invFilter && st.invFilter !== 'all') {
                const inv = parseFloat(row.INV) || 0;
                if (st.invFilter === 'zero' && !(inv === 0)) return false;
                if (st.invFilter === 'more' && !(inv > 0)) return false;
            }

            if (st.dilFilter && st.dilFilter !== 'all') {
                const inv = parseFloat(row.INV) || 0;
                const l30 = parseFloat(row.L30) || 0;
                const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                if (st.dilFilter === 'red' && !(dil < 25)) return false;
                if (st.dilFilter === 'green' && !(dil >= 25 && dil < 50)) return false;
                if (st.dilFilter === 'pink' && !(dil >= 50)) return false;
            }

            if (st.soldFilter && st.soldFilter !== 'all') {
                const soldQty = parseFloat(row.sold) || 0;
                if (st.soldFilter === 'sold' && !(soldQty > 0)) return false;
                if (st.soldFilter === 'zero' && !(soldQty === 0)) return false;
            }

            return true;
        }

        // Unified filter — combines SKU/Parent search, the Missing L badge toggle, and
        // the Sold dropdown into one Tabulator filter so they STACK instead of
        // overwriting each other (matches the Mercari w/Ship pattern). All three filter
        // entry points (search keyup, badge click, Sold dropdown change) call this.
        function applyAllFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyAllFilters(); });
                if (typeof updateBadges === 'function') updateBadges();
                return;
            }
            if (typeof table !== 'undefined' && table && table.setFilter) {
                table.setFilter(function(row) {
                    return fbMpRowPassesFilters(row);
                });
            }
            if (typeof updateBadges === 'function') updateBadges();
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#fbmarketplace-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyAllFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#fbmarketplace-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyAllFilters();
                }
            });
        }
        $('#fbmarketplace-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
            }
            applyAllFilters();
            syncFbMpTriangleBadgeState();
        });

        function fbMpVisibleRows(fallback) {
            const src = (Array.isArray(allTableData) && allTableData.length)
                ? allTableData
                : (Array.isArray(window.allTableData) && window.allTableData.length)
                    ? window.allTableData
                    : (Array.isArray(fallback) ? fallback : []);
            return src.filter(fbMpRowPassesFilters);
        }
        function fbMpMoney(n) {
            return '$' + Math.round(Number(n) || 0).toLocaleString();
        }
        function syncFbMpSoldBadgeState() {
            const soldEl = document.getElementById('sold-filter');
            const soldFilter = soldEl ? soldEl.value : 'all';
            $('#zero-sold-count-badge').toggleClass('is-active', soldFilter === 'zero');
            $('#more-sold-count-badge').toggleClass('is-active', soldFilter === 'sold');
        }
        function updateBadges(data) {
            const rows = fbMpVisibleRows(data);
            let missingL = 0;
            let zeroSold = 0;
            let moreSold = 0;
            let sales = 0;
            let recovery = 0;
            let qty = 0;
            let pftDollars = 0;
            let cogs = 0;
            let views = 0;
            let priceSum = 0;
            let pricedCount = 0;
            let blueTriangleCount = 0;

            rows.forEach(function(row) {
                const nr = row.nr_req || '';
                const price = parseFloat(row.price) || 0;
                const soldQty = parseFloat(row.sold) || 0;
                const inv = parseFloat(row.INV) || 0;
                const lp = parseFloat(row.lp) || 0;
                const factor = parseFloat(row.factor) || 1;
                const rowViews = parseFloat(row.views) || 0;
                if (price === 0 && nr === 'REQ') missingL++;
                if (inv > 0 && !(soldQty > 0)) zeroSold++;
                if (inv > 0 && soldQty > 0) moreSold++;
                if (soldQty > 0 && price > 0) {
                    const lineSales = price * soldQty;
                    sales += lineSales;
                    recovery += lineSales * factor;
                    qty += soldQty;
                    pftDollars += ((price * factor) - lp) * soldQty;
                    cogs += lp * soldQty;
                }
                views += rowViews;
                if (price > 0) {
                    priceSum += price;
                    pricedCount++;
                }
                if (fbMpHasBlueTriangle(row)) blueTriangleCount++;
            });

            const gpft = sales > 0 ? (pftDollars / sales) * 100 : 0;
            const groi = cogs > 0 ? (pftDollars / cogs) * 100 : 0;
            const adsPct = 0;
            const npft = gpft - adsPct;
            const nroi = groi - adsPct;
            const avgPrice = pricedCount > 0 ? (priceSum / pricedCount) : 0;
            const cvr = views > 0 ? (qty / views) * 100 : 0;

            const setTxt = function(id, text) {
                const el = document.getElementById(id);
                if (el) el.textContent = text;
            };
            setTxt('rows-count-badge', 'Rows: ' + rows.length.toLocaleString());
            setTxt('zero-sold-count-badge', '0 Sold: ' + zeroSold.toLocaleString());
            setTxt('more-sold-count-badge', '> 0 Sold: ' + moreSold.toLocaleString());
            setTxt('total-sales-amt-badge', 'Sales: ' + fbMpMoney(sales));
            setTxt('total-recovery-badge', 'Recovery: ' + fbMpMoney(recovery));
            setTxt('total-spend-badge', 'Spend: $0');
            setTxt('qty-sold-badge', 'Qty: ' + Math.round(qty).toLocaleString());
            setTxt('avg-pft-badge', 'GPFT: ' + Math.round(gpft) + '%');
            setTxt('avg-roi-badge', 'GROI: ' + Math.round(groi) + '%');
            setTxt('ads-percent-badge', 'Ads: ' + adsPct.toFixed(1) + '%');
            setTxt('avg-npft-badge', 'NPFT: ' + Math.round(npft) + '%');
            setTxt('avg-nroi-badge', 'NROI: ' + Math.round(nroi) + '%');
            setTxt('avg-price-badge', 'Prc: $' + avgPrice.toFixed(2));
            setTxt('avg-cvr-badge', 'CVR: ' + (Math.round(cvr * 10) / 10) + '%');
            setTxt('missing-l-badge', 'Missing L: ' + missingL);

            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#fbmarketplace-price-gt-lmp-badge', table.getData(), 'fbmarketplace', 'price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#fbmarketplace-price-lt80-lmp-badge', table.getData(), 'fbmarketplace', 'price');
                }
            }
            $('#fbmarketplace-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncFbMpTriangleBadgeState === 'function') syncFbMpTriangleBadgeState();
            syncFbMpSoldBadgeState();
        }
        function updateSummary() {
            updateBadges();
        }

        $('#zero-sold-count-badge').on('click', function() {
            const soldEl = document.getElementById('sold-filter');
            if (!soldEl) return;
            soldEl.value = soldEl.value === 'zero' ? 'all' : 'zero';
            applyAllFilters();
        });
        $('#more-sold-count-badge').on('click', function() {
            const soldEl = document.getElementById('sold-filter');
            if (!soldEl) return;
            soldEl.value = soldEl.value === 'sold' ? 'all' : 'sold';
            applyAllFilters();
        });

        function saveMercariStatus(sku, payload) {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch("{{ route('fb.marketplace.tabulator.save-status') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(Object.assign({ sku: sku }, payload))
            }).catch(function(err) {
                console.error('Failed to save status', err);
            });
        }
    </script>
@endsection
