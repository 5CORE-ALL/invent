@extends('layouts.vertical', ['title' => 'Wayfair - Analytics', 'sidenav' => 'condensed'])

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
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }
        .tabulator-row.wf-parent-row,
        .tabulator-row.wf-parent-row .tabulator-cell {
            background-color: #d1e7dd !important;
            font-weight: 700 !important;
            min-height: 48px !important;
            color: #0f5132;
        }
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        /* SKU column: smooth width change when hover expands (+20% via JS) */
        #wayfair-pricing-table .tabulator-col[tabulator-field="sku"],
        #wayfair-pricing-table .tabulator-cell[tabulator-field="sku"] {
            transition: width 0.2s ease, min-width 0.2s ease;
        }
        #wf-image-hover-preview {
            pointer-events: auto;
            z-index: 10050;
        }
        /* NRP (REQ / NR) emoji select — matches eBay NR/REQ style */

        /* Column visibility dropdown — 4 columns (same pattern as amazon-tabulator-view) */
        #wf-column-dropdown-menu.column-dropdown-multicol {
            min-width: 560px;
            max-height: 420px;
            overflow-y: auto;
            padding: 6px 4px;
            column-count: 4;
            column-gap: 4px;
        }
        #wf-column-dropdown-menu.column-dropdown-multicol > li {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
        }
        #wf-column-dropdown-menu.column-dropdown-multicol .dropdown-item {
            padding: 3px 10px;
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            #wf-column-dropdown-menu.column-dropdown-multicol {
                min-width: 320px;
                column-count: 2;
            }
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'wayfair'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'wayfair'])
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Wayfair - Analytics',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        {{-- Summary badges before All Rows filter --}}
                        <span class="badge bg-dark fs-6 p-2" id="wf-rows-count-badge" style="color:white;font-weight:700;" title="Number of rows currently shown (after filters)">Row: 0</span>
                        <span class="badge bg-success fs-6 p-2 d-none" id="wf-total-profit-badge" style="font-weight:700;" aria-hidden="true">PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="wf-total-sales-badge" style="color:black;font-weight:700;">Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="wf-avg-gpft-badge" style="color:black;font-weight:700;" title="Order-style GPFT%: margin × L30 sales − LP×sold (margin from Marketplace % for Wayfair).">GPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="wf-avg-roi-badge" style="background-color:#6f42c1;color:white;font-weight:700;" title="GROI% = (Σ PFT ÷ Σ COGS) × 100.">GROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2 d-none" id="wf-avg-price-badge" style="color:black;font-weight:700;" aria-hidden="true" title="Weighted average price (Σ sales ÷ Σ units).">Price: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="wf-total-views-badge" style="color:black;font-weight:700;" title="Σ OV L30 (views) across filtered SKUs.">Views: 0</span>
                        <span class="badge fs-6 p-2" id="wf-total-fqty-badge" style="background-color:#20c997;color:black;font-weight:700;" title="Total units sold (Σ al30).">Qty: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="wf-avg-cvr-badge" style="color:black;font-weight:700;" title="CVR = (Σ sold ÷ Σ OV L30) × 100.">CVR: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="wf-nmap-count-badge" style="color:white;font-weight:700;cursor:pointer;" title="Click to filter N Map (listed, INV &gt; 0, price &gt; 0, |INV − Wayfair stock| &gt; 3)">N Map: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="wf-missing-badge" style="color:white;font-weight:700;cursor:pointer;" title="Click to filter ML — Missing Listing (not NR, INV &gt; 0, no uploaded Wayfair price)">ML: 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'wayfair-price-gt-lmp-badge', 'pglChannelKey' => 'wayfair', 'pglPriceField' => 'price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'wayfair-price-lt80-lmp-badge', 'pltChannelKey' => 'wayfair', 'pltPriceField' => 'price'])
                        <span class="badge fs-6 p-2" id="wayfair-blue-triangle-badge" style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;" title="Blue triangle: S PRC ≠ Price.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span class="badge bg-success fs-6 p-2" id="wf-more-sold-badge" style="color:black;font-weight:700;cursor:pointer;" title="Click to filter: sold &gt; 0">Sold &gt;0: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="wf-zero-sold-badge" style="color:white;font-weight:700;cursor:pointer;" title="Click to filter: 0 sold (al30)">0 Sold: 0</span>

                        <select id="wf-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>
                        <select id="wf-inv-filter" class="form-select form-select-sm" style="width:auto; display:inline-block;">
                            <option value="all">INV</option>
                            <option value="zero">Zero</option>
                            <option value="more" selected>More</option>
                        </select>
                        <select id="wf-gpft-filter" class="form-select form-select-sm" style="width:auto; display:inline-block;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40plus">Above 40%</option>
                        </select>
                        <select id="wf-cvr-filter" class="form-select form-select-sm" style="width:auto; display:inline-block;" title="CVR = sold (al30) ÷ OV L30">
                            <option value="all">CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                        <select id="wf-roi-filter" class="form-select form-select-sm" style="width:auto; display:inline-block;">
                            <option value="all">GROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-60">40–60%</option>
                            <option value="60-80">60–80%</option>
                            <option value="80-100">80–100%</option>
                            <option value="gt100">100%+</option>
                        </select>
                        <select id="wf-dil-filter" class="form-select form-select-sm" style="width:auto; display:inline-block;">
                            <option value="all">DIL%</option>
                            <option value="red">Red &lt;25%</option>
                            <option value="green">Green 25-50%</option>
                            <option value="pink">Pink 50%+</option>
                        </select>
                        <input type="text" id="wf-pricing-parent-search" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search parent..." title="Filter by Parent column">
                        <input type="text" id="wf-pricing-sku-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search SKU...">
                        <button type="button" id="wf-export-pricing" class="btn btn-sm btn-success" title="Export CSV" aria-label="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="wfColumnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <ul class="dropdown-menu column-dropdown-multicol" aria-labelledby="wfColumnVisibilityDropdown" id="wf-column-dropdown-menu">
                            </ul>
                        </div>
                        <button id="wf-price-mode-btn" type="button" class="btn btn-sm btn-secondary" title="Cycle: Off → Decrease → Increase → Same SPRICE (enter one price, applies to all selected rows)">
                            <i class="fas fa-exchange-alt"></i> Prc M
                        </button>
                        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'buttons', 'ebaySprcDilChannel' => 'wayfair'])
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'wayfair'])

                        {{-- Target ROI% bulk control — Amazon-style compact UI; Wayfair back-solve omits ship.
                             Formula: sprice = LP × (1 + ROI%/100) / margin   (margin = per-row `_margin`, default 0.95) --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="wf-target-roi-controls"
                            title="Target ROI% — sets S PRC so the SROI column equals the target">
                            <label for="wf-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                            </label>
                            <input type="number" id="wf-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 52px;"
                                title="Target ROI% applied to all selected rows — matches the SROI column">
                            <button id="wf-apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                                title="Compute & save S PRC so SROI = Target ROI% for every selected row">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        {{-- Target GPFT% bulk control — Amazon-style compact UI.
                             Formula: sprice = LP / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="wf-target-gpft-controls"
                            title="Target GPFT% — sets S PRC so the SGPFT column equals the target">
                            <label for="wf-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                            </label>
                            <input type="number" id="wf-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 52px;"
                                title="Target GPFT% applied to all selected rows — matches the SGPFT column. Must be less than take-home margin (typically &lt; 95%).">
                            <button id="wf-apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                                title="Compute & save S PRC so SGPFT = Target GPFT% for every selected row">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        <!-- Play / Pause parent navigation -->
                        <div class="btn-group align-items-center ms-2" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-sm btn-warning rounded-circle shadow-sm" style="display: none;" title="Stop navigation and show all">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>

                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadWayfairPriceModal" title="Upload Wayfair base cost sheet">
                            <i class="fas fa-upload"></i> Upload price
                        </button>
                    </div>

                    <div id="wf-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span id="wf-selected-skus-count" class="fw-bold text-secondary"></span>
                            <span id="wf-discount-type-wrap">
                            <select id="wf-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            </span>
                            <input type="number" id="wf-discount-input" class="form-control form-control-sm" placeholder="Enter %" step="0.01" style="width:110px;">
                            <button id="wf-apply-discount-btn" type="button" class="btn btn-primary btn-sm">Apply</button>
                            <button id="wf-clear-sprice-btn" type="button" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    <div id="wayfair-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadWayfairPriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Wayfair base cost sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        <a href="{{ route('wayfair.pricing.price.sample') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download"></i> Download sample CSV
                        </a>
                    </p>
                    <input type="file" class="form-control" id="wfPriceSheetFile" accept=".xlsx,.xls,.csv,.txt">
                    <small class="text-muted d-block mt-2">Minimum columns: <strong>Supplier Part Number</strong> (stored as <code>sku</code>) or column named <strong>sku</strong>, <strong>price</strong>, optional <strong>wayfair stock</strong> / <strong>wayfair_stock</strong>. Full Wayfair export (New Base Cost, etc.) also supported. TSV/CSV/Excel.</small>
                    <div class="mt-2 small text-muted">
                        Alternate static examples:
                        <a href="{{ asset('sample_excel/wayfair_pricing_sample.csv') }}" class="text-decoration-none" download>supplier part</a>
                        ·
                        <a href="{{ asset('sample_excel/wayfair_pricing_sample_sku_column.csv') }}" class="text-decoration-none" download>sku header</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="wfUploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="wfEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="wfEditLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="wfEditLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="wfEditBuyerLink" class="form-label">B Link (Buyer · Wayfair)</label>
                        <input type="url" class="form-control" id="wfEditBuyerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="wfEditSellerLink" class="form-label">S Link (Seller · Partners)</label>
                        <input type="url" class="form-control" id="wfEditSellerLink" placeholder="https://...">
                    </div>
                    <div id="wfEditLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="wfSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'wayfair'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'modals', 'ebaySprcDilChannel' => 'wayfair'])
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let table = null;
        let allTableData = [];
        // summaryDataCache removed — badges always read from getData('active') / getRows('active').
        let wfMissingActive = false;
        let wfMapActive = false;
        let wfNMapActive = false;
        // Sold filter via badges: 'all' | '0' | 'more' (al30)
        let wfSoldFilter = 'all';
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;
        let blueTriangleFilterActive = false;

        let wfDecreaseModeActive = false;
        let wfIncreaseModeActive = false;
        let wfUniformPriceModeActive = false;
        let wfSelectedSkus = new Set();

        /** Std Prc vs channel price: reduce / hold / increase → red / yellow / green. */
        function wfStdPrcChangeDotMeta(stdPrc, comparePrice) {
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

        function wfStdPrcChangeDotHtml(stdPrc, comparePrice) {
            const meta = wfStdPrcChangeDotMeta(stdPrc, comparePrice);
            if (!meta) return '';
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
        }

        function applyWfStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            applyWfStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
        });

        function isWayfairParentRow(row) {
            if (!row) return false;
            if (row.is_parent === true) return true;
            const sku = String(row.sku || row['(Child) sku'] || row.SKU || '').toUpperCase();
            return sku.includes('PARENT');
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'wayfair'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'script', 'ebaySprcDilChannel' => 'wayfair'])
        function wayfairRowSpriceForAlert(data) {
            let sprice = parseFloat(data && (data.sprice != null ? data.sprice : data.SPRICE)) || 0;
            if (typeof chPromoLiveSprice === 'function' && !isWayfairParentRow(data)) {
                const calc = chPromoLiveSprice(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function wayfairHasBlueTriangle(data) {
            if (isWayfairParentRow(data)) return false;
            const sprice = wayfairRowSpriceForAlert(data);
            const price = parseFloat(data && data.price) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function syncWayfairTriangleBadgeState() {
            $('#wayfair-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

        let wfSkuColHoverBase = null;
        let wfSkuColHoverActive = false;

        function wfResetSkuColHoverWidth() {
            if (table && wfSkuColHoverActive && wfSkuColHoverBase != null) {
                try {
                    const col = table.getColumn('sku');
                    if (col) col.setWidth(wfSkuColHoverBase);
                } catch (err) { /* ignore */ }
            }
            wfSkuColHoverActive = false;
            wfSkuColHoverBase = null;
        }

        let wfImagePreviewHideTimer = null;
        let wfImagePreviewEl = null;

        function wfRemoveImagePreview() {
            if (wfImagePreviewHideTimer) {
                clearTimeout(wfImagePreviewHideTimer);
                wfImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#wf-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            wfImagePreviewEl = null;
        }

        function wfCancelImagePreviewHide() {
            if (wfImagePreviewHideTimer) {
                clearTimeout(wfImagePreviewHideTimer);
                wfImagePreviewHideTimer = null;
            }
        }

        function wfScheduleImagePreviewHide() {
            wfCancelImagePreviewHide();
            wfImagePreviewHideTimer = setTimeout(wfRemoveImagePreview, 220);
        }

        function wfEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.wfPreviewListeners === '1') return;
            wrap.dataset.wfPreviewListeners = '1';
            wrap.addEventListener('mouseenter', wfCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', wfScheduleImagePreviewHide);
        }

        function wfClampImagePreviewPosition(wrap, clientX, clientY) {
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

        function wfShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            wfCancelImagePreviewHide();
            const existing = wfImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    wfClampImagePreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#wf-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            wfImagePreviewEl = null;

            const wrap = document.createElement('div');
            wrap.id = 'wf-image-hover-preview';
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
            wfEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            wfImagePreviewEl = wrap;
            wfClampImagePreviewPosition(wrap, clientX, clientY);
        }

        function money(value) {
            return '$' + (parseFloat(value) || 0).toFixed(2);
        }

        function wfEscUrlAttr(url) {
            if (url == null || url === '') return '';
            return String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function wfEscHtmlAttr(val) {
            if (val == null || val === '') return '';
            return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        /** NRP save: same client pattern as eBay tabulator `/update-ebay-nr-data` (JSON + X-CSRF-TOKEN + { sku, field, value }). */
        function wfSaveNrp(data, onSuccess, onFail) {
            onSuccess = typeof onSuccess === 'function' ? onSuccess : function() {};
            onFail = typeof onFail === 'function' ? onFail : function() {};
            $.ajax({
                url: '{{ route("wayfair.save.nr") }}',
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
                        if (window.toastr) toastr.success(res.message || 'NRP updated');
                        onSuccess();
                    } else {
                        console.warn('NRP not saved:', (res && (res.error || res.message)) || 'unknown');
                        onFail();
                    }
                },
                error: function(xhr) {
                    console.error('NRP save failed:', xhr);
                    const msg = (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) ?
                        (xhr.responseJSON.error || xhr.responseJSON.message) : 'Error saving NRP.';
                    if (window.toastr) toastr.error(msg);
                    else alert(msg);
                    onFail();
                }
            });
        }

        function saveWayfairSpriceUpdates(updates, opts) {
            opts = opts || {};
            if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst !== false) {
                chPromoBatchClearThenSave(updates, function(next) {
                    saveWayfairSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
                }, {
                    wipeFn: function(zeros) {
                        return $.ajax({
                            url: '{{ route("wayfair.pricing.save.sprice") }}',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            data: { _token: '{{ csrf_token() }}', updates: zeros }
                        });
                    }
                });
                return;
            }
            $.ajax({
                url: '{{ route("wayfair.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { _token: '{{ csrf_token() }}', updates: updates },
                success: function(res) {
                    if (res.success) console.log('Wayfair SPRICE saved:', res.updated);
                },
                error: function(xhr) {
                    console.error('Wayfair SPRICE save error:', xhr.responseJSON);
                }
            });
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(row => {
                    if (row && typeof row.getData === 'function') return row.getData();
                    return row || {};
                });
            }
            if (rowsInput && typeof rowsInput === 'object') {
                return Object.values(rowsInput).map(row => {
                    if (row && typeof row.getData === 'function') return row.getData();
                    return row || {};
                });
            }
            return [];
        }

        function wayfairMarginFromRow(row) {
            const m = parseFloat(row._margin);
            return Number.isFinite(m) && m > 0 ? m : 0.95;
        }

        function wfRoundToRetailPrice(price) {
            return Math.ceil(price) - 0.01;
        }

        /**
         * All SKU rows matching current filters, across every pagination page.
         * Tabulator: getRows("active") = filtered dataset (not just the visible page).
         */
        function wfAllFilteredSkuRows() {
            if (!table) return [];
            try {
                const rows = typeof table.getRows === 'function' ? table.getRows('active') : [];
                if (!Array.isArray(rows)) return [];
                return rows.filter(function(row) {
                    try {
                        return row && typeof row.getData === 'function' && !row.getData().is_parent;
                    } catch (e) {
                        return false;
                    }
                });
            } catch (err) {
                return [];
            }
        }

        /** First Row component for a SKU in the active (filtered) dataset, any page. */
        function wfFindActiveSkuRow(sku) {
            if (!table || sku == null || sku === '') return null;
            const want = String(sku);
            const rows = wfAllFilteredSkuRows();
            for (let i = 0; i < rows.length; i++) {
                const d = rows[i].getData();
                if (d && String(d.sku) === want) return rows[i];
            }
            return null;
        }

        function wfSyncSelectAllHeaderCheckbox() {
            const el = document.getElementById('wf-select-all');
            if (!el || !table) return;
            const skuRows = wfAllFilteredSkuRows();
            if (!skuRows.length) {
                el.checked = false;
                el.indeterminate = false;
                return;
            }
            let selected = 0;
            skuRows.forEach(function(row) {
                const d = row.getData();
                const sku = d && d.sku != null ? String(d.sku) : '';
                if (sku && wfSelectedSkus.has(sku)) selected++;
            });
            el.checked = selected === skuRows.length && skuRows.length > 0;
            el.indeterminate = selected > 0 && selected < skuRows.length;
        }

        function wfSyncPriceModeUi() {
            const $btn = $('#wf-price-mode-btn');
            const selectCol = table ? table.getColumn('_wf_select') : null;
            $('#wf-discount-type-wrap').toggle(!wfUniformPriceModeActive);
            if (wfUniformPriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-primary').addClass('btn-warning')
                    .html('<i class="fas fa-equals"></i> Same SPRICE');
                if (selectCol) selectCol.show();
                $('#wf-discount-input').attr('placeholder', 'Enter price (e.g. 19.99)');
                wfUpdateSelectedCount();
                return;
            }
            $('#wf-discount-input').attr('placeholder', $('#wf-discount-type').val() === 'percentage' ? 'Enter %' : 'Enter $');
            if (wfDecreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary btn-warning').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                if (selectCol) selectCol.show();
                wfUpdateSelectedCount();
                return;
            }
            if (wfIncreaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-warning').addClass('btn-primary')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                if (selectCol) selectCol.show();
                wfUpdateSelectedCount();
                return;
            }
            $btn.removeClass('btn-danger btn-primary btn-warning').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Prc M');
            if (selectCol) selectCol.hide();
            wfSelectedSkus.clear();
            wfUpdateSelectedCount();
        }

        function wfUpdateSelectedCount() {
            if (wfUniformPriceModeActive) {
                const cnt = wfSelectedSkus.size;
                if (cnt > 0) {
                    $('#wf-selected-skus-count').text(
                        'Same SPRICE: ' + cnt + ' SKU' + (cnt !== 1 ? 's' : '') + ' selected — enter a price and click Apply to set the SAME SPRICE on these rows.'
                    );
                } else {
                    $('#wf-selected-skus-count').text('Same SPRICE: check the rows you want to update, enter a price, then click Apply.');
                }
            } else {
                const cnt = wfSelectedSkus.size;
                $('#wf-selected-skus-count').text(cnt + ' SKU' + (cnt !== 1 ? 's' : '') + ' selected');
            }
            const showPanel = wfUniformPriceModeActive
                || (wfSelectedSkus.size > 0 && (wfDecreaseModeActive || wfIncreaseModeActive));
            $('#wf-discount-container').toggle(showPanel);
        }

        /** Clear pricing-mode SKU checkboxes when table filters change (visible row set changes). */
        function wfClearSkuSelections() {
            wfSelectedSkus.clear();
            $('#wf-select-all').prop('checked', false).prop('indeterminate', false);
            wfUpdateSelectedCount();
            if (table) {
                try { table.redraw(true); } catch (err) { /* ignore */ }
            }
        }

        function wfApplyDiscount() {
            const discountType = $('#wf-discount-type').val();
            const discountVal = parseFloat($('#wf-discount-input').val());

            if (wfUniformPriceModeActive) {
                if (!table) return;
                if (isNaN(discountVal) || discountVal <= 0) {
                    if (window.toastr) toastr.warning('Enter a price first (e.g. 19.99)');
                    return;
                }
                if (wfSelectedSkus.size === 0) {
                    if (window.toastr) toastr.warning('Check the rows you want to apply this price to first.');
                    return;
                }
                const newSprice = wfRoundToRetailPrice(Math.max(0.99, discountVal));
                const updates = [];
                table.getRows('active').forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent) return;
                    if (!wfSelectedSkus.has(String(d.sku))) return;
                    const margin = wayfairMarginFromRow(d);
                    const lp = parseFloat(d.lp) || 0;
                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                    const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;
                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                    updates.push({ sku: d.sku, sprice: newSprice });
                });
                if (updates.length) {
                    saveWayfairSpriceUpdates(updates);
                    if (window.toastr) toastr.success('SPRICE set to $' + newSprice.toFixed(2) + ' for ' + updates.length + ' SKU(s)');
                } else if (window.toastr) {
                    toastr.warning('No matching SKU rows in the current view.');
                }
                $('#wf-discount-input').val('');
                return;
            }

            if (isNaN(discountVal) || discountVal === 0 || wfSelectedSkus.size === 0) return;

            const updates = [];
            wfSelectedSkus.forEach(function(sku) {
                const row = wfFindActiveSkuRow(sku);
                if (!row) return;
                const rowData = row.getData();
                const currentPrice = parseFloat(rowData.price) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = wfIncreaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = wfIncreaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = wfRoundToRetailPrice(Math.max(0.99, newSprice));

                const margin = wayfairMarginFromRow(rowData);
                const lp = parseFloat(rowData.lp) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;

                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                updates.push({ sku: sku, sprice: newSprice });
            });

            if (updates.length) saveWayfairSpriceUpdates(updates);
            $('#wf-discount-input').val('');
        }

        /** Wayfair map "N Map|{abs diff}" — N Map count/filter only when |diff| &gt; 3 (≤3 is Map). */
        function wfWfStrictNMapFromMap(mapVal) {
            if (!mapVal || typeof mapVal !== 'string' || !mapVal.startsWith('N Map|')) return false;
            const part = mapVal.split('|')[1];
            const d = parseFloat(String(part == null ? '' : part).trim(), 10);
            return Number.isFinite(d) && Math.abs(d) > 3;
        }

        /** Missing L — not NR, INV &gt; 0, no uploaded Wayfair price (Macys / channel-master pattern). */
        function wfRowIsMissing(d) {
            if (d.is_parent) return false;
            if (String(d.nr || '').trim().toUpperCase() === 'NR') return false;
            const inv = parseInt(d.inv, 10) || 0;
            const price = parseFloat(d.price) || 0;
            return inv > 0 && price <= 0;
        }

        /** Map status from raw inv vs ae_stock — listed rows with INV &gt; 0 and price &gt; 0 only. */
        function wfRowMapStatus(d) {
            if (d.is_parent || wfRowIsMissing(d)) return null;
            const inv = parseInt(d.inv, 10) || 0;
            const price = parseFloat(d.price) || 0;
            if (inv <= 0 || price <= 0) return null;
            const wfStock = parseInt(d.ae_stock, 10) || 0;
            const diff = Math.abs(inv - wfStock);
            return diff <= 3 ? 'map' : 'nmap';
        }

        function wfClearSpriceForSelected() {
            if (wfUniformPriceModeActive) {
                const limitToSelection = wfSelectedSkus.size > 0;
                const msg = limitToSelection
                    ? ('Clear SPRICE for ' + wfSelectedSkus.size + ' selected SKU(s)?')
                    : 'Clear SPRICE for ALL SKU rows?';
                if (!confirm(msg)) return;
                const updates = [];
                table.getRows('active').forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent) return;
                    if (limitToSelection && !wfSelectedSkus.has(String(d.sku))) return;
                    row.update({ sprice: 0, sgpft: 0, sroi: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                });
                if (updates.length) saveWayfairSpriceUpdates(updates);
                return;
            }
            if (!wfSelectedSkus.size) return;
            if (!confirm('Clear SPRICE for ' + wfSelectedSkus.size + ' SKU(s)?')) return;
            const updates = [];
            table.getRows('active').forEach(function(row) {
                const d = row.getData();
                if (wfSelectedSkus.has(String(d.sku)) && !d.is_parent) {
                    row.update({ sprice: 0, sgpft: 0, sroi: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                }
            });
            if (updates.length) saveWayfairSpriceUpdates(updates);
        }

        function updateSummary() {
            if (!table) return;
            let rows;
            try {
                rows = table.getData('active');
            } catch (e) {
                return;
            }
            if (!rows) return;

            let totalSales = 0, totalFqty = 0, totalProfit = 0, totalCogs = 0, totalViews = 0;
            let missingCount = 0, mapCount = 0, nmapCount = 0;
            let zeroSold = 0, moreSold = 0;
            let visibleRowCount = 0;

            rows.forEach(row => {
                visibleRowCount++;
                if (row.is_parent) return;
                const isMissing = wfRowIsMissing(row);
                const fqty = parseFloat(row.al30) || 0;
                const sales = parseFloat(row.sales) || 0;
                const lp = parseFloat(row.lp) || 0;
                const views = parseFloat(row.ov_l30) || 0;
                const listProfitPerUnit = parseFloat(row.profit) || 0;

                totalSales += sales;
                totalFqty += fqty;
                totalViews += views;
                totalCogs += lp * fqty;

                const keep = wayfairMarginFromRow(row);
                let rowOrderPft = 0;
                if (sales > 0 && fqty > 0) {
                    rowOrderPft = keep * sales - lp * fqty;
                } else if (fqty > 0 && !isMissing) {
                    rowOrderPft = fqty * listProfitPerUnit;
                }
                totalProfit += rowOrderPft;

                if (fqty === 0) zeroSold++; else moreSold++;
                if (isMissing) {
                    missingCount++;
                } else {
                    const mapStatus = wfRowMapStatus(row);
                    if (mapStatus === 'map') mapCount++;
                    else if (mapStatus === 'nmap') nmapCount++;
                }
            });

            const pftPct = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
            const roiPct = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;
            const avgPrice = totalFqty > 0 ? (totalSales / totalFqty) : 0;
            const avgCvr = totalViews > 0 ? (totalFqty / totalViews) * 100 : 0;

            // Same labels/order as /amazon-tabulator-view (Wayfair Ads%=0 → PFT=GPFT, NROI=GROI).
            $('#wf-rows-count-badge').text('Row: ' + visibleRowCount.toLocaleString());
            $('#wf-total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#wf-total-fqty-badge').text('Qty: ' + totalFqty.toLocaleString());
            $('#wf-total-profit-badge').text('PFT: $' + Math.round(totalProfit).toLocaleString());
            $('#wf-avg-gpft-badge').text('GPFT: ' + Math.round(pftPct) + '%');
            $('#wf-avg-roi-badge').text('GROI: ' + Math.round(roiPct) + '%');
            $('#wf-avg-price-badge').text('Price: $' + avgPrice.toFixed(2));
            $('#wf-total-views-badge').text('Views: ' + Math.round(totalViews).toLocaleString());
            $('#wf-avg-cvr-badge').text('CVR: ' + avgCvr.toFixed(1) + '%');
            $('#wf-missing-badge').text('ML: ' + missingCount.toLocaleString());
            $('#wf-nmap-count-badge').text('N Map: ' + nmapCount.toLocaleString());
            $('#wf-zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
            $('#wf-more-sold-badge').text('Sold >0: ' + moreSold.toLocaleString());
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#wayfair-price-gt-lmp-badge', table.getData(), 'wayfair', 'price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#wayfair-price-lt80-lmp-badge', table.getData(), 'wayfair', 'price');
                }
            }
            let blueTriangleCount = 0;
            (table ? table.getData() : []).forEach(function(row) {
                if (wayfairHasBlueTriangle(row)) blueTriangleCount++;
            });
            $('#wayfair-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncWayfairTriangleBadgeState === 'function') syncWayfairTriangleBadgeState();

            // Active filter colors — same pattern as Amazon N Map / ML badges.
            $('#wf-nmap-count-badge').toggleClass('bg-secondary', !wfNMapActive).toggleClass('bg-danger', wfNMapActive);
            $('#wf-missing-badge').toggleClass('bg-secondary', !wfMissingActive).toggleClass('bg-danger', wfMissingActive);
        }

        // Play / Pause parent navigation state
        let wfUniqueParents = [];
        let isWfPlayActive = false;
        let currentWfParentIndex = -1;

        function normalizeWfParentKey(val) {
            if (val == null || val === '') return '';
            return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
        }
        function buildWfUniqueParents() {
            if (!table) return [];
            const allRows = table.getData('all') || [];
            const seen = {};
            const list = [];
            allRows.forEach(function(r) {
                const p = normalizeWfParentKey(r.parent);
                if (p && !seen[p]) { seen[p] = true; list.push(p); }
            });
            list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
            return list;
        }
        function updateWfPlayButtonStates() {
            $('#play-backward').prop('disabled', !isWfPlayActive || currentWfParentIndex <= 0);
            $('#play-forward').prop('disabled', !isWfPlayActive || currentWfParentIndex >= wfUniqueParents.length - 1);
        }
        function startWfPlay() {
            wfUniqueParents = buildWfUniqueParents();
            if (wfUniqueParents.length === 0) return;
            isWfPlayActive = true;
            currentWfParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateWfPlayButtonStates();
        }
        function stopWfPlay() {
            isWfPlayActive = false;
            currentWfParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyFilters();
            updateWfPlayButtonStates();
        }
        function nextWfParent() {
            if (!isWfPlayActive || currentWfParentIndex >= wfUniqueParents.length - 1) return;
            currentWfParentIndex++;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateWfPlayButtonStates();
        }
        function previousWfParent() {
            if (!isWfPlayActive || currentWfParentIndex <= 0) return;
            currentWfParentIndex--;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateWfPlayButtonStates();
        }
        $('#play-auto').on('click', startWfPlay);
        $('#play-pause').on('click', stopWfPlay);
        $('#play-forward').on('click', nextWfParent);
        $('#play-backward').on('click', previousWfParent);

        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            if (!table) return;
            table.clearFilter();

            // Play navigation: only show current parent's group
            if (isWfPlayActive && wfUniqueParents.length > 0 && currentWfParentIndex >= 0) {
                const currentKey = wfUniqueParents[currentWfParentIndex];
                if (currentKey) {
                    table.addFilter(function(d) {
                        const p = normalizeWfParentKey(d.parent);
                        return p === currentKey || p === ('PARENT ' + currentKey);
                    });
                }
                if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                    table.addFilter(function(data) {
                        return PriceGtLmpBadge.hasRedTriangle(data, 'price');
                    });
                }
                if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                    table.addFilter(function(data) {
                        return PriceLt80LmpBadge.hasPurpleTriangle(data, 'price');
                    });
                }
                if (blueTriangleFilterActive) {
                    table.addFilter(function(data) {
                        return wayfairHasBlueTriangle(data);
                    });
                }
                return;
            }

            const skuSearch = ($('#wf-pricing-sku-search').val() || '').toLowerCase().trim();
            const parentSearch = ($('#wf-pricing-parent-search').val() || '').toLowerCase().trim();
            const rowType = $('#wf-row-type-filter').val();
            const invFilter = $('#wf-inv-filter').val();
            const gpftFilter = $('#wf-gpft-filter').val();
            const cvrFilter = $('#wf-cvr-filter').val();
            const roiFilter = $('#wf-roi-filter').val();
            const dilFilter = $('#wf-dil-filter').val();

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }
            if (parentSearch) {
                table.addFilter(function(d) {
                    const p = (d.parent || '').toLowerCase();
                    const sku = (d.sku || '').toLowerCase();
                    if (d.is_parent === true) {
                        return p.includes(parentSearch) || sku.includes(parentSearch);
                    }
                    return p.includes(parentSearch) || sku.includes(parentSearch);
                });
            }
            if (rowType === 'parents') {
                table.addFilter(d => d.is_parent === true);
            } else if (rowType === 'skus') {
                table.addFilter(d => !d.is_parent);
            }
            if (invFilter === 'zero') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) === 0);
            } else if (invFilter === 'more') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) > 0);
            }
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40plus') return gpft >= 40;
                    return true;
                });
            }
            if (cvrFilter !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.ov_l30) || 0;
                    const sold = parseFloat(d.al30) || 0;
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
            if (roiFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const roi = parseFloat(d.groi) || 0;
                    if (roiFilter === 'lt40') return roi < 40;
                    if (roiFilter === 'gt100') return roi > 100;
                    const parts = roiFilter.split('-').map(Number);
                    return roi >= parts[0] && roi <= parts[1];
                });
            }
            if (wfSoldFilter === '0') {
                table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            } else if (wfSoldFilter === 'more') {
                table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
            }
            if (dilFilter !== 'all') {
                table.addFilter(function(d) {
                    const inv = parseFloat(d.inv) || 0;
                    const ovL30 = parseFloat(d.ov_l30) || 0;
                    const dil = inv === 0 ? 0 : (ovL30 / inv) * 100;
                    if (dilFilter === 'red') return dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }
            if (wfMissingActive) {
                table.addFilter(function(d) { return wfRowIsMissing(d); });
            }
            if (wfMapActive) table.addFilter(d => wfRowMapStatus(d) === 'map');
            if (wfNMapActive) table.addFilter(d => wfRowMapStatus(d) === 'nmap');
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'price');
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return wayfairHasBlueTriangle(data);
                });
            }

            updateSummary();
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#wayfair-price-gt-lmp-badge',
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
                badge: '#wayfair-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyFilters();
                }
            });
        }
        $('#wayfair-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
            }
            applyFilters();
        });

        function wfBuildColumnDropdown() {
            if (!table) return;
            if (window.AnalyticsColVis) {
                window.AnalyticsColVis.install({
                    getTable: function() { return table; },
                    menuId: 'wf-column-dropdown-menu',
                    storageKey: 'wayfair_col_cats_v1',
                    skipFields: ['_wf_select', '_select'],
                    onSave: function() {
                        if (typeof wfSaveColumnVisibilityToServer === 'function') wfSaveColumnVisibilityToServer();
                        else if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
                    }
                });
                window.AnalyticsColVis.rebuild(null, 'wf-column-dropdown-menu');
                return;
            }
            const menu = document.getElementById('wf-column-dropdown-menu');
            if (!menu) return;
            let html = '';
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                const def = col.getDefinition();
                const titleRaw = def.title;
                const titleStr = titleRaw != null ? String(titleRaw) : '';
                const label = titleStr.replace(/<[^>]*>/g, '').trim() || field;
                if (field && field !== '_wf_select' && label) {
                    const isVisible = col.isVisible();
                    const fEsc = String(field).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const lEsc = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    html += '<li class="dropdown-item px-2 py-1">' +
                        '<label class="d-flex align-items-center gap-2 mb-0 w-100" style="cursor:pointer;">' +
                        '<input type="checkbox" class="wf-column-toggle" data-field="' + fEsc + '" ' + (isVisible ? 'checked' : '') + '>' +
                        '<span>' + lEsc + '</span>' +
                        '</label></li>';
                }
            });
            menu.innerHTML = html;
        }

        function wfSaveColumnVisibilityToServer() {
            if (!table) return;
            const visibility = {};
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                if (field && field !== '_wf_select') {
                    visibility[field] = col.isVisible();
                }
            });
            $.ajax({
                url: '{{ route("wayfair.pricing.column.set") }}',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function wfApplyColumnVisibilityFromServer() {
            if (!table) return;
            $.ajax({
                url: '{{ route("wayfair.pricing.column.get") }}',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && typeof visibility === 'object' && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(function(field) {
                            const col = table.getColumn(field);
                            if (col) {
                                if (visibility[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                        wfBuildColumnDropdown();
                    }
                }
            });
        }

        $(document).ready(function() {
            table = new Tabulator('#wayfair-pricing-table', {
                ajaxURL: '{{ route("wayfair.pricing.data") }}',
                ajaxResponse: function(url, params, response) {
                    setTimeout(() => applyFilters(), 100);
                    return response;
                },
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [50, 100, 150, 200],
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('wf-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Image', field: 'image', width: 60, headerSort: false, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent || !src) return '';
                            const esc = String(src).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return '<img src="' + esc + '" data-full="' + esc + '" class="wf-hover-thumb" alt="" ' +
                                'style="width:44px;height:44px;object-fit:cover;border-radius:4px;cursor:pointer;" ' +
                                'onerror="this.onerror=null;this.style.display=\'none\'">';
                        },
                        cellMouseOver: function(e, cell) {
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.wf-hover-thumb');
                            if (!img) return;
                            const fullUrl = img.getAttribute('data-full');
                            wfShowImagePreview(e.clientX, e.clientY, fullUrl);
                        },
                        cellMouseMove: function(e, cell) {
                            const preview = wfImagePreviewEl;
                            if (!preview || !document.body.contains(preview)) return;
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.wf-hover-thumb');
                            const fullUrl = img ? img.getAttribute('data-full') : '';
                            const big = preview.querySelector('img');
                            if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                            wfClampImagePreviewPosition(preview, e.clientX, e.clientY);
                        },
                        cellMouseOut: function(e, cell) {
                            const related = e.relatedTarget;
                            if (related && typeof related.closest === 'function' && related.closest('#wf-image-hover-preview')) {
                                wfCancelImagePreviewHide();
                                return;
                            }
                            wfScheduleImagePreviewHide();
                        }
                    },
                    {
                        title: "<input type=\"checkbox\" id=\"wf-select-all\" title=\"Select / clear all filtered SKUs (all pages)\">",
                        field: '_wf_select',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 38,
                        download: false,
                        visible: false,
                        frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sku = String(d.sku || '');
                            const esc = sku.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            const chk = wfSelectedSkus.has(d.sku) ? 'checked' : '';
                            return '<input type="checkbox" class="wf-sku-chk" data-sku="' + esc + '" ' + chk + '>';
                        }
                    },
                    {
                        title: 'Parent', field: 'parent', width: 120, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + v + '</span>';
                        }
                    },
                    ParentExpand.columnDef(),
                    {
                        title: 'SKU', field: 'sku', minWidth: 200, frozen: true, headerFilter: 'input',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return '<span style="color:#0f5132;font-size:13px;font-weight:700;">' + String(val).replace(/</g, '&lt;') + '</span>';
                            }
                            const raw = String(val);
                            const esc = raw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            return '<span class="d-inline-flex align-items-center gap-1">' +
                                '<span class="fw-bold">' + esc + '</span>' +
                                '<button type="button" class="btn btn-sm btn-link p-0 wf-copy-sku-btn" data-sku="' + esc + '" title="Copy SKU" ' +
                                'style="min-width:auto;line-height:1;color:#6c757d;vertical-align:middle;"><i class="fas fa-copy" style="font-size:12px;"></i></button>' +
                                '</span>';
                        }
                    },
                    {
                        title: 'B/S',
                        field: 'buyer_link',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 64,
                        download: false,
                        frozen: true,
                        tooltip: 'Double-click to add / edit links',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const b = d.buyer_link;
                            const s = d.seller_link;
                            const parts = [];
                            if (b) {
                                parts.push('<a href="' + wfEscUrlAttr(b) + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="fw-semibold" style="color:#0d6efd;" title="Buyer (Wayfair)">B</a>');
                            }
                            if (s) {
                                parts.push('<a href="' + wfEscUrlAttr(s) + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="fw-semibold" style="color:#6f42c1;" title="Seller (Partners)">S</a>');
                            }
                            return parts.length ? parts.join('<span class="text-muted" style="margin:0 3px;">|</span>') : '<span class="text-muted" style="font-size:12px;">-</span>';
                        },
                        cellDblClick: function(e, cell) {
                            e.stopPropagation();
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            openWfEditLinksModal(cell.getRow());
                        }
                    },
                    {
                        title: 'INV', field: 'inv', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="font-weight:700;">' + cell.getValue() + '</span>';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        }
                    },
                    {
                        title: 'Wayfair stock', field: 'ae_stock', sorter: 'number', hozAlign: 'center', width: 82,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="font-weight:700;">' + cell.getValue() + '</span>';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        }
                    },
                    {
                        title: 'OV L30', field: 'ov_l30', sorter: 'number', hozAlign: 'center', width: 60,
                        formatter: function(cell) {
                            return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'Dil', field: 'dil_percent', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ovL30 = parseFloat(row.ov_l30) || 0;
                            if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ovL30 / inv) * 100;
                            let color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        }
                    },
                    {
                        title: 'A L30', field: 'al30', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue(), 10) || 0;
                            return '<span style="font-weight:700;">' + v + '</span>';
                        }
                    },
                    {
                        // Same color bands as /amazon-tabulator-view CVR L30
                        title: 'CVR', field: 'cvr', hozAlign: 'center', width: 55,
                        headerTooltip: 'CVR = A L30 ÷ OV L30',
                        sorter: function(a, b, aRow, bRow) {
                            const calc = function(d) {
                                const sold = parseFloat(d.al30) || 0;
                                const ov = parseFloat(d.ov_l30) || 0;
                                return ov === 0 ? 0 : (sold / ov) * 100;
                            };
                            return calc(aRow.getData()) - calc(bRow.getData());
                        },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const sold = parseFloat(d.al30) || 0;
                            const ov = parseFloat(d.ov_l30) || 0;
                            if (ov === 0) {
                                return '<span style="color:#a00211;font-weight:600;">0%</span>';
                            }
                            const cvr = (sold / ov) * 100;
                            let color = '#a00211';
                            if (cvr > 4 && cvr <= 7) color = '#ffc107';
                            else if (cvr > 7 && cvr <= 13) color = '#28a745';
                            else if (cvr > 13) color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(cvr) + '%</span>';
                        }
                    },
                    {
                        title: 'Std Prc',
                        field: 'STANDARD_PRICE',
                        hozAlign: 'center',
                        headerTooltip: 'Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings. Dot vs channel price.',
                        editor: 'input',
                        width: 70,
                        sorter: 'number',
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return false;
                            const sku = String(d.sku || d['(Child) sku'] || d.SKU || '');
                            return !!sku;
                        },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const value = cell.getValue();
                            const std = parseFloat(value) || 0;
                            if (!value || std <= 0) return '';
                            const channelPrice = parseFloat(d.price || d['Price'] || 0) || 0;
                            const dot = wfStdPrcChangeDotHtml(std, channelPrice);

                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                dot + ('$' + std.toFixed(2)) + '</span>';
                        }
                    },
                    {
                        title: 'Price', field: 'price', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(cell.getValue(), d.lmp_price || d.lmp || d.LMP) : '');
                            const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(cell.getValue(), d.lmp_price || d.lmp || d.LMP) : '');
                            return '<span style="font-weight:700;">' + money(cell.getValue()) + '</span>' + lmpTri + purpleTri;
                        }
                    },
                    {
                        // Miss L — same role as Amazon Miss L / ML badge.
                        title: 'Miss L', field: 'missing', hozAlign: 'center',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            if (!wfRowIsMissing(d)) return '';
                            return '<span class="badge bg-danger">L</span>';
                        }
                    },
                    {
                        // Miss M — mapping match/mismatch (Amazon Miss M / N Map).
                        title: 'Miss M',
                        field: 'map',
                        hozAlign: 'center',
                        width: 90,
                        headerTooltip: 'Map when listed, INV > 0, price > 0, and |INV − Wayfair stock| ≤ 3; N Map when |diff| > 3.',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const mapStatus = wfRowMapStatus(d);
                            if (mapStatus === 'map') {
                                return '<span style="color:#198754;font-weight:bold;">Map</span>';
                            }
                            if (mapStatus === 'nmap') {
                                const diff = Math.abs((parseInt(d.inv, 10) || 0) - (parseInt(d.ae_stock, 10) || 0));
                                return '<span style="color:#dc3545;font-weight:bold;">N Map (' + diff + ')</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: 'GROI%', field: 'groi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            // Same bands as /amazon-tabulator-view GROI%
                            let color;
                            if (v < 50) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'GPFT %', field: 'gpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0 && !d.is_parent) return '0%';
                            if (v === 0 && d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            // Same bands as /amazon-tabulator-view GPFT %
                            let color = v < 10 ? '#a00211' : v < 20 ? '#3591dc' : v < 30 ? '#ffc107' : v < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:' + (d.is_parent ? '700' : '600') + ';">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'PFT %', field: 'npft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            // Wayfair has no ads — PFT% = GPFT% (same as Amazon when Ads%=0)
                            const d = cell.getRow().getData();
                            const v = parseFloat(d.gpft);
                            if (isNaN(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0 && !d.is_parent) return '0%';
                            if (v === 0 && d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            let color = v < 10 ? '#a00211' : v < 20 ? '#3591dc' : v < 30 ? '#ffc107' : v < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:' + (d.is_parent ? '700' : '600') + ';">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'NROI', field: 'nroi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            // Wayfair has no ads — NROI% = GROI%
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(d.groi) || 0;
                            let color;
                            if (v < 50) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'Profit', field: 'profit', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return '<span style="color:' + color + ';font-weight:700;">' + money(v) + '</span>';
                            }
                            return money(v);
                        }
                    },
                    {
                        title: 'Sales', field: 'sales', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                return '<span style="font-weight:700;">' + money(v) + '</span>';
                            }
                            return money(v);
                        }
                    },
                    {
                        title: 'LP', field: 'lp', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                    {
                        title: 'Sprc Dil',
                        field: 'SPRC_DIL',
                        hozAlign: 'center',
                        headerSort: true,
                        sorter: function(a, b, aRow, bRow) {
                            const val = function(row) {
                                return (typeof ebaySprcDilForRow === 'function')
                                    ? (ebaySprcDilForRow(row) || 0)
                                    : 0;
                            };
                            return val(aRow.getData()) - val(bRow.getData());
                        },
                        headerTooltip: 'S PRC from Dil → Target GROI% slabs. 0 Sold (A L30 = 0, INV > 0) uses the lowest Target GROI in the table. Formula: (LP × (1 + GROI%/100)) / margin. Ship not used.',
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (isWayfairParentRow(rowData)) return '';
                            if (typeof ebayDilGroiMetaForRow !== 'function') return '';
                            const meta = ebayDilGroiMetaForRow(rowData);
                            if (!meta || !(meta.sprc > 0)) return '';
                            const tip = 'Dil ' + (isFinite(meta.dil) ? meta.dil.toFixed(1) : '0') + '%'
                                + ' → ' + meta.label
                                + ' → GROI ' + meta.groi + '%'
                                + ' → $' + meta.sprc.toFixed(2);
                            return '<span title="' + String(tip).replace(/"/g, '&quot;') + '" style="font-weight:600;color:#6f42c1;">$'
                                + meta.sprc.toFixed(2) + '</span>';
                        },
                        width: 78
                    },
                    {
                        title: 'S PRC', field: 'sprice', sorter: 'number', hozAlign: 'right',
                        editor: 'number', editorParams: { min: 0, step: 0.01 },
                        headerTooltip: 'S PRC from Sprc Dil. Dil-matching Target GROI when A L30 > 0; 0 Sold uses the lowest Target GROI in the table. S PRC = (LP × (1 + GROI%/100)) / margin (Ship not used). Blue triangle = S PRC ≠ Price. Red text = S PRC > LMP.',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            let value = parseFloat(cell.getValue() || 0);
                            if (typeof chPromoLiveSprice === 'function') {
                                const calc = chPromoLiveSprice(d);
                                if (calc > 0) value = calc;
                            }
                            const live = parseFloat(d.price) || 0;
                            const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                            if (!(value > 0)) return '';
                            const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(d, value) : null;
                            if (cap && cap.shown > 0) value = cap.shown;
                            const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                            const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                            const formatted = money(value);
                            const priceHtml = overLmp
                                ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                                : '<span style="font-weight:600;">' + formatted + '</span>';
                            const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">' + priceHtml + blueTri + '</span>';
                        }
                    },
                    {
                        title: 'S GPFT', field: 'sgpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            // Same bands as Amazon GPFT % / S GPFT
                            let color = v < 10 ? '#a00211' : v < 20 ? '#3591dc' : v < 30 ? '#ffc107' : v < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'SNPFT', field: 'snpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            // Wayfair has no ads — SNPFT = SGPFT
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(d.sgpft);
                            if (isNaN(v) || v === 0) return '0%';
                            let color = v < 10 ? '#a00211' : v < 20 ? '#3591dc' : v < 30 ? '#ffc107' : v < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'SNROI', field: 'sroi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            // Wayfair has no ads — SNROI = gross SROI (no Ads% cut)
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '<span style="font-weight:700;">0%</span>';
                            // Same bands as Amazon GROI% / SNROI
                            let color;
                            if (v < 50) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'NRP',
                        field: 'nr',
                        minWidth: 52,
                        width: 56,
                        hozAlign: 'center',
                        headerSort: true,
                        accessor: function(value, data) {
                            const val = data && data.nr != null ? data.nr : value;
                            if (val === null || val === undefined || String(val).trim() === '') return '';
                            const s = String(val).trim().toUpperCase();
                            return s === 'NR' ? 'NR' : 'REQ';
                        },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '') {
                                value = d.nr;
                            }
                            if (value === null || value === undefined) {
                                value = '';
                            } else {
                                value = String(value).trim().toUpperCase();
                            }
                            if (!value || value === '') {
                                value = 'REQ';
                            }
                            if (value !== 'REQ' && value !== 'NR') {
                                value = 'REQ';
                            }
                            const sku = String(d.sku || '');
                            const parent = d.parent != null ? String(d.parent) : '';
                            let dotColor = '#22c55e';
                            let tip = 'REQ';
                            if (value === 'NR') {
                                dotColor = '#dc3545';
                                tip = 'NR';
                            }
                            const skuAttr = wfEscHtmlAttr(sku);
                            const parentAttr = wfEscHtmlAttr(parent);
                            return (
                                '<select class="form-select form-select-sm nrp-nr-select" ' +
                                'data-type="NR" data-sku="' + skuAttr + '" data-parent="' + parentAttr + '" ' +
                                'style="width:50px;border:1px solid gray;padding:2px;font-size:20px;text-align:center;" ' +
                                'aria-label="NRP: ' + wfEscHtmlAttr(tip) + '">' +
                                '<option value="REQ"' + (value === 'REQ' ? ' selected' : '') + '>🟢</option>' +
                                '<option value="NR"' + (value === 'NR' ? ' selected' : '') + '>🔴</option>' +
                                '</select>'
                            );
                        }
                    },
                ],
                dataLoaded: function(data) {
                    allTableData = Array.isArray(data) ? data : [];
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    wfResetSkuColHoverWidth();
                    wfRemoveImagePreview();
                    updateSummary();
                    if (typeof ebayScheduleSprcDilAutoApply === 'function') {
                        ebayScheduleSprcDilAutoApply();
                    }
                },
                dataFiltered: function(filters, rows) {
                    updateSummary();
                },
                renderComplete: function() { setTimeout(updateSummary, 100); }
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                    onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
                });
                ParentExpand.bind();
            }

            table.on('tableBuilt', function() {
                wfBuildColumnDropdown();
                wfApplyColumnVisibilityFromServer();
            });

            table.on('scrollVertical', wfRemoveImagePreview);
            table.on('scrollHorizontal', wfRemoveImagePreview);

            $('#wayfair-pricing-table').on('mouseover', function(e) {
                if (!table || !e.target || typeof e.target.closest !== 'function') return;
                if (!e.target.closest('[tabulator-field="sku"]')) return;
                if (wfSkuColHoverActive) return;
                const col = table.getColumn('sku');
                if (!col) return;
                wfSkuColHoverBase = col.getWidth();
                if (wfSkuColHoverBase <= 0) return;
                col.setWidth(Math.round(wfSkuColHoverBase * 1.2));
                wfSkuColHoverActive = true;
            });

            $('#wayfair-pricing-table').on('mouseout', function(e) {
                if (!table || !wfSkuColHoverActive) return;
                const related = e.relatedTarget;
                const root = this;
                if (related && root.contains(related) && typeof related.closest === 'function' && related.closest('[tabulator-field="sku"]')) {
                    return;
                }
                const col = table.getColumn('sku');
                if (col && wfSkuColHoverBase != null) col.setWidth(wfSkuColHoverBase);
                wfSkuColHoverActive = false;
                wfSkuColHoverBase = null;
            });

            wfSyncPriceModeUi();

            $('#wf-price-mode-btn').on('click', function() {
                if (!wfDecreaseModeActive && !wfIncreaseModeActive && !wfUniformPriceModeActive) {
                    wfDecreaseModeActive = true;
                    wfIncreaseModeActive = false;
                    wfUniformPriceModeActive = false;
                } else if (wfDecreaseModeActive) {
                    wfDecreaseModeActive = false;
                    wfIncreaseModeActive = true;
                    wfUniformPriceModeActive = false;
                } else if (wfIncreaseModeActive) {
                    wfDecreaseModeActive = false;
                    wfIncreaseModeActive = false;
                    wfUniformPriceModeActive = true;
                } else {
                    wfDecreaseModeActive = false;
                    wfIncreaseModeActive = false;
                    wfUniformPriceModeActive = false;
                }
                wfSyncPriceModeUi();
            });

            $('#wf-discount-type').on('change', function() {
                $('#wf-discount-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
            });
            $('#wf-apply-discount-btn').on('click', function() { wfApplyDiscount(); });
            $('#wf-discount-input').on('keypress', function(e) { if (e.which === 13) wfApplyDiscount(); });
            $('#wf-clear-sprice-btn').on('click', function() { wfClearSpriceForSelected(); });

            /*
             * Target ROI% / Target GPFT% bulk apply (Wayfair, per-row `_margin`, default 0.95)
             * ------------------------------------------------------------------------------
             * Wayfair's server-side SGPFT / SROI formulas (WayfairController::saveWayfairSpriceUpdates,
             * lines ~922-923) do NOT include shipping — they're just:
             *     SGPFT% = ((sprice * margin − lp) / sprice) * 100
             *     SROI%  = ((sprice * margin − lp) / lp)     * 100
             * Back-solving each:
             *     SROI = target  →  sprice = lp * (1 + ROI%/100) / margin
             *     SGPFT = target →  sprice = lp / (margin − GPFT%/100)
             * Optimistic SGPFT / SROI written client-side (using wayfairMarginFromRow), then the
             * existing /wayfair/pricing-save-sprice endpoint reconciles them server-side. Plain
             * 2-decimal rounding — no .99 snapping — because snapping would shift the achieved
             * SROI / SGPFT away from the typed target.
             */
            $('#wf-apply-target-roi-btn').on('click', function () {
                const rawInput = $('#wf-target-roi-input').val();
                const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    if (window.toastr) toastr.error('Please enter a Target ROI%');
                    return;
                }
                if (!isFinite(targetRoiPct)) {
                    if (window.toastr) toastr.error('Target ROI% must be a number');
                    return;
                }
                if (wfSelectedSkus.size === 0) {
                    const selectCol = table ? table.getColumn('_wf_select') : null;
                    if (selectCol) selectCol.show();
                    if (window.toastr) toastr.warning('Please check at least one SKU first (toggle Pricing mode to reveal checkboxes)');
                    return;
                }

                const roiMultiplier = 1 + (targetRoiPct / 100);
                const updates = [];
                let updatedCount = 0;
                let skippedNoLp  = 0;

                wfSelectedSkus.forEach(function (sku) {
                    const row = wfFindActiveSkuRow(sku);
                    if (!row) return;
                    const rowData = row.getData();
                    if (rowData.is_parent) return;

                    const lp = parseFloat(rowData.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }

                    const margin = wayfairMarginFromRow(rowData);
                    const candidate = (lp * roiMultiplier) / margin;
                    const newSprice = +candidate.toFixed(2);
                    if (!isFinite(newSprice) || newSprice <= 0) return;

                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                    const sroi  = lp > 0       ? Math.round(((newSprice * margin - lp) / lp)     * 100) : 0;

                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                    updates.push({ sku: sku, sprice: newSprice });
                    updatedCount++;
                });

                if (updates.length === 0) {
                    if (window.toastr) toastr.warning('No checked rows have a usable LP > 0');
                    return;
                }

                saveWayfairSpriceUpdates(updates);
                const note = skippedNoLp > 0 ? ' (' + skippedNoLp + ' skipped — no LP)' : '';
                if (window.toastr) toastr.success('Target ROI ' + targetRoiPct + '% applied to ' + updatedCount + ' SKU(s)' + note);
            });

            $('#wf-apply-target-gpft-btn').on('click', function () {
                const rawInput = $('#wf-target-gpft-input').val();
                const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    if (window.toastr) toastr.error('Please enter a Target GPFT%');
                    return;
                }
                if (!isFinite(targetGpftPct)) {
                    if (window.toastr) toastr.error('Target GPFT% must be a number');
                    return;
                }
                if (wfSelectedSkus.size === 0) {
                    const selectCol = table ? table.getColumn('_wf_select') : null;
                    if (selectCol) selectCol.show();
                    if (window.toastr) toastr.warning('Please check at least one SKU first (toggle Pricing mode to reveal checkboxes)');
                    return;
                }

                const targetFraction = targetGpftPct / 100;
                const updates = [];
                let updatedCount = 0;
                let skippedNoLp  = 0;
                const skippedHighGpft = [];

                wfSelectedSkus.forEach(function (sku) {
                    const row = wfFindActiveSkuRow(sku);
                    if (!row) return;
                    const rowData = row.getData();
                    if (rowData.is_parent) return;

                    const lp = parseFloat(rowData.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }

                    const margin = wayfairMarginFromRow(rowData);
                    const denom  = margin - targetFraction;
                    if (denom <= 0) { skippedHighGpft.push(sku); return; }
                    const candidate = lp / denom;
                    const newSprice = +candidate.toFixed(2);
                    if (!isFinite(newSprice) || newSprice <= 0) return;

                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                    const sroi  = lp > 0       ? Math.round(((newSprice * margin - lp) / lp)     * 100) : 0;

                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                    updates.push({ sku: sku, sprice: newSprice });
                    updatedCount++;
                });

                if (updates.length === 0) {
                    if (skippedHighGpft.length > 0) {
                        if (window.toastr) toastr.error('Target GPFT% ' + targetGpftPct + '% is too high — must be less than each row\'s take-home margin (typically < 95%).');
                    } else {
                        if (window.toastr) toastr.warning('No checked rows have a usable LP > 0');
                    }
                    return;
                }

                saveWayfairSpriceUpdates(updates);
                let note = '';
                if (skippedNoLp > 0)        note += ' (' + skippedNoLp + ' skipped — no LP)';
                if (skippedHighGpft.length) note += ' (' + skippedHighGpft.length + ' skipped — target ≥ margin)';
                if (window.toastr) toastr.success('Target GPFT ' + targetGpftPct + '% applied to ' + updatedCount + ' SKU(s)' + note);
            });

            $('#wf-target-roi-input').on('keypress', function (e) {
                if (e.which === 13) $('#wf-apply-target-roi-btn').click();
            });
            $('#wf-target-gpft-input').on('keypress', function (e) {
                if (e.which === 13) $('#wf-apply-target-gpft-btn').click();
            });

            $(document).on('change', '#wf-select-all', function() {
                const checked = $(this).prop('checked');
                const skuRows = wfAllFilteredSkuRows();
                skuRows.forEach(function(row) {
                    const d = row.getData();
                    const sku = d.sku != null ? String(d.sku) : '';
                    if (!sku) return;
                    if (checked) wfSelectedSkus.add(sku); else wfSelectedSkus.delete(sku);
                });
                skuRows.forEach(function(row) {
                    const cellEl = row.getElement();
                    if (!cellEl) return;
                    const chk = cellEl.querySelector('.wf-sku-chk');
                    if (chk) chk.checked = checked;
                });
                const head = document.getElementById('wf-select-all');
                if (head) head.indeterminate = false;
                wfUpdateSelectedCount();
            });

            $(document).on('change', '.wf-sku-chk', function() {
                const sku = $(this).attr('data-sku');
                if (!sku) return;
                if ($(this).prop('checked')) wfSelectedSkus.add(sku); else wfSelectedSkus.delete(sku);
                wfUpdateSelectedCount();
                wfSyncSelectAllHeaderCheckbox();
            });

            table.on('pageLoaded', function() {
                wfSyncSelectAllHeaderCheckbox();
            });
            table.on('renderComplete', function() {
                wfSyncSelectAllHeaderCheckbox();
            });

            table.on('cellEdited', function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const d = row.getData();
                const value = cell.getValue();

                if (field === 'STANDARD_PRICE') {
                    if (d.is_parent) return;
                    const sku = d.sku || d['(Child) sku'] || d.SKU;
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
                            applyWfStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                            const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                            if (typeof showToast === 'function') {
                                showToast(n > 1
                                    ? ('Std Prc saved for ' + n + ' linked SKUs')
                                    : 'Std Prc saved', 'success');
                            }
                        },
                        error: function() {
                            if (typeof showToast === 'function') showToast('Failed to save Std Prc', 'error');
                        }
                    });
                    return;
                }

                if (field !== 'sprice') return;
                if (d.is_parent) return;
                const sku = d.sku;
                const sprice = parseFloat(cell.getValue()) || 0;
                const margin = wayfairMarginFromRow(d);
                const lp = parseFloat(d.lp) || 0;
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - lp) / sprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((sprice * margin - lp) / lp) * 100) : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi });
                saveWayfairSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            $('#wf-pricing-parent-search, #wf-pricing-sku-search').on('input', function() { applyFilters(); });
            $('#wf-row-type-filter, #wf-inv-filter, #wf-gpft-filter, #wf-cvr-filter, #wf-roi-filter, #wf-dil-filter').on('change', function() {
                wfClearSkuSelections();
                applyFilters();
            });

            // ---- Edit B/S Links (double-click on B/S cell) ----
            let wfEditLinksRow = null;
            window.openWfEditLinksModal = function(row) {
                if (!row) return;
                wfEditLinksRow = row;
                const d = row.getData();
                $('#wfEditLinksSku').val(d.sku);
                $('#wfEditLinksSkuDisplay').text(d.sku);
                $('#wfEditBuyerLink').val(d.buyer_link || '');
                $('#wfEditSellerLink').val(d.seller_link || '');
                $('#wfEditLinksError').hide().text('');
                new bootstrap.Modal(document.getElementById('wfEditLinksModal')).show();
            };

            $(document).on('click', '#wfSaveLinksBtn', function() {
                const sku = $('#wfEditLinksSku').val();
                const buyerLink = $('#wfEditBuyerLink').val().trim();
                const sellerLink = $('#wfEditSellerLink').val().trim();
                const $err = $('#wfEditLinksError');
                $err.hide().text('');
                const $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: '{{ route("wayfair.pricing.save.links") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { _token: '{{ csrf_token() }}', sku: sku, buyer_link: buyerLink, seller_link: sellerLink },
                    success: function(res) {
                        if (wfEditLinksRow) {
                            wfEditLinksRow.update({ buyer_link: res.buyer_link || null, seller_link: res.seller_link || null })
                                .then(function() { wfEditLinksRow.reformat(); })
                                .catch(function() { wfEditLinksRow.reformat(); });
                        }
                        if (window.toastr) toastr.success(sku + ': links saved');
                        bootstrap.Modal.getInstance(document.getElementById('wfEditLinksModal'))?.hide();
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Failed to save links.';
                        $err.text(msg).show();
                    },
                    complete: function() { $btn.prop('disabled', false); }
                });
            });

            $(document).on('click', '.wf-copy-sku-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).attr('data-sku');
                if (!sku) return;
                const done = function() {
                    if (window.toastr) toastr.success('SKU copied');
                };
                const fail = function() {
                    if (window.toastr) toastr.error('Could not copy SKU');
                    else alert('Could not copy SKU');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(sku).then(done).catch(fail);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = sku;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy')) done(); else fail();
                    } catch (err) { fail(); }
                    document.body.removeChild(ta);
                }
            });

            $('#wf-missing-badge').on('click', function() {
                wfMissingActive = !wfMissingActive;
                wfMapActive = wfNMapActive = false;
                wfSoldFilter = 'all';
                wfClearSkuSelections();
                applyFilters();
            });
            $('#wf-nmap-count-badge').on('click', function() {
                wfNMapActive = !wfNMapActive;
                wfMissingActive = wfMapActive = false;
                wfSoldFilter = 'all';
                wfClearSkuSelections();
                applyFilters();
            });
            $('#wf-zero-sold-badge').on('click', function() {
                wfSoldFilter = wfSoldFilter === '0' ? 'all' : '0';
                wfMissingActive = wfMapActive = wfNMapActive = false;
                wfClearSkuSelections();
                applyFilters();
            });
            $('#wf-more-sold-badge').on('click', function() {
                wfSoldFilter = wfSoldFilter === 'more' ? 'all' : 'more';
                wfMissingActive = wfMapActive = wfNMapActive = false;
                wfClearSkuSelections();
                applyFilters();
            });

            function wfPatchRowForNrpChange(d, newValue) {
                // Only update nr — Missing L and Map columns are computed from d.nr + d.price
                // in their formatters (Macy's pattern), so no patch to missing/map fields needed.
                return { nr: newValue };
            }

            $(document).on('change', '#wayfair-pricing-table .nrp-nr-select', function() {
                const $el = $(this);
                const newValue = String($el.val() || '').trim();
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                if (!sku || !table) return;
                const rows = table.getRows().filter(function(r) {
                    const d = r.getData();
                    return !d.is_parent && String(d.sku) === String(sku);
                });
                const row = rows.length ? rows[0] : null;
                const prevNr = row ? String(row.getData().nr ?? '').trim().toUpperCase() : '';
                const prevSelect = prevNr === 'NR' ? 'NR' : 'REQ';
                // Update nr field → Missing L / Map formatters recompute from d.nr + d.price (Macy's pattern).
                if (row) {
                    row.update({ nr: newValue }, true);
                    ['nr', 'missing', 'map'].forEach(function(field) {
                        const c = row.getCells().find(function(cell) { return cell.getField() === field; });
                        if (c) c.reformat();
                    });
                    updateSummary();
                }
                wfSaveNrp(
                    { sku: sku, parent: parent, value: newValue },
                    function() {},
                    function() {
                        $el.val(prevSelect);
                        if (row) {
                            row.update({ nr: prevNr }, true);
                            ['nr', 'missing', 'map'].forEach(function(field) {
                                const c = row.getCells().find(function(cell) { return cell.getField() === field; });
                                if (c) c.reformat();
                            });
                            updateSummary();
                        }
                    }
                );
            });

            $('#wf-export-pricing').on('click', function() {
                table.download('csv', 'wayfair_analytics_data.csv');
            });

            const wfColMenu = document.getElementById('wf-column-dropdown-menu');
            if (wfColMenu) {
                wfColMenu.addEventListener('change', function(e) {
                    if (e.target.classList.contains('wf-column-toggle')) {
                        const field = e.target.getAttribute('data-field');
                        const col = field ? table.getColumn(field) : null;
                        if (col) {
                            if (e.target.checked) {
                                col.show();
                            } else {
                                col.hide();
                            }
                            wfSaveColumnVisibilityToServer();
                        }
                    }
                });
            }
            $('#wfUploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('wfPriceSheetFile').files[0];
                if (!file) {
                    alert('Please select a file first.');
                    return;
                }
                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');
                $.ajax({
                    url: '{{ route("wayfair.pricing.upload.price") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (window.toastr) toastr.success(response.message || 'Upload completed.');
                        else alert(response.message || 'Upload completed.');
                        $('#uploadWayfairPriceModal').modal('hide');
                        $('#wfPriceSheetFile').val('');
                        table.setData('{{ route("wayfair.pricing.data") }}');
                    },
                    error: function(xhr) {
                        const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Upload failed.';
                        if (window.toastr) toastr.error(message);
                        else alert(message);
                    }
                });
            });
        });
    </script>
@endsection
