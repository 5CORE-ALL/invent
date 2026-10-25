@extends('layouts.vertical', ['title' => 'Purchasing Power - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }

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
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0px !important; }
        .tabulator-paginator label { margin-right: 5px; }
        .pp-sprice-amz-lbl {
            color: #fd7e14;
            font-weight: 800;
            font-size: 10px;
            line-height: 1;
            margin-left: 3px;
            cursor: help;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'purchasing_power'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'purchasing_power'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="{{ asset('js/tabulator-sort-defaults.js') }}?v={{ file_exists(public_path('js/tabulator-sort-defaults.js')) ? filemtime(public_path('js/tabulator-sort-defaults.js')) : '1' }}"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Purchasing Power - Analytics',
        'sub_title'  => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <div class="d-flex flex-column gap-1" style="width: auto;" title="CVR = PP L30 ÷ OV L30">
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All DIL%</option>
                        <option value="red">Red (&lt;25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>
                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'buttons', 'ebaySprcDilChannel' => 'purchasing_power'])
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'purchasing_power'])
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary ({{ $ppPercentage }}% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge fs-6 p-2" id="pp-rows-badge"
                            style="background-color:#343a40;color:#fff;font-weight:700;"
                            title="Rows shown (SKUs, parent rows excluded). When a filter is on it reads shown / total.">Rows: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color:black;font-weight:bold;display:none;">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color:black;font-weight:bold;" title="Last 30 days actual order revenue — same MCM OR11 lines as /purchasing-power-sales L30 Sales.">Total Sales: $0</span>
                        <span class="badge fs-6 p-2" id="gpft-pct-badge"
                              style="background:#6f42c1;color:#fff;font-weight:bold;"
                              title="Weighted Gross Profit %: (Σ Profit ÷ Σ Sales L30) × 100. Matches /all-marketplace-master Gprofit% formula and /purchasing-power-sales GPFT % (rev) badge.">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color:white;font-weight:bold;" title="Weighted GROI% = (Σ Profit ÷ Σ COGS) × 100.">GROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color:white;font-weight:bold;cursor:pointer;" title="Click to filter 0 sold">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color:#28a745;color:white;font-weight:bold;cursor:pointer;">&gt; 0 Sold</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-badge" style="color:black;font-weight:bold;display:none;">DIL%: 0%</span>
                        @include('partials.analytics-dil-badge', ['dilChannel' => 'purchasingpower'])
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color:black;font-weight:bold;display:none;">COGS: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color:white;font-weight:bold;cursor:pointer;">&lt; Amz</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color:#28a745;color:white;font-weight:bold;cursor:pointer;">&gt; Amz</span>
                        <span class="badge fs-6 p-2" id="pp-blue-triangle-badge"
                            style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                            title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        <span class="badge fs-6 p-2" id="pp-amz-cap-badge"
                            style="background-color:#fd7e14;color:#fff;font-weight:700;cursor:pointer;"
                            title="Amz: Dil S PRC was below A Price and was raised to Amz. Click to filter. Click again to clear."
                            aria-label="S PRC raised to Amazon">Amz 0</span>
                        <span class="badge fs-6 p-2" id="pp-clearance-badge"
                            style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
                            title="C = Clearance SKUs (Inv days clearance = YES). Click to filter. Click again to clear."
                            aria-label="Clearance SKUs">C 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'purchasingpower-price-gt-lmp-badge', 'pglChannelKey' => 'purchasingpower', 'pglPriceField' => 'PP Price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'purchasingpower-price-lt80-lmp-badge', 'pltChannelKey' => 'purchasingpower', 'pltPriceField' => 'PP Price'])
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display:none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="pp-table-wrapper" style="height:calc(100vh - 200px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                    </div>
                    <div id="pp-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'purchasing_power'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'modals', 'ebaySprcDilChannel' => 'purchasing_power'])
@endsection

@section('script-bottom')
    @include('partials.lazy-chart-js')
<script>
    // S PRC is saved + pushed by the server (purchasing-power:sprc-dil-auto-push). The page only shows it.
    window.CH_STD_SERVER_OWNED = true;
    // The SNROI% column here is (S PRC x take-home - Ship BB - LP) / LP with no Ads%, so the SNROI Cap judges the same number.
    window.CH_PROMO_ADS_FN = function() { return 0; };
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'purchasing_power'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'script', 'ebaySprcDilChannel' => 'purchasing_power'])
    @include('partials.channel-clearance-badge', ['chClrPart' => 'script'])
    @include('partials.temu-rule-hover', ['temuRuleHoverKind' => 'purchasing_power', 'temuRuleLiveLabel' => 'PP Price'])
    const COLUMN_VIS_KEY = "pp_tabulator_column_visibility";
    let table = null;
    let allTableData = [];
    let selectedSkus = new Set();
    const PP_MARGIN = {{ $ppPercentage }} / 100;

    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function ppStdPrcChangeDotMeta(stdPrc, comparePrice) {
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
        return null;
    }

    function ppStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = ppStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyPpStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
            const rowSku = String(d['(Child) sku'] || d.sku || d.SKU || '').trim();
            if (!rowSku) return;
            const rowKey = rowSku.toUpperCase();
            const linked = Array.isArray(d.linked_lmp_skus) ? d.linked_lmp_skus : [];
            const inGroup = appliedSet.has(rowKey)
                || linked.some(function(s) { return String(s || '').trim().toUpperCase() === target; })
                || (target && rowKey === target);
            if (!inGroup) return;
            r.update({ STANDARD_PRICE: std, standard_price: std });
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
        applyPpStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT'))).forEach(r => {
                isChecked ? selectedSkus.add(r['(Child) sku']) : selectedSkus.delete(r['(Child) sku']);
            });
            $('.sku-select-checkbox').each(function() { $(this).prop('checked', selectedSkus.has($(this).data('sku'))); });
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            $(this).prop('checked') ? selectedSkus.add(sku) : selectedSkus.delete(sku);
            updateSelectedCount(); updateSelectAllCheckbox();
        });

        $('#clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

        let soldFilter = 'all';
        let lessAmzFilterActive = false, moreAmzFilterActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;
        let blueTriangleFilterActive = false;
        let amzCapFilterActive = false;
        let clearanceFilterActive = false;

        function ppIsParentRow(d) {
            return !!(d && (d.is_parent_summary || d.is_parent || (d.Parent && String(d.Parent).toUpperCase().indexOf('PARENT') === 0)));
        }
        function isPpListed(rowData) {
            if (!rowData || ppIsParentRow(rowData)) return false;
            if (rowData.is_pp_inactive === true) return false;
            const price = parseFloat(rowData['PP Price']) || 0;
            if (price > 0) return true;
            if (typeof rowData.is_missing_pp !== 'undefined') {
                return rowData.is_missing_pp === false || rowData.is_missing_pp === 0 || rowData.is_missing_pp === 'false';
            }
            return false;
        }
        function isPpInactive(rowData) {
            if (!rowData) return false;
            if (rowData.is_pp_inactive === true) return true;
            const flag = String(rowData.live_inactive || rowData.listing_status || '').toLowerCase();
            return ['inactive', 'offline', 'ended', 'disabled'].indexOf(flag) !== -1;
        }
        function ppAmazonPrice(data) {
            return Math.round((Number(data && (data['A Price'] != null ? data['A Price'] : (data.a_price || data.amazon_price))) || 0) * 100) / 100;
        }
        // Set by the server job when the Amz rule raised S PRC to A Price (AMZ_CAPPED on the saved row).
        // Amz rule: S PRC is never below A Price. A saved S PRC under A Price (the server job has not caught up yet)
        // is shown as A Price with the Amz cap applied. The server saves the same value on its next run.
        function ppSavedSprice(data) {
            if (!data || ppIsParentRow(data)) return 0;
            if (typeof chPromoTableSprice === 'function') {
                const saved = Number(chPromoTableSprice(data)) || 0;
                if (saved > 0) return saved;
            }
            return parseFloat(data.SPRICE) || 0;
        }
        function ppHasAmzCap(data) {
            if (ppIsParentRow(data)) return false;
            const amz = ppAmazonPrice(data);
            const saved = ppSavedSprice(data);
            if (!(amz > 0) || !(saved > 0)) return false;
            // S PRC lower than A Price: the cap applies.
            if (saved < amz - 0.015) return true;
            // Server already raised it: flag set and S PRC = A Price.
            const flag = data && data.AMZ_CAPPED;
            return (flag === true || flag === 1 || flag === '1' || flag === 'true') && Math.abs(saved - amz) <= 0.015;
        }
        function ppDilOn() {
            return typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() === 'dil';
        }
        /** Take-home % for the Dil price: the page's Purchasing Power margin. */
        function ppMarginOf() { return PP_MARGIN > 0 ? PP_MARGIN : 0.65; }
        /** Sprc Dil price before the A Price floor (no Std Prc maximum): Dil slab + Diff points. 0 when the rule gives none. */
        function ppDilRawSprice(data) {
            if (typeof ebayDilGroiMetaForRow !== 'function') return 0;
            const meta = ebayDilGroiMetaForRow(data);
            const n = meta ? Number(meta.rawSprc > 0 ? meta.rawSprc : meta.sprc) : 0;
            return n > 0 ? Math.round(n * 100) / 100 : 0;
        }
        /** Sprc Dil, no LMP (Purchasing Power has no LMP data) and no Std Prc maximum: the Dil price as is (the A Price floor runs after). */
        function ppCapDilSprice(data, raw) {
            const out = Math.round((parseFloat(raw) || 0) * 100) / 100;
            return out > 0 ? out : 0;
        }
        /** The rule price of the ON switch with the A Price floor (no Std max), live. 0 while rules load. */
        function ppLiveRuleSprice(data) {
            if (!data || ppIsParentRow(data)) return 0;
            try {
                let out = 0;
                if (ppDilOn()) {
                    if (window._ebayDilRulesLoaded !== true) return 0;
                    const raw = ppDilRawSprice(data);
                    out = raw > 0 ? ppCapDilSprice(data, raw) : 0;
                } else {
                    if (window.__chStdSettled !== true) return 0;
                    out = (typeof window.chStdPriceForRow === 'function') ? (Number(window.chStdPriceForRow(data)) || 0) : 0;
                    out = out > 0 ? Math.round(out * 100) / 100 : 0;
                }
                if (!(out > 0)) return 0;
                const amz = ppAmazonPrice(data);
                return (amz > 0 && out < amz - 0.0001) ? amz : out;
            } catch (e) { /* rules not loaded yet */ }
            return 0;
        }
        window.ppLiveRuleSprice = ppLiveRuleSprice;
        /**
         * The LIVE rule price with its caps, like Amazon, so a switch or rule edit repaints at once. The server saves and pushes
         * the same price right after. Falls back to the saved S PRC (with the A Price floor) while the rules load.
         */
        function ppDisplayedSprice(data) {
            const live = ppLiveRuleSprice(data);
            if (live > 0) return live;
            const saved = ppSavedSprice(data);
            if (!(saved > 0)) return 0;
            const amz = ppAmazonPrice(data);
            return (amz > 0 && saved < amz - 0.015) ? amz : saved;
        }
        // Names the shared rule hover card uses (partials/temu-rule-hover, kind 'purchasing_power').
        function ppShownSprice(data) { return ppDisplayedSprice(data); }
        function ppAmzPrice(data) { return ppAmazonPrice(data); }
        /** The Diff rule setting saved in the Sprc Dil modal (default: above 20% adds +10). */
        function ppDiffCfgNow() {
            const c = (typeof window.ebayDiffAdjCfg === 'function') ? window.ebayDiffAdjCfg() : null;
            return { gt_pct: c && isFinite(Number(c.gt_pct)) ? Number(c.gt_pct) : 20, points: c && isFinite(Number(c.points)) ? Number(c.points) : 10 };
        }
        /** Std Diff % = (Std Prc - PP Price) / PP Price x 100, same as /amazon-tabulator-view. null when either is missing. */
        function ppStdPrcDiffPct(rowData) {
            if (!rowData || ppIsParentRow(rowData)) return null;
            const std = parseFloat(rowData.STANDARD_PRICE) || 0;
            const price = parseFloat(rowData['PP Price']) || 0;
            if (!(std > 0) || !(price > 0)) return null;
            return ((std - price) / price) * 100;
        }
        /** Std Diff cell: pink above the Sprc Dil Diff threshold (default 20%), red < -5%, else green. Styles the cell element itself. */
        function ppRenderStdPrcDiff(el, rowData) {
            const pct = ppStdPrcDiffPct(rowData);
            let html = '';
            let bg = '';
            let color = '';
            if (pct !== null) {
                if (pct > ppDiffCfgNow().gt_pct) { bg = '#ffb6d9'; color = '#000'; }
                else if (pct < -5) { bg = '#dc3545'; color = '#fff'; }
                else { bg = '#28a745'; color = '#fff'; }
                html = '<span style="font-weight:600;">' + pct.toFixed(1) + '%</span>';
            }
            if (el) {
                el.style.backgroundColor = bg;
                el.style.color = color;
            }
            return html;
        }
        // The Sprc Dil Diff rule reads Std Prc and the Purchasing Power listing price from these two columns.
        window.ebayDiffStdPrice = function(d) { return parseFloat(d && d.STANDARD_PRICE) || 0; };
        window.ebayDiffLivePrice = function(d) { return parseFloat(d && d['PP Price']) || 0; };
        /** Repaint every S PRC dependent cell from the rule now ON (switch, or a rule edit). No row writes, no scroll jump. */
        function ppRefreshSpriceCells() {
            if (typeof table === 'undefined' || !table || typeof table.getRows !== 'function') return;
            try {
                (table.getRows('visible') || []).forEach(function(row) {
                    try { if (row && typeof row.reformat === 'function') row.reformat(); } catch (e) { /* ignore */ }
                });
            } catch (e) { /* ignore */ }
            if (typeof updateSummary === 'function') {
                try { updateSummary(); } catch (e2) { /* ignore */ }
            }
        }
        window.ppRefreshSpriceCells = ppRefreshSpriceCells;
        // The rule hover card (partials/temu-rule-hover) lives outside this closure and reads these by name.
        window.ppDilOn = ppDilOn;
        window.ppShownSprice = ppShownSprice;
        window.ppAmzPrice = ppAmzPrice;
        window.ppMarginOf = ppMarginOf;
        window.ppStdPrcDiffPct = ppStdPrcDiffPct;
        // Switch Sprc Dil <-> Std prc vs dil: repaint at once from the ON rule. The server re-saves S PRC right after the switch is saved.
        window.spriceOnRuleSwitched = function() {
            ppRefreshSpriceCells();
            if (typeof showToast === 'function') showToast('S PRC now follows ' + (ppDilOn() ? 'Sprc Dil' : 'Std prc vs dil'), 'info');
        };
        // A Sprc Dil edit (slab, Diff) was saved: the cells repaint from it; the server saves the same price right after.
        window.ebayOnDilRulesSaved = function() { ppRefreshSpriceCells(); };
        // Rules and the clearance list finish loading after the first paint: repaint once so no S PRC stays blank.
        $(document).on('chStd:settled', function() { ppRefreshSpriceCells(); });
        window.ppDisplayedSprice = ppDisplayedSprice;
        function ppSpriceMetrics(data, sprice) {
            const price = (sprice != null && sprice !== '') ? Number(sprice) : ppDisplayedSprice(data);
            if (!(price > 0) || !data || ppIsParentRow(data)) return { sgpft: 0, sroi: 0 };
            const lp = Number(data.LP_productmaster != null ? data.LP_productmaster : data.lp) || 0;
            const ship = Number(data.Ship_productmaster != null ? data.Ship_productmaster : data.ship) || 0;
            const sgpft = Math.round(((price * PP_MARGIN - lp - ship) / price) * 10000) / 100;
            const sroi = lp > 0 ? Math.round(((price * PP_MARGIN - lp - ship) / lp) * 10000) / 100 : 0;
            return { sgpft: sgpft, sroi: sroi };
        }
        function ppRowSpriceForAlert(data) {
            return ppDisplayedSprice(data);
        }
        function ppHasBlueTriangle(data) {
            if (ppIsParentRow(data) || !isPpListed(data)) return false;
            if (!(parseFloat(data && data.INV) > 0)) return false;
            const sprice = ppRowSpriceForAlert(data);
            const price = parseFloat(data && data['PP Price']) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        window.ppHasBlueTriangle = ppHasBlueTriangle;
    function syncPpTriangleBadgeState() {
            $('#pp-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
            $('#pp-amz-cap-badge').css({
                outline: amzCapFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: amzCapFilterActive ? '2px' : ''
            });
            $('#pp-clearance-badge').css({
                outline: clearanceFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: clearanceFilterActive ? '2px' : ''
            });
        }

        $('#zero-sold-count-badge').on('click', function() {
            soldFilter = soldFilter === 'zero' ? 'all' : 'zero';
            applyFilters();
        });
        $('#more-sold-count-badge').on('click', function() {
            soldFilter = soldFilter === 'sold' ? 'all' : 'sold';
            applyFilters();
        });
        $('#less-amz-badge').on('click', function() { lessAmzFilterActive = !lessAmzFilterActive; moreAmzFilterActive = false; applyFilters(); });
        $('#more-amz-badge').on('click', function() { moreAmzFilterActive = !moreAmzFilterActive; lessAmzFilterActive = false; applyFilters(); });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            const filteredSkus = new Set(table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT'))).map(r => r['(Child) sku']).filter(s => s));
            $('#select-all-checkbox').prop('checked', filteredSkus.size > 0 && [...filteredSkus].every(s => selectedSkus.has(s)));
        }

        function roundToRetailPrice(price) { 
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            return Math.ceil(price) - 0.01; 
        }

        function saveSpriceUpdates(updates, opts) {
            opts = opts || {};
            if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst !== false) {
                chPromoBatchClearThenSave(updates, function(next) {
                    saveSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
                }, {
                    wipeFn: function(zeros) {
                        return $.ajax({
                            url: '/pp-save-sprice-batch',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            data: { updates: zeros }
                        });
                    }
                });
                return;
            }
            $.ajax({
                url: '/pp-save-sprice-batch',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates },
                success: function(response) {
                    if (response.success) {
                        console.log('PP SPRICE saved:', response.updated, 'records');
                    }
                },
                error: function(xhr) {
                    showToast('Error saving SPRICE: ' + (xhr.responseJSON?.error || 'Unknown'), 'error');
                }
            });
        }

        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) { showToast('Select SKUs first', 'error'); return; }
            if (!confirm(`Clear SPRICE for ${selectedSkus.size} SKU(s)?`)) return;

            let clearedCount = 0;
            const updates = [];
            table.getRows().forEach(row => {
                const sku = row.getData()['(Child) sku'];
                if (!selectedSkus.has(sku)) return;
                row.update({ SPRICE: 0, SGPFT: 0, SPFT: 0, SROI: 0 });
                updates.push({ sku, sprice: 0 });
                clearedCount++;
            });
            if (updates.length) saveSpriceUpdates(updates);
            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
        }

        // Initialize Tabulator
        table = new Tabulator('#pp-table', {
            ajaxURL: '/pp-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            columnCalcs: 'both',
            langs: { default: { pagination: { page_size: 'SKU Count' } } },
            initialSort: [{ column: 'PP L30', dir: 'desc' }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT'))
                    row.getElement().style.backgroundColor = '#fffef2';
            },
            columns: [
                { title: 'Parent', field: 'Parent', sorter: "alphanum", sorterParams: { alignEmptyValues: "bottom" }, headerFilter: 'input', headerFilterPlaceholder: 'Search Parent...', cssClass: 'text-primary', tooltip: true, frozen: true, width: 150, visible: false },
                {
                    title: 'Image', field: 'image_path', headerSort: false, width: 80,
                    formatter: function(cell) {
                        const v = cell.getValue();
                        return v ? `<img src="${v}" style="width:50px;height:50px;object-fit:cover;">` : '';
                    }
                },
                {
                    title: 'SKU', field: '(Child) sku', sorter: "alphanum", sorterParams: { alignEmptyValues: "bottom" }, headerFilter: 'input', headerFilterPlaceholder: 'Search SKU...',
                    cssClass: 'text-primary fw-bold', tooltip: true, frozen: true, width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        return `<span>${sku}</span><i class="fa fa-copy text-secondary copy-sku-btn" style="cursor:pointer;margin-left:8px;font-size:14px;" data-sku="${sku}" title="Copy SKU"></i>`;
                    }
                },
                { title: 'INV',  field: 'INV',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                {
                    title: 'Dil', field: 'PP Dil%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const inv = parseFloat(d.INV) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (parseFloat(d['L30']) || 0) / inv * 100;
                        const color = dil < 25 ? '#dc3545' : dil < 50 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                { title: 'PP L30',   field: 'PP L30',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'PP Stock', field: 'PP INV',  hozAlign: 'center', width: 60, sorter: 'number',
                    headerTooltip: 'Purchasing Power stock from MCM OF21 (purchasing_power_products.stock). Hover a cell for its source.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const v = parseInt(cell.getValue()) || 0;
                        const source = d['PP Stock Source'] || 'unknown';
                        const color = v === 0 ? '#a00211' : v < 5 ? '#ffc107' : '#28a745';
                        return `<span title="Source: ${source}" style="color:${color};font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: 'NR/REQ', field: 'nr_req', hozAlign: 'center', headerSort: false, width: 60,
                    formatter: function(cell) {
                        const v = cell.getValue() || 'REQ';
                        return `<select class="form-select form-select-sm nr-req-dropdown" style="border:1px solid #ddd;text-align:center;cursor:pointer;padding:2px 4px;font-size:16px;width:50px;height:28px;">
                            <option value="REQ" ${v === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR"  ${v === 'NR'  ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e) { e.stopPropagation(); }
                },
                {
                    title: 'Std Prc', field: 'STANDARD_PRICE', hozAlign: 'center', sorter: 'number', width: 70,
                    headerTooltip: 'Standard Price (Std Prc) — same shared value as /amazon-tabulator-view. Editable; saves to all Sku Link LMP siblings. Dot vs Amz price.',
                    editor: 'input',
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const amzPrice = parseFloat(d['A Price'] || d.a_price || d.amazon_price || 0) || 0;
                        const channelPrice = parseFloat(d['PP Price'] || d.price || 0) || 0;
                        const comparePrice = amzPrice > 0 ? amzPrice : channelPrice;
                        const dot = ppStdPrcChangeDotHtml(std, comparePrice);

                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' + dot + ('$' + std.toFixed(2)) + (window.SpriceLmpCap && typeof SpriceLmpCap.reviewStdTriangleHtml === 'function' ? SpriceLmpCap.reviewStdTriangleHtml(d) : '') + '</span>';
                    }
                },
                {
                    title: 'Std Diff', field: 'std_prc_diff', hozAlign: 'center', width: 62,
                    headerTooltip: 'Std Diff = (Std Prc − PP Price) / PP Price × 100, same as /amazon-tabulator-view. Pink = above the Sprc Dil Diff threshold (default 20%: Sprc Dil adds +10 to the Target SNROI), red = below −5%, green = the rest.',
                    sorter: tabSortBlankLast(function(d) { return ppStdPrcDiffPct(d); }),
                    formatter: function(cell) { return ppRenderStdPrcDiff(cell.getElement(), cell.getRow().getData()); }
                },
                {
                    title: 'Price', field: 'PP Price', hozAlign: 'center', sorter: 'number', width: 70,
                    cellMouseEnter: function(e, cell) { if (typeof temuRuleTipShow === 'function') temuRuleTipShow('price', cell); },
                    cellMouseLeave: function() { if (typeof temuRuleTipScheduleHide === 'function') temuRuleTipScheduleHide(); },
                    headerTooltip: 'Purchasing Power listed price from MCM OF21 (purchasing_power_products). Hover a cell for its source.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (ppIsParentRow(d)) return '';
                        if (!isPpListed(d)) {
                            return '<span style="color:#6c757d;">0</span>';
                        }
                        const v = parseFloat(cell.getValue() || 0);
                        const amz = parseFloat(d['A Price']) || 0;
                        const source = d['PP Price Source'] || 'unknown';
                        const tip = `Source: ${source}`;
                        if (v === 0) {
                            return `<span title="${tip}" style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle"></i></span>`;
                        }
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(v, d.lmp_price || d.lmp || d.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(v, d.lmp_price || d.lmp || d.LMP) : '');
                        const color = amz > 0 ? (v < amz ? '#a00211' : v > amz ? '#28a745' : '') : '';
                        return `<span title="${tip}" style="color:${color};font-weight:${color ? '600' : 'normal'};">$${v.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                    }
                },
                {
                    title: 'A Price', field: 'A Price', hozAlign: 'center', sorter: 'number', width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        return (!v || isNaN(v)) ? '<span style="color:#6c757d;">-</span>' : `$${v.toFixed(2)}`;
                    }
                },
                {
                    title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'NPFT%', field: 'PFT %', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Purchasing Power has no ads — NPFT% = GPFT%
                        const p = parseFloat(cell.getRow().getData()['GPFT%'] ?? cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'GROI%', field: 'ROI%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'NROI%', field: 'NROI', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Purchasing Power has no ads — NROI% = GROI% (ROI%)
                        const p = parseFloat(cell.getRow().getData()['ROI%']);
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'Profit', field: 'Profit', hozAlign: 'center', sorter: 'number', visible: false, width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="color:${v >= 0 ? '#28a745' : '#a00211'};font-weight:600;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Sales', field: 'Sales L30', hozAlign: 'center', sorter: 'number', visible: false, width: 80,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: 'LP', field: 'LP_productmaster', hozAlign: 'center', sorter: 'number', visible: false, width: 60,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: 'Ship BB', field: 'Ship_productmaster', hozAlign: 'center', sorter: 'number', visible: false, width: 70,
                    headerTooltip: 'Shipping Master Ship BB (slab + Handling + O-Size)',
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: '_select', hozAlign: 'center', headerSort: false, width: 40, visible: true,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'];
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${selectedSkus.has(sku) ? 'checked' : ''}>`;
                    }
                },
                ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: 'SPRICE', field: 'SPRICE', hozAlign: 'center',
                    editable: false, width: 120,
                    cellMouseEnter: function(e, cell) { if (typeof temuRuleTipShow === 'function') temuRuleTipShow('sprice', cell); },
                    cellMouseLeave: function() { if (typeof temuRuleTipScheduleHide === 'function') temuRuleTipScheduleHide(); },
                    sorter: tabSortBlankLast(function(d) { if (ppIsParentRow(d)) return null; const v = ppDisplayedSprice(d); return v > 0 ? v : null; }),
                    headerTooltip: 'The ON switch fills this column. Sprc Dil: Dil slab Target SNROI (+ Diff points when Std Diff is above the threshold), no Std Prc maximum. Std prc vs dil: Std Prc − Age − Dil − B Disc − 0 Sold (PP L30 = 0) − CVR − Reviews (or the SNROI Cap price). Either way never below A Price (Amz). No LMP rule: Purchasing Power has no LMP. Clearance SKUs show C and are not priced. Repaints at once on a switch or rule edit; the server saves it right after, and saves and pushes twice a day (4:35 AM / 8:35 PM IST) and every 10 min for blue triangles, page not required. Blue triangle = S PRC ≠ Price. Hover for every rule used.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (ppIsParentRow(d)) return '';
                        // Clearance SKUs show "C" in S PRC (same as Amazon). The server saves and pushes nothing for them.
                        if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) {
                            return '<span class="pp-sprice-clearance" title="Clearance (Inv days clearance = YES): no S PRC is saved or pushed" style="display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;background:#dc3545;color:#fff;font-size:10px;font-weight:800;line-height:1;">C</span>';
                        }
                        let value = ppDisplayedSprice(d);
                        let bg = '';
                        if (d.SPRICE_STATUS === 'pushed') bg = 'background-color:#fff3cd;';
                        else if (d.SPRICE_STATUS === 'applied') bg = 'background-color:#d4edda;';
                        else if (d.SPRICE_STATUS === 'error') bg = 'background-color:#f8d7da;';
                        else if (d.has_custom_sprice) bg = 'background-color:#e7f1ff;';
                        if (!(value > 0)) return '';
                        const live = parseFloat(d['PP Price']) || 0;
                        const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                        const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(d, value) : null;
                        const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                        const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                        const formatted = '$' + value.toFixed(2);
                        const priceHtml = overLmp
                            ? `<span style="color:#dc3545;font-weight:600;${bg}padding:2px 6px;border-radius:3px;">${formatted}</span>`
                            : `<span style="font-weight:600;${bg}padding:2px 6px;border-radius:3px;">${formatted}</span>`;
                        const amz = ppAmazonPrice(d);
                        const amzLbl = ppHasAmzCap(d)
                            ? '<span class="pp-sprice-amz-lbl" title="S PRC raised to Amazon $'
                                + Number(amz).toFixed(2) + '">Amz</span>'
                            : '';
                        const blueTri = (isPpListed(d) && live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        // Clearance SKUs keep the discount price. Red "C" after the value.
                        const isClearance = typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d);
                        const clearanceMark = isClearance
                            ? '<span class="pp-sprice-clearance" title="Clearance" style="font-weight:800;color:#dc3545;margin-left:2px;">C</span>'
                            : '';
                        // No Std Prc cap mark: Purchasing Power has no Std Prc maximum, and no LMP triangles (no LMP data).
                        const stdTri = '';
                        return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${clearanceMark}${amzLbl}${redTri}${blueTri}${stdTri}</span>`;
                    }
                },
                {
                    title: 'SGPFT%', field: 'SGPFT', hozAlign: 'center', width: 50,
                    sorter: tabSortBlankLast(function(d) { return (!ppIsParentRow(d) && ppDisplayedSprice(d) > 0) ? ppSpriceMetrics(d).sgpft : null; }),
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const live = ppSpriceMetrics(d);
                        const stored = parseFloat(cell.getValue());
                        const p = (live.sgpft || stored || 0);
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNPFT%', field: 'SPFT', hozAlign: 'center', width: 50,
                    sorter: tabSortBlankLast(function(d) { return (!ppIsParentRow(d) && ppDisplayedSprice(d) > 0) ? ppSpriceMetrics(d).sgpft : null; }),
                    formatter: function(cell) {
                        // Purchasing Power has no ads — SNPFT% = SGPFT%
                        const d = cell.getRow().getData();
                        const live = ppSpriceMetrics(d);
                        const stored = parseFloat(d.SGPFT ?? cell.getValue());
                        const p = (live.sgpft || stored || 0);
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SROI%', field: 'SROI', hozAlign: 'center', width: 50,
                    sorter: tabSortBlankLast(function(d) { return (!ppIsParentRow(d) && ppDisplayedSprice(d) > 0) ? ppSpriceMetrics(d).sroi : null; }),
                    headerTooltip: 'Gross S ROI from S PRC. Same $ as GROI% using S PRC + Ship BB.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const live = ppSpriceMetrics(d);
                        const stored = parseFloat(cell.getValue());
                        const p = (live.sroi || stored || 0);
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNROI%', field: 'SNROI', hozAlign: 'center', width: 50,
                    sorter: tabSortBlankLast(function(d) { return (!ppIsParentRow(d) && ppDisplayedSprice(d) > 0) ? ppSpriceMetrics(d).sroi : null; }),
                    headerTooltip: 'Dil Target Rule. SNROI% from S PRC so SNROI = slab Target SNROI. Purchasing Power has no Ads% — SNROI% = SROI%.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const live = ppSpriceMetrics(d);
                        const stored = parseFloat(d.SROI ?? cell.getValue());
                        const p = (live.sroi || stored || 0);
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                }
            ]
        });

        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const row = table.getRow($cell.closest('.tabulator-row')[0]);
            const sku = row.getData()['(Child) sku'];
            const newValue = $(this).val();
            $.ajax({
                url: '{{ url("/pp-update-nr-req") }}',
                method: 'POST',
                data: { sku, nr_req: newValue, _token: '{{ csrf_token() }}' },
                success: function() { showToast(`${sku}: updated to ${newValue}`, 'success'); row.update({ nr_req: newValue }); },
                error: function() { showToast(`Failed to update ${sku}`, 'error'); }
            });
        });

        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const d = row.getData();
            const value = cell.getValue();

            if (field === 'STANDARD_PRICE') {
                const sku = d['(Child) sku'] || d.sku || d.SKU;
                const std = parseFloat(value);
                if (!sku || !isFinite(std) || std <= 0) {
                    row.update({ STANDARD_PRICE: null, standard_price: null });
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
                        applyPpStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                        const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                        showToast(n > 1 ? ('Std Prc saved for ' + n + ' linked SKUs') : 'Std Prc saved', 'success');
                    },
                    error: function() {
                        showToast('Failed to save Std Prc', 'error');
                    }
                });
                return;
            }

            // S PRC is server-owned (read-only on this page). Only Std Prc is editable.
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            navigator.clipboard.writeText($(this).data('sku')).then(() => showToast(`Copied: ${$(this).data('sku')}`, 'success'));
        });

        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            const inv   = $('#inventory-filter').val();
            const gpft  = $('#gpft-filter').val();
            const cvrF  = $('#cvr-filter').val();
            const dil   = $('#dil-filter').val();
            const roi   = $('#roi-filter').val();
            table.clearFilter();

            if (inv === 'zero') table.addFilter('INV', '=', 0);
            else if (inv === 'more') table.addFilter('INV', '>', 0);

            if (gpft !== 'all') {
                if (gpft === 'negative') table.addFilter('GPFT%', '<', 0);
                else if (gpft === '50plus') table.addFilter('GPFT%', '>=', 50);
                else { const [min, max] = gpft.split('-').map(Number); table.addFilter('GPFT%', '>=', min); table.addFilter('GPFT%', '<', max); }
            }

            if (cvrF !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.L30) || 0;
                    const sold = parseFloat(d['PP L30']) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrF === '0-0') return cvrRounded === 0;
                    if (cvrF === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrF === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                    if (cvrF === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrF === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // ROI% filter (same as AliExpress)
            if (roi !== 'all') {
                table.addFilter(function(d) {
                    const roiVal = parseFloat(d['ROI%']) || 0;
                    if (roi === 'lt40')  return roiVal < 40;
                    if (roi === '40-75') return roiVal >= 40 && roiVal < 75;
                    if (roi === '75-125') return roiVal >= 75 && roiVal < 125;
                    if (roi === 'gt125') return roiVal >= 125;
                    return true;
                });
            }

            if (dil !== 'all') {
                table.addFilter(function(data) {
                    const inv2 = parseFloat(data.INV) || 0, l30 = parseFloat(data.L30) || 0;
                    const d2 = inv2 === 0 ? 0 : (l30 / inv2) * 100;
                    if (dil === 'red') return d2 < 25;
                    if (dil === 'green') return d2 >= 25 && d2 < 50;
                    if (dil === 'pink') return d2 >= 50;
                    return true;
                });
            }

            if (soldFilter === 'zero') table.addFilter('PP L30', '=', 0);
            else if (soldFilter === 'sold') table.addFilter('PP L30', '>', 0);
            if (lessAmzFilterActive) table.addFilter(d => { const mc = parseFloat(d['PP Price']) || 0, amz = parseFloat(d['A Price']) || 0; return amz > 0 && mc > 0 && mc < amz; });
            if (moreAmzFilterActive) table.addFilter(d => { const mc = parseFloat(d['PP Price']) || 0, amz = parseFloat(d['A Price']) || 0; return amz > 0 && mc > 0 && mc > amz; });
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'PP Price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'PP Price');
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return ppHasBlueTriangle(data);
                });
            }
            if (amzCapFilterActive) {
                table.addFilter(function(data) {
                    return ppHasAmzCap(data);
                });
            }
            if (clearanceFilterActive) {
                table.addFilter(function(data) {
                    return !ppIsParentRow(data) && typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(data);
                });
            }

            updateSummary();
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#purchasingpower-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) {
                        blueTriangleFilterActive = false;
                        amzCapFilterActive = false;
                        clearanceFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#purchasingpower-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) {
                        blueTriangleFilterActive = false;
                        amzCapFilterActive = false;
                        clearanceFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        $('#pp-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                amzCapFilterActive = false;
                clearanceFilterActive = false;
            }
            applyFilters();
        });
        $('#pp-clearance-badge').on('click', function() {
            clearanceFilterActive = !clearanceFilterActive;
            if (clearanceFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                blueTriangleFilterActive = false;
                amzCapFilterActive = false;
                if (window.PriceGtLmpBadge) { PriceGtLmpBadge.setOutline(document.querySelector('[id$="-price-gt-lmp-badge"]'), false); }
                if (window.PriceLt80LmpBadge) { PriceLt80LmpBadge.setOutline(document.querySelector('[id$="-price-lt80-lmp-badge"]'), false); }
            }
            applyFilters();
        });

        // Std prc vs dil rule + clearance all loaded: repaint the "C" marks, recount the badges.
        $(document).off('chStd:settled.pp').on('chStd:settled.pp', function() {
            try { if (table && table.getRows) table.getRows('active').forEach(function(r) { try { r.reformat(); } catch (e) { /* ignore */ } }); } catch (e) { /* ignore */ }
            try { if (typeof updateSummary === 'function') updateSummary(); } catch (e) { /* ignore */ }
        });
        // Clearance SKU list (Inv days clearance = YES) loads once, then the C badge count refreshes.
        (function loadClearanceForBadge(tries) {
            if (typeof window.chStdLoadClearance !== 'function') {
                if (tries < 40) setTimeout(function() { loadClearanceForBadge(tries + 1); }, 250);
                return;
            }
            window.chStdLoadClearance().always(function() {
                // The S PRC cells painted before the clearance list arrived: repaint so the "C" shows.
                try { if (table && table.getRows) table.getRows('active').forEach(function(r) { try { r.reformat(); } catch (e) { /* ignore */ } }); } catch (e) { /* ignore */ }
                setTimeout(function() {
                    try { if (table && table.getRows) table.getRows('active').forEach(function(r) { try { r.reformat(); } catch (e) { /* ignore */ } }); } catch (e) { /* ignore */ }
                    try { if (typeof updateSummary === 'function') updateSummary(); } catch (e) { /* ignore */ }
                }, 2500);
                try { if (typeof updateSummary === 'function') updateSummary(); } catch (e) { /* ignore */ }
            });
        })(0);
        $('#pp-amz-cap-badge').on('click', function() {
            amzCapFilterActive = !amzCapFilterActive;
            if (amzCapFilterActive) {
                clearanceFilterActive = false;
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                blueTriangleFilterActive = false;
            }
            applyFilters();
        });

        $('#inventory-filter, #gpft-filter, #cvr-filter, #dil-filter, #roi-filter').on('change', function() { applyFilters(); });

        function updateSummary() {
            const data = table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT')));
            let totalPft = 0, totalSales = 0, zeroSold = 0, totalDil = 0, dilCount = 0;
            let totalCogs = 0;

            data.forEach(row => {
                totalPft   += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;

                if ((parseFloat(row['PP L30']) || 0) === 0) zeroSold++;

                const dil = parseFloat(row['PP Dil%']) || 0;
                if (dil > 0) { totalDil += dil; dilCount++; }

                const lp = parseFloat(row.LP_productmaster) || 0, l30 = parseFloat(row['PP L30']) || 0;
                totalCogs += lp * l30;
            });

            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            // Weighted ROI % = (Σ Profit ÷ Σ COGS) × 100.
            // Matches /all-marketplace-master's G ROI cell exactly (same formula
            // /bestbuy/daily-sales & /aliexpress-tabulator use). The simple-average
            // version that lived here (Σ ROI% ÷ count) was misleading — a single
            // SKU with $1 COGS and 800% ROI swung the badge a lot even though it
            // contributed almost no profit. Weighted = the right number for
            // leadership and matches the channel master row.
            const roiPctWeighted = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-pft-amt-badge').text(`Total PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Total Sales: $${Math.round(totalSales).toLocaleString()}`);
            // PFT $ (sum of Profit) and GPFT % weighted by sales — same shape as
            // /all-marketplace-master's Total PFT + Gprofit% cells and the
            // /purchasing-power-sales page badges. Weighted GPFT respects sales
            // volume per SKU (industry-standard margin), unlike a simple per-row
            // average that lets low-sales SKUs swing the % unfairly.
            const gpftPctWeighted = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;
            $('#gpft-pct-badge').text(`GPFT: ${gpftPctWeighted.toFixed(1)}%`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSold}`);
            $('#avg-dil-badge').text(`DIL%: ${(avgDil * 100).toFixed(1)}%`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#roi-percent-badge').text(`GROI: ${roiPctWeighted.toFixed(1)}%`);
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#purchasingpower-price-gt-lmp-badge', table.getData(), 'purchasingpower', 'PP Price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#purchasingpower-price-lt80-lmp-badge', table.getData(), 'purchasingpower', 'PP Price');
                }
            }
            let blueTriangleCount = 0;
            let amzCapCount = 0;
            let clearanceCount = 0;
            (table ? table.getData() : []).forEach(function(row) {
                if (ppHasBlueTriangle(row)) blueTriangleCount++;
                if (ppHasAmzCap(row)) amzCapCount++;
                if (!ppIsParentRow(row) && typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(row)) clearanceCount++;
            });
            $('#pp-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            $('#pp-amz-cap-badge').text('Amz ' + amzCapCount.toLocaleString());
            $('#pp-clearance-badge').text('C ' + clearanceCount.toLocaleString());
            (function() {
                let shown = 0, total = 0;
                try {
                    total = table.getData().filter(function(r) { return !ppIsParentRow(r); }).length;
                    shown = table.getData('active').filter(function(r) { return !ppIsParentRow(r); }).length;
                } catch (e) { /* ignore */ }
                $('#pp-rows-badge').text('Rows: ' + shown.toLocaleString() + (shown !== total ? ' / ' + total.toLocaleString() : ''));
            })();
            if (typeof syncPpTriangleBadgeState === 'function') syncPpTriangleBadgeState();
        
            if (window.AnalyticsDilBadge) {
                var __dilRows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) ? allTableData
                    : (typeof summaryDataCache !== 'undefined' && Array.isArray(summaryDataCache) && summaryDataCache.length) ? summaryDataCache
                    : (typeof table !== 'undefined' && table && typeof table.getData === 'function') ? (function () { try { return table.getData() || []; } catch (e) { return []; } })()
                    : (typeof tableData !== 'undefined' && Array.isArray(tableData) ? tableData : []);
                AnalyticsDilBadge.paintFromRows(__dilRows);
            }
        }

        function buildColumnDropdown() {
            if (window.AnalyticsColVis) {
                window.AnalyticsColVis.install({
                    getTable: function() { return table; },
                    menuId: 'column-dropdown-menu',
                    storageKey: 'purchasing_power_col_cats_v1',
                    skipFields: ['_select'],
                    onSave: function() {
                        if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
                    }
                });
                window.AnalyticsColVis.rebuild();
                return;
            }
            let html = '';
            table.getColumns().forEach(col => {
                const field = col.getField(), title = col.getDefinition().title;
                if (field && field !== '_select' && title) {
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${col.isVisible() ? 'checked' : ''}>
                        ${title.replace(/<[^>]*>/g, '')}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => { if (col.getField() && col.getField() !== '_select') visibility[col.getField()] = col.isVisible(); });
            $.ajax({ url: '/pp-pricing-column-visibility', method: 'POST', data: { visibility, _token: '{{ csrf_token() }}' } });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/pp-pricing-column-visibility', method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) visibility[field] ? col.show() : col.hide();
                        });
                        buildColumnDropdown();
                    }
                }
            });
        }

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

        table.on('tableBuilt', function() { buildColumnDropdown(); applyColumnVisibilityFromServer(); });
        // Std Diff follows the row when Std Prc or the PP Price changes.
        table.on('rowUpdated', function(row) {
            try {
                const c = row.getCell('std_prc_diff');
                if (!c) return;
                const el = c.getElement();
                el.innerHTML = ppRenderStdPrcDiff(el, row.getData());
            } catch (e) { /* ignore */ }
        });
        table.on('dataLoaded', function(data) {
            // ParentExpand.expand() replaces the table data with a single parent's
            // children, which fires dataLoaded again. Without this guard allTableData would
            // be overwritten by that subset and later filters would run against only
            // those rows, leaving the table stuck on the expanded group.
            if (window.ParentExpand && ParentExpand.isExpanded()) return;
            allTableData = Array.isArray(data) ? data : [];
            if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
            setTimeout(function() { applyFilters(); updateSummary(); }, 100);
        });
        table.on('renderComplete', function() { setTimeout(function() { updateSummary(); }, 100); });

        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) { e.target.checked ? col.show() : col.hide(); saveColumnVisibilityToServer(); }
            }
        });

        document.getElementById('show-all-columns-btn').addEventListener('click', function() {
            table.getColumns().forEach(col => { if (col.getField() !== '_select') col.show(); });
            buildColumnDropdown(); saveColumnVisibilityToServer();
        });

        document.getElementById('export-btn').addEventListener('click', function() {
            const visibleCols = table.getColumns().filter(c => c.isVisible() && c.getField() !== '_select');
            const headers = visibleCols.map(c => c.getDefinition().title || c.getField());
            const rows = table.getData('active').map(row => visibleCols.map(col => {
                let v = row[col.getField()];
                if (v === null || v === undefined) return '';
                if (typeof v === 'number') return parseFloat(v.toFixed(2));
                return v;
            }));
            let csv = [headers, ...rows].map(row => row.map(cell => {
                if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n')))
                    return '"' + cell.replace(/"/g, '""') + '"';
                return cell;
            }).join(',')).join('\n');
            const link = document.createElement('a');
            link.setAttribute('href', URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' })));
            link.setAttribute('download', 'purchasing_power_pricing_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });

    });
</script>
@endsection
