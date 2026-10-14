@extends('layouts.vertical', ['title' => 'TopDawg - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; white-space: nowrap;
            transform: rotate(180deg); height: 80px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        #summary-stats .badge.active-filter {
            box-shadow: 0 0 0 3px rgba(255,255,255,.85), 0 0 0 5px currentColor;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'topdawg'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TopDawg - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-1">
                {{-- Summary badges — each badge gets flex:1 + text-align:center so the
                     row of badges spans the FULL width of the card from left → right,
                     each one occupying an equal slice. flex-nowrap keeps it as a
                     single visual band. Padding kept tight (p-1 / py-1 px-2) so the
                     strip doesn't dominate the screen. --}}
                <div id="summary-stats" class="p-1 bg-light rounded">
                    <div class="d-flex flex-nowrap gap-1 w-100" style="overflow-x:auto;">
                        {{-- Badges are intentionally ~1.5× the default `.badge` height:
                             custom padding (8px vert / 10px horiz) + font-size 14px
                             gives a visible, easy-to-read pill while still keeping
                             everything on a single horizontal strip. The flex:1 1 0
                             rule still distributes them equally across the row. --}}
                        {{-- Rows — count of SKUs visible after all current filters
                             apply. Matches the row count rendered in the table /
                             feeding every other summary badge. PARENT rows are
                             excluded (same shape as everything else here). --}}
                        <span class="badge text-center" id="total-rows-badge"
                              style="background:#343a40;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Number of SKU rows currently passing the filters">Rows: 0</span>
                        <span class="badge text-center" id="total-sales-badge"
                              style="background:#198754;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Exact Total Revenue from /topdawg/sales-dashboard (Σ amount on all topdawg_order_metrics rows)">Sales: ${{ number_format($topdawgSalesDashboardRevenue ?? 0, 2, '.', ',') }}</span>
                        <span class="badge bg-success text-center" id="total-td-l30-badge" style="color:#000;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;" title="Sum of TD L30 qty from topdawg_order_metrics (L30) on filtered rows">TD L30: 0</span>
                        <span class="badge bg-danger text-center" id="zero-sold-badge" style="color:#fff;font-weight:bold;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;" title="SKUs with TD L30 = 0">0 Sold: 0</span>
                        <span class="badge text-center" id="more-sold-badge" style="background:#28a745;color:#fff;font-weight:bold;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;" title="SKUs with TD L30 &gt; 0">&gt; 0 Sold: 0</span>
                        {{-- GPFT / GROI pinned to /topdawg/sales-dashboard. --}}
                        <span class="badge text-center" id="gpft-pct-badge"
                              style="background:#6f42c1;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Exact GPFT from /topdawg/sales-dashboard: rounded PFT ÷ rounded Amount. PFT = (Amount × margin) − COGS − COGS Ship.">GPFT: {{ (int) round((float) ($topdawgSalesDashboardGpft ?? 0)) }}%</span>
                        <span class="badge text-center" id="groi-pct-badge"
                              style="background:#0d6efd;color:#fff;font-weight:bold;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                              title="Exact GROI from /topdawg/sales-dashboard: rounded PFT ÷ rounded COGS. COGS = LP × Qty.">GROI: {{ (int) round((float) ($topdawgSalesDashboardRoi ?? 0)) }}%</span>
                        <span class="badge bg-danger text-center" id="missing-badge" style="color:#fff;font-weight:bold;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;" title="REQ + INV&gt;0 + TD Price=0">Missing L: 0</span>
                        <span class="badge text-center" id="topdawg-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;flex:1 1 0;min-width:90px;font-size:14px;padding:8px 10px;"
                            title="Blue triangle: S PRC ≠ Price and not yet submitted at this S PRC. Click to show only those rows. Already-pushed SKUs stay yellow until TopDawg approves the review (1–24h).">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        @include('partials.analytics-dil-badge', ['dilChannel' => 'topdawg'])
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'topdawg-price-gt-lmp-badge', 'pglChannelKey' => 'topdawg', 'pglPriceField' => 'TD Price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'topdawg-price-lt80-lmp-badge', 'pltChannelKey' => 'topdawg', 'pltPriceField' => 'TD Price'])
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Pricing-mode input bar (shown when a mode is active + SKUs selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display:none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width:140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-selected-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="topdawg-table-wrapper" style="height:calc(100vh - 240px);display:flex;flex-direction:column;">
                    {{-- Filter / action row — lives ABOVE the search row.
                         flex-wrap keeps everything on one line on wide screens
                         and falls back to wrapping (no horizontal scrollbar)
                         on narrower viewports. Tight px-2 py-1 + gap-1 keeps
                         the strip vertically compact. --}}
                    <div class="px-2 py-1 bg-light border-bottom d-flex flex-wrap gap-1 align-items-center">
                        <select id="inventory-filter" class="form-select form-select-sm" style="width:90px;">
                            <option value="all">ALL</option>
                            <option value="zero">0 Inv</option>
                            <option value="more" selected>&gt; 0</option>
                        </select>
                        <select id="nrl-filter" class="form-select form-select-sm" style="width:100px;">
                            <option value="all">All Status</option>
                            <option value="REQ" selected>REQ Only</option>
                            <option value="NR">NR Only</option>
                        </select>
                        <select id="gpft-filter" class="form-select form-select-sm" style="width:95px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30plus">30%+</option>
                        </select>
                        {{-- GROI / ROI% bracket filter — backed by the per-row `ROI%` field
                             (((TD Price × {{ $topdawgPercentage }}% − LP) / LP) × 100 — no ship).
                             Brackets match the column's existing color thresholds
                             (red < 40, yellow 40–75, green 75–125, pink ≥ 125) so the
                             dropdown options stay visually consistent with the cell colors. --}}
                        <select id="groi-filter" class="form-select form-select-sm" style="width:90px;"
                                title="Filter by per-row GROI% (matches GROI badge bracketing)">
                            <option value="all">GROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="125plus">125%+</option>
                        </select>
                        {{-- DIL% bracket filter — backed by the per-row `Dil` field
                             ((L30 ÷ INV) × 100, controller-computed at fetch time).
                             Buckets were 4 (red / yellow / green / pink) but the
                             yellow band (16.7–25%) was merged into red, so the
                             filter — and the cell color formatter below — now use
                             three buckets: red < 25, green 25–50, pink ≥ 50. --}}
                        <select id="dil-filter" class="form-select form-select-sm" style="width:95px;"
                                title="Filter by per-row DIL% (matches Dil column color buckets)">
                            <option value="all">All DIL%</option>
                            <option value="red">Red (&lt;25%)</option>
                            <option value="green">Green (25–50%)</option>
                            <option value="pink">Pink (50%+)</option>
                        </select>
                        {{-- Sold dropdown (mirrors Amazon tabulator + every other /pricing page).
                             Backed by `TD L30`:
                               all  → no filter
                               sold → TD L30 > 0
                               zero → TD L30 = 0
                             Single source of truth — the #zero-sold-badge / #more-sold-badge clicks
                             and the ?badge=zero_sold|more_sold URL deep-link all write into this
                             dropdown so badges + dropdown + URL stay in sync. --}}
                        <select id="sold-filter" class="form-select form-select-sm" style="width:90px;"
                                title="Filter by TD L30 sold quantity">
                            <option value="all">Sold</option>
                            <option value="sold">Sold &gt; 0</option>
                            <option value="zero">0 Sold</option>
                        </select>
                        {{-- Column-visibility controls — same UX as /purchasing-power-pricing.
                             • Dropdown shows a checkbox per data column; toggling persists
                               immediately to /topdawg-pricing-column-visibility (server-side
                               cache, keyed per user, 1-year TTL).
                             • "Show All" resets every column to visible and saves. --}}
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                                title="Show / hide columns (saved per user)">
                                <i class="fa fa-eye"></i>
                            </button>
                            <ul class="dropdown-menu" id="column-dropdown-menu"
                                style="max-height: 400px; overflow-y: auto;"></ul>
                        </div>
                        <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary"
                                title="Show all columns" aria-label="Show all columns">
                            <i class="fa fa-eye"></i>
                        </button>
                        <button id="export-btn" class="btn btn-sm btn-info" title="Export CSV" aria-label="Export CSV"><i class="fas fa-download"></i></button>

                        {{-- Single price-mode cycle button — same UX as /doba-tabulator and other
                             marketplace pricing pages. Click to cycle through:
                               Off → Decrease → Increase → Same Price → Off --}}
                        <button id="td-price-mode-btn" type="button" class="btn btn-sm btn-secondary"
                                title="Cycle pricing mode: Off → Decrease → Increase → Same Price → Off">
                            <i class="fas fa-exchange-alt"></i> Price %
                        </button>
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'topdawg'])
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>

                    {{-- Dedicated search row — Search SKU + Search Parent each take
                         half the row (col-6) so they span the full table width.
                         Tight px-2 py-1 keeps this strip slim. Sits BELOW the
                         filter/action row so search inputs are nearest to the
                         table they affect (per the user's "interchange rows" ask). --}}
                    <div class="px-2 py-1 bg-light border-bottom">
                        <div class="d-flex gap-1 align-items-center">
                            <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="flex:1;">
                            <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="flex:1;">
                            <button type="button" id="td-search-btn" class="btn btn-sm btn-primary" title="Search Parent / SKU">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div id="topdawg-pricing-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="tdEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tdEditLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="tdEditLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="tdEditSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="tdEditSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="tdEditBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="tdEditBuyerLink" placeholder="https://...">
                    </div>
                    <div id="tdEditLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="tdSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'topdawg'])
@endsection

@section('script-bottom')
    @include('partials.lazy-chart-js')
<script>
    // S PRC is saved + pushed by the server (topdawg:sprc-dil-auto-push). The page only shows it.
    window.CH_STD_SERVER_OWNED = true;
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'topdawg'])
    const TD_MAP_TOLERANCE = 3;
    const TD_PERCENTAGE    = {{ $topdawgPercentage }} / 100;
    // Pinned to /topdawg/sales-dashboard Total Revenue / PFT% / ROI%.
    const TD_SALES_DASHBOARD_REVENUE = {{ number_format((float) ($topdawgSalesDashboardRevenue ?? 0), 2, '.', '') }};
    const TD_SALES_DASHBOARD_GPFT = {{ (int) round((float) ($topdawgSalesDashboardGpft ?? 0)) }};
    const TD_SALES_DASHBOARD_ROI = {{ (int) round((float) ($topdawgSalesDashboardRoi ?? 0)) }};
    let table = null;
    let allTableData = [];
    // zeroSoldFilter / moreSoldFilter removed — Sold filter is now owned by the
    // #sold-filter dropdown (driven by badge clicks and the ?badge=zero_sold|more_sold URL).
    let missingFilter = false;
    let priceGtLmpFilterActive = false;
    let priceLt80LmpFilterActive = false;
    let blueTriangleFilterActive = false;

    function tdIsParentRow(d) {
        return !!(d && (d.is_parent_summary || d.is_parent || (d.Parent && String(d.Parent).toUpperCase().indexOf('PARENT') === 0)));
    }
    function tdAmzPrice(data) {
        return Math.round((parseFloat(data && data['A Price']) || 0) * 100) / 100;
    }
    function tdRawSprice(data) {
        if (!data || tdIsParentRow(data)) return 0;
        if (typeof chPromoTableSprice === 'function') {
            const saved = Number(chPromoTableSprice(data)) || 0;
            if (saved > 0) return saved;
        }
        if (typeof chPromoSavedOrLiveSprice === 'function') {
            return Number(chPromoSavedOrLiveSprice(data)) || 0;
        }
        return parseFloat(data.SPRICE) || 0;
    }
    /** Amz rule on display: a saved S PRC under A Price shows A Price right away, before the next server run re-saves it. */
    function tdDisplayedSprice(data) {
        const v = tdRawSprice(data);
        const amz = tdAmzPrice(data);
        if (v > 0 && amz > 0 && v + 0.0001 < amz) return amz;
        return v;
    }
    function tdHasAmzCap(data) {
        if (!data || tdIsParentRow(data)) return false;
        const amz = tdAmzPrice(data);
        const shown = tdDisplayedSprice(data);
        if (!(amz > 0) || !(shown > 0) || Math.abs(shown - amz) > 0.015) return false;
        const flag = data.AMZ_CAPPED === true || data.AMZ_CAPPED === 1 || data.AMZ_CAPPED === '1';
        return flag || tdRawSprice(data) + 0.0001 < amz;
    }
    function tdRowSpriceForAlert(data) {
        return tdDisplayedSprice(data);
    }
    window.tdDisplayedSprice = tdDisplayedSprice;
    function tdHasBlueTriangle(data) {
        if (tdIsParentRow(data)) return false;
        if (!(parseFloat(data && data.INV) > 0)) return false;
        const sprice = tdRowSpriceForAlert(data);
        const price = parseFloat(data && data['TD Price']) || 0;
        if (!(sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100))) {
            return false;
        }
        // Already submitted to TopDawg review at this S PRC — waiting on TD,
        // not another push. Yellow SPRICE cell shows "queued in review".
        const status = String((data && (data.SPRICE_STATUS || data.push_status)) || '').toLowerCase();
        const pushed = parseFloat(data && (data.SPRICE_PUSHED_VALUE != null ? data.SPRICE_PUSHED_VALUE : data.CHANNEL_PUSHED_PRICE)) || 0;
        if (status === 'pushed' && pushed > 0 && Math.round(pushed * 100) === Math.round(sprice * 100)) {
            return false;
        }
        return true;
    }
    window.tdHasBlueTriangle = tdHasBlueTriangle;
    function syncTdTriangleBadgeState() {
        $('#topdawg-blue-triangle-badge').css({
            outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
            outlineOffset: blueTriangleFilterActive ? '2px' : ''
        });
    }

    // Pricing-mode state
    let tdDecreaseModeActive = false;
    let tdIncreaseModeActive = false;
    let tdSamePriceModeActive = false;
    let tdSelectedSkus = new Set();

    /** Std Prc vs channel price: reduce / hold / increase → red / yellow / green. */
    function tdStdPrcChangeDotMeta(stdPrc, comparePrice) {
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

    function tdStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = tdStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyTdStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
        applyTdStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

    function tdAnyModeActive() {
        return tdDecreaseModeActive || tdIncreaseModeActive || tdSamePriceModeActive;
    }

    // Update the single #td-price-mode-btn to reflect the current mode state.
    // Replaces the old per-button reset helpers (tdResetDecreaseBtn / …Increase / …SamePrice)
    // — now a single sync function owns the button's color + icon + label for every state.
    function tdSyncPriceModeUi() {
        const $btn = $('#td-price-mode-btn');
        if (!$btn.length) return;
        const allColorClasses = 'btn-secondary btn-warning btn-success btn-info btn-danger';
        if (tdDecreaseModeActive) {
            $btn.removeClass(allColorClasses).addClass('btn-danger')
                .html('<i class="fas fa-arrow-down"></i> Decrease ON');
        } else if (tdIncreaseModeActive) {
            $btn.removeClass(allColorClasses).addClass('btn-danger')
                .html('<i class="fas fa-arrow-up"></i> Increase ON');
        } else if (tdSamePriceModeActive) {
            $btn.removeClass(allColorClasses).addClass('btn-danger')
                .html('<i class="fas fa-equals"></i> Same Price ON');
        } else {
            $btn.removeClass(allColorClasses).addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price %');
        }
    }

    function tdSyncDiscountInputUi() {
        const $input = $('#discount-percentage-input');
        if (tdSamePriceModeActive) {
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

    function tdShowSelectColumn(show) {
        // Selection column is always visible now (see column definition above).
        // Kept as a no-op so existing mode-toggle handlers don't need editing —
        // they call this with `false` when a mode is turned off, but we no longer
        // want to hide the column in that case.
        if (!table) return;
        const col = table.getColumn('_select');
        if (col && !col.isVisible()) col.show();
    }

    function tdUpdateSelectedCount() {
        const count = tdSelectedSkus.size;
        $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
        $('#discount-input-container').toggle(tdAnyModeActive() && count > 0);
    }

    // Returns the rows on the *current* pagination page (excluding PARENT rows).
    //
    // IMPORTANT: do NOT use Tabulator's getRows('visible') here — that returns
    // only the rows currently rendered to the DOM viewport (virtual-scroll
    // window), which is usually just ~7-15 rows depending on table height.
    // With a paginationSize of 100, that means the header "select all" would
    // miss the other ~85 rows on the same page.
    //
    // Instead, fetch ALL active (filter-passing) rows and slice them by the
    // current page bounds. That way "select all" always selects every row on
    // the current pagination page, regardless of scroll position.
    function tdGetCurrentPageRows() {
        if (!table) return [];
        const allActive = table.getRows('active').filter(r => {
            const p = (r.getData().Parent || '');
            return !(p && String(p).toUpperCase().startsWith('PARENT'));
        });
        const pageSize = (typeof table.getPageSize === 'function' ? table.getPageSize() : 0) || 100;
        const currentPage = (typeof table.getPage === 'function' ? table.getPage() : 1) || 1;
        const start = (currentPage - 1) * pageSize;
        return allActive.slice(start, start + pageSize);
    }

    function tdUpdateSelectAllHeaderCheckbox() {
        const el = document.getElementById('td-select-all');
        if (!el || !table) return;
        const rows = tdGetCurrentPageRows();
        if (!rows.length) { el.checked = false; el.indeterminate = false; return; }
        let selected = 0;
        rows.forEach(r => {
            const sku = String(r.getData()['(Child) sku'] || '');
            if (sku && tdSelectedSkus.has(sku)) selected++;
        });
        el.checked = selected === rows.length && rows.length > 0;
        el.indeterminate = selected > 0 && selected < rows.length;
    }

    function tdRoundToRetailPrice(price) {
        const p = parseFloat(price) || 0;
        if (p < 20.99) return +p.toFixed(2);
        return +(Math.ceil(p) - 0.01).toFixed(2);
    }

    function tdShowToast(msg, type) {
        type = type || 'success';
        if (window.toastr) { toastr[type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success'](msg); return; }
        let c = document.querySelector('.toast-container');
        if (!c) { c = document.createElement('div'); c.className = 'toast-container'; document.body.appendChild(c); }
        const bg = type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#198754';
        const t = document.createElement('div');
        t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;min-width:240px;color:#fff;background:' + bg + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    function tdSaveSpriceUpdates(updates, opts) {
        opts = opts || {};
        if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst !== false) {
            chPromoBatchClearThenSave(updates, function(next) {
                tdSaveSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
            }, {
                wipeFn: function(zeros) {
                    return $.ajax({
                        url: "{{ route('topdawg.save.sprice') }}",
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}', updates: zeros }
                    });
                }
            });
            return;
        }
        $.ajax({
            url: "{{ route('topdawg.save.sprice') }}",
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', updates: updates },
            success: function(res) {
                if (!res || res.success !== true) {
                    tdShowToast((res && res.message) || 'Failed to save SPRICE', 'error');
                }
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to save SPRICE';
                tdShowToast(msg, 'error');
            }
        });
    }

    function tdApplyDiscount() {
        const discountType = $('#discount-type-select').val();
        const discountValue = parseFloat($('#discount-percentage-input').val());

        if (!tdAnyModeActive()) { tdShowToast('Turn on Decrease, Increase, or Same Price mode first', 'error'); return; }
        if (isNaN(discountValue) || discountValue <= 0) {
            tdShowToast(tdSamePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
            return;
        }
        if (tdSelectedSkus.size === 0) { tdShowToast('Please select at least one SKU', 'error'); return; }

        const updates = [];
        let updatedCount = 0;

        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d['(Child) sku'] != null ? String(d['(Child) sku']) : '';
            if (!sku || !tdSelectedSkus.has(sku)) return;

            const currentPrice = parseFloat(d['TD Price']) || 0;
            if (!tdSamePriceModeActive && currentPrice <= 0) return;

            let newSprice;
            if (tdSamePriceModeActive) {
                newSprice = Math.max(0.99, discountValue);
            } else if (discountType === 'percentage') {
                newSprice = tdIncreaseModeActive
                    ? currentPrice * (1 + discountValue / 100)
                    : currentPrice * (1 - discountValue / 100);
            } else {
                newSprice = tdIncreaseModeActive
                    ? currentPrice + discountValue
                    : currentPrice - discountValue;
            }

            newSprice = Math.max(0.99, tdRoundToRetailPrice(newSprice));

            const lp   = parseFloat(d.LP_productmaster) || 0;
            // No ship — same as /topdawg/sales-dashboard
            const sgpft = newSprice > 0 ? Math.round(((newSprice * TD_PERCENTAGE - lp) / newSprice) * 100) : 0;
            const sroi  = lp > 0 ? Math.round(((newSprice * TD_PERCENTAGE - lp) / lp) * 100) : 0;

            row.update({ SPRICE: newSprice, SGPFT: sgpft, SROI: sroi });
            updates.push({ sku: sku, sprice: newSprice });
            updatedCount++;
        });

        if (!updates.length) { tdShowToast('No matching selected rows in the current view.', 'warning'); return; }

        tdSaveSpriceUpdates(updates);
        const action = tdSamePriceModeActive ? 'Same Price' : (tdIncreaseModeActive ? 'Increase' : 'Decrease');
        tdShowToast(`${action} applied to ${updatedCount} SKU(s)`, 'success');
        $('#discount-percentage-input').val('');
    }

    function tdApplyRuleSpriceToRow(row, price) {
        const d = row.getData() || {};
        const lp = parseFloat(d.LP_productmaster) || 0;
        const sgpft = price > 0 ? Math.round(((price * TD_PERCENTAGE - lp) / price) * 100) : 0;
        const sroi = lp > 0 ? Math.round(((price * TD_PERCENTAGE - lp) / lp) * 100) : 0;
        const patch = (typeof chPromoSpricePatch === 'function')
            ? chPromoSpricePatch(price)
            : { SPRICE: price, sprice: price, has_custom_sprice: true };
        row.update(Object.assign({}, patch, {
            SPRICE: price,
            SGPFT: sgpft,
            SROI: sroi,
            SPRICE_STATUS: 'applied',
            push_status: null,
        }));
    }

    function tdClearSpriceForSelected() {
        if (tdSelectedSkus.size === 0) { tdShowToast('Select SKUs first', 'error'); return; }
        if (!confirm(`Clear SPRICE for ${tdSelectedSkus.size} SKU(s)?`)) return;

        const items = [];
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d['(Child) sku'] != null ? String(d['(Child) sku']) : '';
            if (!sku || !tdSelectedSkus.has(sku)) return;
            if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
            else row.update({ SPRICE: null, SGPFT: null, SROI: null, has_custom_sprice: false });
            items.push({ row: row, sku: sku });
        });

        if (!items.length) return;

        const zeros = items.map(function(i) { return { sku: i.sku, sprice: 0 }; });
        $.ajax({
            url: "{{ route('topdawg.save.sprice') }}",
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', updates: zeros }
        }).always(function() {
            const fills = [];
            items.forEach(function(item) {
                const d = item.row.getData() || {};
                let price = (typeof window.chStdPriceForRow === 'function')
                    ? (Number(window.chStdPriceForRow(d)) || 0)
                    : 0;
                if (!(price > 0)) return;
                tdApplyRuleSpriceToRow(item.row, price);
                fills.push({ sku: item.sku, sprice: price });
            });
            if (fills.length) {
                tdSaveSpriceUpdates(fills, { clearFirst: false });
                tdShowToast('S PRC cleared, then Std prc vs dil saved on ' + fills.length + ' SKU(s)', 'success');
            } else {
                tdShowToast('Cleared SPRICE for ' + items.length + ' SKU(s)', 'success');
            }
        });
    }

    function applyUrlBadgeFilter() {
        const p = new URLSearchParams(window.location.search).get('badge');
        // Reset every badge-style filter (including the dropdown-backed Sold filter)
        // before honoring the URL deep-link, so only one filter is active afterwards.
        missingFilter = false;
        $('#sold-filter').val('all');
        if (p === 'missing') missingFilter = true;
        else if (p === 'zero_sold') $('#sold-filter').val('zero');
        else if (p === 'more_sold') $('#sold-filter').val('sold');
    }

    function setActiveBadges() {
        $('#missing-badge').toggleClass('active-filter', missingFilter);
        // Sold-badge active state is now derived from the #sold-filter dropdown value,
        // which is the single source of truth for the Sold filter.
        const sold = $('#sold-filter').val();
        $('#zero-sold-badge').toggleClass('active-filter', sold === 'zero');
        $('#more-sold-badge').toggleClass('active-filter', sold === 'sold');
    }

    function getSummaryRows() {
        if (!table) return [];
        const rows = table.getRows('active');
        const data = (rows && rows.length)
            ? rows.map(r => r.getData())
            : (table.getData() || []);
        return data.filter(r => !(r.Parent && String(r.Parent).toUpperCase().startsWith('PARENT')));
    }

    function updateSummary() {
        const data = getSummaryRows();
        let totalTdL30 = 0;
        let zeroSold = 0, moreSold = 0, missing = 0;
        data.forEach(row => {
            const tdL30 = parseInt(row['TD L30'], 10) || 0;
            totalTdL30 += tdL30;
            tdL30 === 0 ? zeroSold++ : moreSold++;

            const inv = parseFloat(row.INV) || 0;
            const nrReq = row.nr_req || 'REQ';
            const isMissing = row.Missing === 'M';
            if (isMissing && nrReq === 'REQ' && inv > 0) missing++;
        });

        // Sales / GPFT / GROI pinned to /topdawg/sales-dashboard (not filter-dependent).
        $('#total-rows-badge').text('Rows: ' + data.length.toLocaleString());
        $('#total-td-l30-badge').text('TD L30: ' + totalTdL30.toLocaleString());
        $('#total-sales-badge').text('Sales: $' + Number(TD_SALES_DASHBOARD_REVENUE).toFixed(2));
        $('#zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
        $('#more-sold-badge').text('> 0 Sold: ' + moreSold.toLocaleString());
        $('#gpft-pct-badge').text('GPFT: ' + Math.round(Number(TD_SALES_DASHBOARD_GPFT)) + '%');
        $('#groi-pct-badge').text('GROI: ' + Math.round(Number(TD_SALES_DASHBOARD_ROI)) + '%');
        $('#missing-badge').text('Missing L: ' + missing.toLocaleString());
        if (window.PriceGtLmpBadge && table) {
            PriceGtLmpBadge.update('#topdawg-price-gt-lmp-badge', table.getData(), 'topdawg', 'TD Price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#topdawg-price-lt80-lmp-badge', table.getData(), 'topdawg', 'TD Price');
                }
        }
        let blueTriangleCount = 0;
        (table ? table.getData() : []).forEach(function(row) {
            if (tdHasBlueTriangle(row)) blueTriangleCount++;
        });
        $('#topdawg-blue-triangle-badge').html(
            '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
        );
        if (typeof syncTdTriangleBadgeState === 'function') syncTdTriangleBadgeState();
    
        if (window.AnalyticsDilBadge) {
            var __dilRows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) ? allTableData
                : (typeof summaryDataCache !== 'undefined' && Array.isArray(summaryDataCache) && summaryDataCache.length) ? summaryDataCache
                : (typeof table !== 'undefined' && table && typeof table.getData === 'function') ? (function () { try { return table.getData() || []; } catch (e) { return []; } })()
                : (typeof tableData !== 'undefined' && Array.isArray(tableData) ? tableData : []);
            AnalyticsDilBadge.paintFromRows(__dilRows);
        }
    }

    function applyFilters() {
        if (!table) return;
        if (window.ParentExpand && ParentExpand.isExpanded()) {
            ParentExpand.beforeFilters(function(){ applyFilters(); });
            return;
        }
        table.clearFilter();

        const skuSearch = ($('#sku-search').val() || '').trim().toLowerCase();
        const parentSearch = ($('#parent-search').val() || '').trim().toLowerCase();
        if (skuSearch) {
            table.addFilter(function(data) {
                const sku = String(data['(Child) sku'] || data.sku || '').toLowerCase();
                return sku.indexOf(skuSearch) !== -1;
            });
        }
        if (parentSearch) {
            table.addFilter(function(data) {
                const parent = String(data.Parent || data.parent || '').toLowerCase();
                return parent.indexOf(parentSearch) !== -1;
            });
        }

        // Searching a published SKU should find it even when INV is 0 / NRL is REQ.
        const invF = (skuSearch || parentSearch) ? 'all' : $('#inventory-filter').val();
        if (invF === 'zero') table.addFilter('INV', '=', 0);
        if (invF === 'more') table.addFilter('INV', '>', 0);

        const nrl = (skuSearch || parentSearch) ? 'all' : $('#nrl-filter').val();
        if (nrl !== 'all') table.addFilter('nr_req', '=', nrl);

        const gpft = $('#gpft-filter').val();
        if (gpft !== 'all') {
            table.addFilter(data => {
                const g = parseFloat(data['GPFT%']) || 0;
                if (gpft === 'negative') return g < 0;
                if (gpft === '0-10') return g >= 0 && g < 10;
                if (gpft === '10-20') return g >= 10 && g < 20;
                if (gpft === '20-30') return g >= 20 && g < 30;
                if (gpft === '30plus') return g >= 30;
                return true;
            });
        }

        // GROI / ROI% bracket filter — buckets match the ROI% column's color thresholds
        // (red < 40, yellow 40–75, green 75–125, pink ≥ 125).
        const groi = $('#groi-filter').val();
        if (groi !== 'all') {
            table.addFilter(data => {
                const r = parseFloat(data['ROI%']) || 0;
                if (groi === 'lt40')    return r < 40;
                if (groi === '40-75')   return r >= 40  && r < 75;
                if (groi === '75-125')  return r >= 75  && r < 125;
                if (groi === '125plus') return r >= 125;
                return true;
            });
        }

        // DIL% bracket filter — three buckets after merging yellow into red:
        // red < 25, green 25–50, pink ≥ 50. Computes DIL live from INV + L30
        // so the filter always agrees with the cell-color formatter (no
        // rounding-drift surprises at bucket boundaries).
        const dil = $('#dil-filter').val();
        if (dil !== 'all') {
            table.addFilter(data => {
                const inv   = parseFloat(data.INV) || 0;
                const ovL30 = parseFloat(data.L30) || 0;
                if (inv <= 0) return dil === 'red'; // INV=0 falls into the lowest bucket
                const pct = (ovL30 / inv) * 100;
                if (dil === 'red')   return pct < 25;
                if (dil === 'green') return pct >= 25 && pct < 50;
                if (dil === 'pink')  return pct >= 50;
                return true;
            });
        }

        // Sold filter — driven by the #sold-filter dropdown (single source of truth).
        const soldFilter = $('#sold-filter').val();
        if (soldFilter === 'zero') {
            table.addFilter(data => (parseInt(data['TD L30'], 10) || 0) === 0);
        } else if (soldFilter === 'sold') {
            table.addFilter(data => (parseInt(data['TD L30'], 10) || 0) > 0);
        }
        if (missingFilter) table.addFilter(data => data.Missing === 'M' && data.nr_req === 'REQ' && (parseFloat(data.INV) || 0) > 0);
        if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
            table.addFilter(function(data) {
                return PriceGtLmpBadge.hasRedTriangle(data, 'TD Price');
            });
        }
        if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
            table.addFilter(function(data) {
                return PriceLt80LmpBadge.hasPurpleTriangle(data, 'TD Price');
            });
        }
        if (blueTriangleFilterActive) {
            table.addFilter(function(data) {
                return tdHasBlueTriangle(data);
            });
        }

        setActiveBadges();
        updateSummary();
    }

    if (window.PriceGtLmpBadge) {
        PriceGtLmpBadge.bind({
            badge: '#topdawg-price-gt-lmp-badge',
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
            badge: '#topdawg-price-lt80-lmp-badge',
            getActive: function() { return priceLt80LmpFilterActive; },
            onToggle: function(on) {
                priceLt80LmpFilterActive = on;
                if (on) blueTriangleFilterActive = false;
                applyFilters();
            }
        });
    }
    $('#topdawg-blue-triangle-badge').on('click', function() {
        blueTriangleFilterActive = !blueTriangleFilterActive;
        if (blueTriangleFilterActive) {
            priceGtLmpFilterActive = false;
            priceLt80LmpFilterActive = false;
        }
        applyFilters();
    });

    $(document).ready(function() {
        applyUrlBadgeFilter();

        table = new Tabulator('#topdawg-pricing-table', {
            ajaxURL: '/topdawg-data-json',
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [50, 100, 200, 500, 1000],
            initialSort: [{ column: 'TD L30', dir: 'desc' }],
            columns: [
                {
                    // Selection column — always visible. Checkboxes let the user
                    // pre-select SKUs before turning on Decrease / Increase / Same Price
                    // / Target ROI% / Target GPFT% (instead of having to toggle a mode
                    // first just to reveal the checkbox column).
                    title: '<div style="display:flex;align-items:center;justify-content:center;"><input type="checkbox" id="td-select-all" title="Select / clear all filtered SKUs"></div>',
                    field: '_select',
                    width: 50,
                    frozen: true,
                    headerSort: false,
                    visible: true,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'];
                        if (!sku) return '';
                        const safe = String(sku).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                        const checked = tdSelectedSkus.has(String(sku)) ? 'checked' : '';
                        return `<input type="checkbox" class="td-sku-chk" data-sku="${safe}" ${checked}>`;
                    }
                },
                { title: 'Parent', field: 'Parent', frozen: true, width: 150, visible: false },
                (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, frozen: true, headerSort: false }),
                { title: 'Image', field: 'image_path', width: 80, headerSort: false,
                    formatter: c => {
                        const v = c.getValue();
                        return v ? `<img src="${v}" alt="Product" style="width:50px;height:50px;object-fit:cover;">` : '';
                    }},
                { title: 'SKU', field: '(Child) sku', frozen: true, width: 250, headerFilter: 'input',
                    cssClass: 'text-primary fw-bold' },
                {
                    title: 'Links', field: 'links_column', frozen: true, width: 55, hozAlign: 'center', headerSort: false, visible: true,
                    tooltip: 'Double-click to add / edit links',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const buyerLink = d['B Link'] || '';
                        const sellerLink = d['S Link'] || '';
                        let html = '<div style="display:flex;flex-direction:column;gap:4px;align-items:center;">';
                        if (sellerLink) {
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> S</a>`;
                        }
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> B</a>`;
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        e.stopPropagation();
                        openTdEditLinksModal(cell.getRow());
                    }
                },
                { title: 'INV', field: 'INV', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'Dil', field: 'Dil', hozAlign: 'center', width: 50, sorter: 'number',
                    // DIL color buckets: red < 25, green 25–50, pink ≥ 50.
                    // (The old yellow band 16.7–25 was merged into red so the
                    // dropdown filter and cell color stay in lockstep.)
                    formatter: c => {
                        const row = c.getRow().getData();
                        const inv = parseFloat(row.INV) || 0;
                        const ovL30 = parseFloat(row.L30) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ovL30 / inv) * 100;
                        let color = '';
                        if (dil < 25) color = '#a00211';
                        else if (dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }},
                { title: 'TD L30', field: 'TD L30', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'TD L60', field: 'TD L60', hozAlign: 'center', width: 50, sorter: 'number', visible: false },
                { title: 'TD Stock', field: 'TD Stock', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => {
                        const inv = parseFloat(c.getRow().getData().INV) || 0;
                        const td = parseFloat(c.getValue()) || 0;
                        const diff = Math.abs(inv - td);
                        const color = diff > TD_MAP_TOLERANCE ? '#dc3545' : '#28a745';
                        return `<span style="color:${color};font-weight:600;">${td}</span>`;
                    }},
                { title: 'Missing L', field: 'Missing', hozAlign: 'center', width: 70,
                    formatter: c => c.getValue() === 'M'
                        ? '<span style="color:#dc3545;font-weight:bold;background:#ffe6e6;padding:2px 6px;border-radius:3px;">M</span>'
                        : '' },
                { title: 'NR/REQ', field: 'nr_req', hozAlign: 'center', width: 60, headerSort: false,
                    formatter: c => {
                        let value = c.getValue();
                        if (!value || String(value).trim() === '') value = 'REQ';
                        return `<select class="form-select form-select-sm nr-req-dropdown"
                            style="border:1px solid #ddd;text-align:center;cursor:pointer;padding:2px 4px;font-size:16px;width:50px;height:28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: (e) => e.stopPropagation() },
                { title: 'Std Prc', field: 'STANDARD_PRICE', hozAlign: 'center', width: 70, sorter: 'number',
                    headerTooltip: 'Standard Price (Std Prc) — same shared value as /amazon-tabulator-view. Editable; saves to all Sku Link LMP siblings. Dot vs TD Price.',
                    editor: 'input',
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent_summary || d.is_parent) return false;
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (d.is_parent_summary || d.is_parent) return '';
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const channelPrice = parseFloat(d['TD Price'] || d.price || 0) || 0;
                        const dot = tdStdPrcChangeDotHtml(std, channelPrice);

                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                            dot + ('$' + std.toFixed(2)) + (window.SpriceLmpCap && typeof SpriceLmpCap.reviewStdTriangleHtml === 'function' ? SpriceLmpCap.reviewStdTriangleHtml(d) : '') + '</span>';
                    }},
                { title: 'Price', field: 'TD Price', hozAlign: 'center', width: 70, sorter: 'number',
                    formatter: c => {
                        const v = parseFloat(c.getValue()) || 0;
                        const rowData = c.getRow().getData();
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(v, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(v, rowData.lmp_price || rowData.lmp || rowData.LMP) : '');
                        if (v === 0) {
                            return '<span style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left:4px;"></i></span>';
                        }
                        return `$${v.toFixed(2)}${lmpTri}${purpleTri}`;
                    }},
                {
                    title: 'Sales', field: 'Sales', hozAlign: 'center', width: 80, sorter: 'number',
                    tooltip: 'L30 order sales $ from topdawg_order_metrics (same source as sales-dashboard)',
                    formatter: c => {
                        const sales = parseFloat(c.getValue()) || 0;
                        if (sales <= 0) return '<span class="text-muted">-</span>';
                        return `<strong style="color:#198754;">$${Math.round(sales).toLocaleString()}</strong>`;
                    }
                },
                { title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'NPFT%', field: 'PFT %', hozAlign: 'center', width: 50, sorter: 'number', visible: false,
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'GROI%', field: 'ROI%', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'NROI%', field: 'NROI', hozAlign: 'center', width: 50, sorter: 'number',
                    headerTooltip: 'TopDawg has no Ads% — NROI% = GROI%.',
                    formatter: c => {
                        const percent = parseFloat(c.getRow().getData()['ROI%']);
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: 'SPRICE', field: 'SPRICE', hozAlign: 'center', width: 92, sorter: 'number',
                    editable: false,
                    headerTooltip: 'Not editable. Saved by the server at 4:30 AM and 8:30 PM IST: Std Prc − Age/Dil/B/0 Sold/CVR/Rev Disc, Min NPFT, LMP cap (same as Amazon), then never below A Price (Amz), then pushed to TopDawg (no ship). TopDawg queues each price for review (1–24h). The page does not need to be open. Blue triangle = S PRC ≠ Price. Red triangle = S PRC ≥ LMP.',
                    formatter: c => {
                        const rowData = c.getRow().getData();
                        if (tdIsParentRow(rowData)) return '';
                        let value = tdDisplayedSprice(rowData);
                        if (!(value > 0)) value = parseFloat(c.getValue() || 0);
                        const status = rowData.SPRICE_STATUS || '';
                        const live = parseFloat(rowData['TD Price']) || 0;
                        const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(rowData, value) : null;
                        const lmp = cap ? cap.lmp : (parseFloat(rowData.lmp_price || rowData.lmp || rowData.LMP) || 0);
                        let bg = '';
                        let tip = '';
                        if (status === 'pushing') { bg = 'background-color:#ffe5b4;'; tip = ' title="Pushing to TopDawg…"'; }
                        else if (status === 'pushed') { bg = 'background-color:#fff3cd;'; tip = ' title="Pushed — queued in TopDawg review"'; }
                        else if (status === 'failed') { bg = 'background-color:#f8d7da;'; tip = ' title="Push failed — see console / Laravel log"'; }
                        else if (status === 'applied' || value > 0) { bg = 'background-color:#d4edda;'; }
                        if (!(value > 0)) return '';
                        const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                        const formatted = '$' + value.toFixed(2);
                        const priceHtml = overLmp
                            ? `<strong${tip} style="color:#dc3545;${bg}padding:2px 6px;border-radius:3px;">${formatted}</strong>`
                            : `<strong${tip} style="${bg}padding:2px 6px;border-radius:3px;">${formatted}</strong>`;
                        const redTri = overLmp
                            ? '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="Saved S PRC ≥ LMP $'
                                + Number(lmp).toFixed(2) + '"></i>'
                            : '';
                        const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        const amzPrice = tdAmzPrice(rowData);
                        const amzLbl = (tdHasAmzCap(rowData) && amzPrice > 0)
                            ? '<span class="td-sprice-amz-lbl" style="font-size:9px;font-weight:700;color:#fd7e14;border:1px solid #fd7e14;border-radius:3px;padding:0 3px;" title="S PRC raised to Amazon $'
                                + amzPrice.toFixed(2) + '">Amz</span>'
                            : '';
                        return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${amzLbl}${redTri}${blueTri}</span>`;
                    }
                },
                {
                    title: 'SGPFT%', field: 'SGPFT', hozAlign: 'center', width: 55, sorter: 'number',
                    headerTooltip: 'GPFT% at S PRC (no ship): ((S PRC × margin − LP) / S PRC) × 100.',
                    formatter: c => {
                        const v = c.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        const percent = parseFloat(v);
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNPFT%', field: 'SNPFT', hozAlign: 'center', width: 55, sorter: 'number',
                    headerTooltip: 'TopDawg has no Ads% — SNPFT% = SGPFT%.',
                    formatter: c => {
                        const percent = parseFloat(c.getRow().getData().SGPFT);
                        if (isNaN(percent)) return '<span class="text-muted">-</span>';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SROI%', field: 'SROI', hozAlign: 'center', width: 55, sorter: 'number',
                    tooltip: 'GROI % at SPRICE (same formula as GROI%, no ship)',
                    formatter: c => {
                        const v = c.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        const percent = parseFloat(v);
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNROI%', field: 'SNROI', hozAlign: 'center', width: 55, sorter: 'number',
                    headerTooltip: 'TopDawg has no Ads% — SNROI% = SROI%.',
                    formatter: c => {
                        const percent = parseFloat(c.getRow().getData().SROI);
                        if (isNaN(percent)) return '<span class="text-muted">-</span>';
                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }
                },
                { title: 'Profit', field: 'Profit', hozAlign: 'center', width: 70, sorter: 'number', visible: false,
                    formatter: c => {
                        const v = parseFloat(c.getValue()) || 0;
                        const color = v >= 0 ? '#28a745' : '#a00211';
                        return `<span style="color:${color};font-weight:600;">$${v.toFixed(2)}</span>`;
                    }},
                { title: 'LP', field: 'LP_productmaster', hozAlign: 'center', width: 60, sorter: 'number', visible: false,
                    formatter: c => '$' + (parseFloat(c.getValue()) || 0).toFixed(2) },
                { title: 'Ship', field: 'Ship_productmaster', hozAlign: 'center', width: 60, sorter: 'number', visible: false,
                    formatter: c => '$' + (parseFloat(c.getValue()) || 0).toFixed(2) },
                { title: 'TDID', field: 'TDID', width: 120, visible: false },
                { title: 'State', field: 'listing_state', width: 70, visible: false },
            ],
            ajaxResponse: function(url, params, response) {
                const rows = Array.isArray(response) ? response : (response.data || []);
                allTableData = rows;
                if (window.ParentExpand) ParentExpand.captureDataset(rows);
                return rows;
            },
        });

        if (window.ParentExpand) {
            ParentExpand.configure({
                parentField: 'Parent',
                skuField: '(Child) sku',
                getTable: () => table,
                getDataset: () => allTableData,
                onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
            });
            ParentExpand.bind();
        }

        table.on('dataLoaded', function(data) {
            if (Array.isArray(data) && data.length) {
                // ParentExpand.expand() replaces the table data with a single parent's
                // children, which fires dataLoaded again. Without this guard allTableData would
                // be overwritten by that subset and later filters would run against only
                // those rows, leaving the table stuck on the expanded group.
                if (window.ParentExpand && ParentExpand.isExpanded()) {
                    setTimeout(function() {
                        applyTdColumnVisibilityMap(tdColumnVisibilityMap);
                        tdBuildColumnDropdown(tdColumnVisibilityMap);
                    }, 800);
                    return;
                }
                allTableData = data;
                if (window.ParentExpand) ParentExpand.captureDataset(data);
            }
            setTimeout(function() {
                applyTdColumnVisibilityMap(tdColumnVisibilityMap);
                tdBuildColumnDropdown(tdColumnVisibilityMap);
            }, 800);
            setTimeout(function() {
                applyFilters();
                updateSummary();
            }, 100);
        });
        table.on('renderComplete', function() {
            setTimeout(updateSummary, 100);
        });
        table.on('dataFiltered', updateSummary);

        // ─── Column visibility ───────────────────────────────────────────
        // Persists in the shared `channel_tabulator_column_settings` DB table
        // (same store eBay1/eBay2/eBay3/Amazon/mfrg use) via the shared
        // /tabulator-column-visibility endpoint. The `channel` param is what
        // makes the row unique — every page that uses this endpoint owns one
        // channel string and one row in the table.
        const TD_TABULATOR_COLUMN_CHANNEL = 'topdawg_tabulator';
        const TD_TABULATOR_COLUMN_VISIBILITY_URL = '{{ url('/tabulator-column-visibility') }}';
        const TD_COLUMN_VIS_KEY = 'topdawg_tabulator_col_vis_v1';
        let tdColumnVisibilityMap = {};

        function tdColumnField(col) {
            if (!col) return '';
            const def = (typeof col.getDefinition === 'function') ? (col.getDefinition() || {}) : {};
            return def.field || (typeof col.getField === 'function' ? col.getField() : '') || '';
        }
        function tdVisibilityIsOn(v) {
            return v === true || v === 1 || v === '1' || v === 'true';
        }
        function readTdColumnVisibilityLocal() {
            try {
                const raw = localStorage.getItem(TD_COLUMN_VIS_KEY);
                const parsed = raw ? JSON.parse(raw) : {};
                return (parsed && typeof parsed === 'object') ? parsed : {};
            } catch (e) {
                return {};
            }
        }
        function writeTdColumnVisibilityLocal(map) {
            try { localStorage.setItem(TD_COLUMN_VIS_KEY, JSON.stringify(map || {})); } catch (e) {}
        }
        function applyTdColumnVisibilityMap(map) {
            if (!table || !map || typeof map !== 'object') return;
            tdColumnVisibilityMap = map;
            table.getColumns().forEach(function(col) {
                const field = tdColumnField(col);
                if (!field || field === '_select') return;
                if (!Object.prototype.hasOwnProperty.call(map, field)) return;
                if (tdVisibilityIsOn(map[field])) col.show();
                else col.hide();
            });
        }
        function collectTdColumnVisibility() {
            const visibility = {};
            if (!table) return visibility;
            table.getColumns().forEach(function(col) {
                const field = tdColumnField(col);
                if (!field || field === '_select') return;
                visibility[field] = !!col.isVisible();
            });
            return visibility;
        }

        function tdBuildColumnDropdown(savedVisibility) {
            if (window.AnalyticsColVis) {
                window.AnalyticsColVis.install({
                    getTable: function() { return table; },
                    menuId: 'column-dropdown-menu',
                    storageKey: 'topdawg_col_cats_v1',
                    skipFields: ['_select'],
                    onSave: function() {
                        if (typeof tdSaveColumnVisibilityToServer === 'function') tdSaveColumnVisibilityToServer();
                    }
                });
                window.AnalyticsColVis.rebuild(savedVisibility || tdColumnVisibilityMap || null);
                return;
            }
            let html = '';
            const map = (savedVisibility && typeof savedVisibility === 'object')
                ? savedVisibility
                : tdColumnVisibilityMap;
            table.getColumns().forEach(col => {
                const field = tdColumnField(col);
                const title = col.getDefinition().title;
                if (field && field !== '_select' && title) {
                    const safeLabel = String(title).replace(/<[^>]*>/g, '').trim() || field;
                    const isVisible = Object.prototype.hasOwnProperty.call(map, field)
                        ? tdVisibilityIsOn(map[field])
                        : col.isVisible();
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="td-column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                        ${safeLabel}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        function tdSaveColumnVisibilityToServer() {
            const visibility = collectTdColumnVisibility();
            tdColumnVisibilityMap = visibility;
            writeTdColumnVisibilityLocal(visibility);
            fetch(TD_TABULATOR_COLUMN_VISIBILITY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    channel: TD_TABULATOR_COLUMN_CHANNEL,
                    visibility: visibility
                })
            }).catch(err => console.error('Error saving TopDawg column visibility:', err));
        }

        function tdApplyColumnVisibilityFromServer() {
            const localMap = readTdColumnVisibilityLocal();
            if (localMap && Object.keys(localMap).length) {
                applyTdColumnVisibilityMap(localMap);
                tdBuildColumnDropdown(localMap);
            }
            fetch(TD_TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TD_TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(r => r.json())
            .then(savedVisibility => {
                if (savedVisibility && typeof savedVisibility === 'object' && Object.keys(savedVisibility).length > 0) {
                    const merged = Object.assign({}, localMap, savedVisibility);
                    applyTdColumnVisibilityMap(merged);
                    writeTdColumnVisibilityLocal(merged);
                    tdBuildColumnDropdown(merged);
                }
            })
            .catch(err => console.error('Error loading TopDawg column visibility:', err));
        }

        table.on('tableBuilt', function() {
            tdBuildColumnDropdown();
            tdApplyColumnVisibilityFromServer();
        });

        // Per-column checkbox toggle — save instantly after every change.
        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.classList.contains('td-column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) {
                    e.target.checked ? col.show() : col.hide();
                    tdSaveColumnVisibilityToServer();
                }
            }
        });

        // "Show All" — un-hide every data column and persist.
        document.getElementById('show-all-columns-btn').addEventListener('click', function() {
            table.getColumns().forEach(col => {
                if (col.getField() && col.getField() !== '_select') col.show();
            });
            tdSaveColumnVisibilityToServer();
            tdBuildColumnDropdown(tdColumnVisibilityMap);
        });

        // Inline SPRICE edit — same UX as /purchasing-power-pricing. Live-recompute
        // SGPFT / SROI from the new SPRICE (LP only — no ship, same as sales-dashboard),
        // then auto-save via the existing
        // /topdawg-save-sprice endpoint. Empty value → null (clear SPRICE).
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const d = row.getData();
            const value = cell.getValue();

            if (field === 'STANDARD_PRICE') {
                const sku = d['(Child) sku'] || d.sku || d.SKU;
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
                        applyTdStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                        const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                        tdShowToast(n > 1
                            ? ('Std Prc saved for ' + n + ' linked SKUs')
                            : 'Std Prc saved', 'success');
                    },
                    error: function() {
                        tdShowToast('Failed to save Std Prc', 'error');
                    }
                });
                return;
            }

            // SPRICE is not editable — auto-saved from Sprc Dil (same as TikTok).
        });

        $('#inventory-filter, #nrl-filter, #gpft-filter, #groi-filter, #dil-filter, #sold-filter').on('change', function() {
            // Keep badge "active-filter" styling in sync when the Sold dropdown changes
            // directly (not via badge click). Other handlers call setActiveBadges() themselves.
            setActiveBadges();
            applyFilters();
        });
        $('#sku-search, #parent-search').on('keyup input', function(e) {
            if (e.type === 'keyup' && e.key === 'Enter') {
                e.preventDefault();
            }
            applyFilters();
        });
        $('#td-search-btn').on('click', function() {
            applyFilters();
        });

        // Sold badges just toggle the #sold-filter dropdown so the dropdown stays the
        // single source of truth (mirrors Amazon tabulator). Click again to clear.
        $('#zero-sold-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            setActiveBadges();
            applyFilters();
        });
        $('#more-sold-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            setActiveBadges();
            applyFilters();
        });
        $('#missing-badge').on('click', function() { missingFilter = !missingFilter; applyFilters(); });

        $('#export-btn').on('click', () => table.download('csv', 'topdawg_pricing.csv'));

        // ─── Pricing-mode toggles ─────────────────────────────────────────
        $('#discount-type-select').on('change', function() { tdSyncDiscountInputUi(); });

        // Cycle pricing modes on a single button:
        //   Off → Decrease → Increase → Same Price → Off
        // Each click advances exactly one step. When we cycle BACK to Off, the
        // selected SKUs are cleared (same behavior as the old per-button toggle
        // that cleared on turn-off) — but moving between modes keeps the selection
        // intact so users can re-apply different math to the same set of SKUs.
        $('#td-price-mode-btn').on('click', function() {
            if (!tdDecreaseModeActive && !tdIncreaseModeActive && !tdSamePriceModeActive) {
                tdDecreaseModeActive = true;
            } else if (tdDecreaseModeActive) {
                tdDecreaseModeActive = false;
                tdIncreaseModeActive = true;
            } else if (tdIncreaseModeActive) {
                tdIncreaseModeActive = false;
                tdSamePriceModeActive = true;
            } else {
                tdSamePriceModeActive = false;
                tdSelectedSkus.clear();
            }
            tdShowSelectColumn(true);
            tdUpdateSelectedCount();
            tdSyncPriceModeUi();
            tdSyncDiscountInputUi();
        });

        // ─── Selection handlers ───────────────────────────────────────────
        // Select-all is scoped to the *current pagination page* (not the entire
        // filtered set). SKUs that were selected on other pages stay selected,
        // so you can page-by-page accumulate a selection without losing rows
        // when you move forward / backward.
        $(document).on('change', '#td-select-all', function() {
            const checked = $(this).prop('checked');
            const rows = tdGetCurrentPageRows();
            rows.forEach(r => {
                const sku = String(r.getData()['(Child) sku'] || '');
                if (!sku) return;
                if (checked) tdSelectedSkus.add(sku); else tdSelectedSkus.delete(sku);
            });
            $('.td-sku-chk').each(function() {
                const sku = String($(this).attr('data-sku'));
                $(this).prop('checked', tdSelectedSkus.has(sku));
            });
            tdUpdateSelectedCount();
        });

        $(document).on('change', '.td-sku-chk', function() {
            const sku = String($(this).attr('data-sku'));
            if (!sku) return;
            if ($(this).prop('checked')) tdSelectedSkus.add(sku); else tdSelectedSkus.delete(sku);
            tdUpdateSelectedCount();
            tdUpdateSelectAllHeaderCheckbox();
        });

        // ─── Apply / Clear handlers ───────────────────────────────────────
        $('#apply-discount-btn').on('click', tdApplyDiscount);
        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) tdApplyDiscount();
        });
        $('#clear-sprice-btn, #clear-sprice-selected-btn').on('click', tdClearSpriceForSelected);

        table.on('renderComplete', tdUpdateSelectAllHeaderCheckbox);

        // ---- Edit B/S Links (double-click on Links cell) ----
        let tdEditLinksRow = null;
        window.openTdEditLinksModal = function(row) {
            if (!row) return;
            tdEditLinksRow = row;
            const d = row.getData();
            $('#tdEditLinksSku').val(d['(Child) sku']);
            $('#tdEditLinksSkuDisplay').text(d['(Child) sku']);
            $('#tdEditSellerLink').val(d['S Link'] || '');
            $('#tdEditBuyerLink').val(d['B Link'] || '');
            $('#tdEditLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('tdEditLinksModal')).show();
        };

        function tdNotify(msg, type) {
            if (window.toastr) { toastr[type === 'error' ? 'error' : 'success'](msg); return; }
            let c = document.getElementById('tdToastContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'tdToastContainer';
                c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(c);
            }
            const t = document.createElement('div');
            t.style.cssText = 'min-width:220px;max-width:340px;color:#fff;background:' + (type === 'error' ? '#dc3545' : '#198754') + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;opacity:0;transition:opacity .25s ease;';
            t.textContent = msg;
            c.appendChild(t);
            requestAnimationFrame(function() { t.style.opacity = '1'; });
            setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2600);
        }

        $(document).on('click', '#tdSaveLinksBtn', function() {
            const sku = $('#tdEditLinksSku').val();
            const sellerLink = $('#tdEditSellerLink').val().trim();
            const buyerLink = $('#tdEditBuyerLink').val().trim();
            const $err = $('#tdEditLinksError');
            $err.hide().text('');
            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ url("/topdawg-save-links") }}',
                method: 'POST',
                data: { sku: sku, seller_link: sellerLink, buyer_link: buyerLink, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (tdEditLinksRow) {
                        tdEditLinksRow.update({ 'S Link': res.seller_link || '', 'B Link': res.buyer_link || '' })
                            .then(function() { tdEditLinksRow.reformat(); })
                            .catch(function() { tdEditLinksRow.reformat(); });
                    }
                    tdNotify(sku + ': links saved', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('tdEditLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });
    });
</script>
@endsection
