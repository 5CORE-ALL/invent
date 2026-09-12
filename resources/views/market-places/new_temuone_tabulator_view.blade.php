@extends('layouts.vertical', ['title' => 'New Temu One', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Vertical column headers (same as /bestbuy-pricing) */
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

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .lmp-eye-btn {
            line-height: 1;
            vertical-align: middle;
        }
        #lmpModal tr.lmp-lowest-row,
        #lmpModal tr.lmp-lowest-row > td {
            background-color: #d1ecf1 !important;
        }
        #lmpModal tr.lmp-ignored-row {
            opacity: 0.55;
        }

        .toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1080;
        }

        .temu-sprice-cap-lbl {
            color: #fd7e14;
            font-weight: 800;
            font-size: 10px;
            line-height: 1;
            margin-left: 3px;
            cursor: help;
        }
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'temu'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'New Temu One',
        'sub_title' => 'New Temu One',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div id="summary-stats" class="d-flex align-items-center flex-wrap gap-1">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All INV</option>
                        <option value="zero">INV = 0</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm"
                        style="width: 90px; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red (&lt;25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>
                    @include('partials.ebay-sprc-dil', [
                        'ebaySprcDilPart' => 'buttons',
                        'ebaySprcDilChannel' => 'temu',
                        'ebaySprcDilZeroSoldUsesMinGroi' => false,
                    ])
                </div>
                <div id="newtemuone-badge-row" class="d-flex flex-wrap gap-1 mt-2" role="group" aria-label="Summary metrics">
                    <span class="badge bg-dark fs-6 p-2" id="rows-count-badge"
                        style="color: white; font-weight: bold;"
                        title="Number of rows currently shown after filters">Rows: 0</span>
                    <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                        style="color: white; font-weight: bold; cursor: pointer;"
                        title="Temu L30 = 0 with INV &gt; 0. Click to filter.">0 Sold: 0</span>
                    <span class="badge fs-6 p-2" id="more-sold-count-badge"
                        style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;"
                        title="Temu L30 &gt; 0 with INV &gt; 0. Click to filter.">&gt; 0 Sold: 0</span>
                    <span class="badge fs-6 p-2" id="total-l30-badge"
                        style="background-color: #6f42c1; color: white; font-weight: bold;"
                        title="Σ Temu L30 qty of the rows shown">L30: 0</span>
                    <span class="badge bg-info fs-6 p-2" id="total-views-badge"
                        style="color: black; font-weight: bold;"
                        title="Σ Views of the rows shown">Views: 0</span>
                    <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge"
                        style="color: white; font-weight: bold;"
                        title="CVR = (Σ Temu L30 ÷ Σ Views) × 100">CVR: 0%</span>
                    <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge"
                        style="color: black; font-weight: bold;"
                        title="GPFT% = Σ Gpft ÷ Σ T Price × 100 (margin from marketplace_percentages &quot;Temu&quot;)">GPFT: 0%</span>
                    <span class="badge bg-secondary fs-6 p-2" id="avg-groi-badge"
                        style="color: white; font-weight: bold;"
                        title="GROI% = Σ Gpft ÷ Σ LP × 100 (margin from marketplace_percentages &quot;Temu&quot;)">GROI: 0%</span>
                    @php
                        $adsPct = (float) ($temuAds['percent'] ?? 0);
                        // Same Ads% bands as the /channel-master Ads % column.
                        $adsColor = $adsPct < 5 ? '#e83e8c' : ($adsPct <= 10 ? '#28a745' : '#a00211');
                    @endphp
                    <span class="badge fs-6 p-2" id="ads-percent-badge"
                        style="background-color: {{ $adsColor }}; color: white; font-weight: bold;"
                        title="Ads% = L30 Ad Spend ${{ number_format((float) ($temuAds['spend'] ?? 0), 2) }} ÷ L30 Sales ${{ number_format((float) ($temuAds['sales'] ?? 0), 2) }} × 100. Same source as the /channel-master Temu row: spend from temu_ads_api_reports, sales from temu_orders ({{ $temuAds['window'] ?? 'L30' }}).">Ads: {{ number_format($adsPct, 1) }}%<a
                            href="/temu/ads" target="_blank" rel="noopener noreferrer"
                            style="color: white; margin-left: 4px;" title="Open Temu ads page"
                            onclick="event.stopPropagation();"><i class="fas fa-arrow-up-right-from-square"></i></a></span>
                    @include('partials.price-gt-lmp-badge', [
                        'pglBadgeId' => 'newtemuone-price-gt-lmp-badge',
                        'pglChannelKey' => '',
                        'pglPriceField' => 'temu_price',
                    ])
                    @include('partials.price-lt80-lmp-badge', [
                        'pltBadgeId' => 'newtemuone-price-lt80-lmp-badge',
                        'pltChannelKey' => '',
                        'pltPriceField' => 'temu_price',
                    ])
                    <span class="badge fs-6 p-2" id="newtemuone-blue-triangle-badge"
                        style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                        title="Blue triangle: S PRC ≠ T Price. Click to filter."><i class="fas fa-exclamation-triangle"></i> 0</span>
                    <span class="badge fs-6 p-2" id="newtemuone-lmp-cap-badge"
                        style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
                        title="S PRC capped at LMP. Click to filter.">LMP cap 0</span>
                    <span class="badge fs-6 p-2" id="temu-amz-cap-badge"
                        style="background-color:#fd7e14;color:#fff;font-weight:700;cursor:pointer;"
                        title="S PRC capped to Amazon. Click to show only Amz rows."
                        aria-label="S PRC capped to Amazon">Amz 0</span>
                    <span class="badge fs-6 p-2" id="temu-eb-cap-badge"
                        style="background-color:#fd7e14;color:#fff;font-weight:700;cursor:pointer;"
                        title="S PRC capped to eBay. Click to show only EB rows."
                        aria-label="S PRC capped to eBay">EB 0</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="new-temuone-table-wrapper" style="height: calc(100vh - 160px); display: flex; flex-direction: column;">
                    <div class="px-2 py-1 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                    </div>
                    <div id="new-temuone-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="newTemuoneEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="newTemuoneEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="newTemuoneSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="newTemuoneBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="newTemuoneSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="lmpModalLabel"><i class="fas fa-link me-2"></i>LMP for <span id="lmpModalSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-3"><i class="fas fa-plus text-success me-1"></i> Add New LMP</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewPrice" placeholder="e.g. 29.99">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-0">Delivery</label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="lmpNewDelivery" placeholder="0.00">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-0">Product Link</label>
                                <input type="text" class="form-control form-control-sm" id="lmpNewLink" placeholder="https://...">
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="lmpAddRowBtn"><i class="fas fa-plus me-1"></i> Add LMP</button>
                            </div>
                        </div>
                    </div>
                    <h6 class="mb-2">LMP List</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="lmpListTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Price</th>
                                    <th style="width: 90px;">Delivery</th>
                                    <th style="width: 90px;">Price+D</th>
                                    <th>Link</th>
                                    <th class="text-center" style="width: 70px;">Ignore</th>
                                    <th style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="lmpEntriesContainer"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.ebay-sprc-dil', [
        'ebaySprcDilPart' => 'modals',
        'ebaySprcDilChannel' => 'temu',
        'ebaySprcDilZeroSoldUsesMinGroi' => false,
    ])
@endsection

@section('script-bottom')
<script>
    let table = null;
    let newTemuoneEditLinksRow = null;

    function chPromoRound2(n) {
        return Math.round((Number(n) || 0) * 100) / 100;
    }
    function chPromoTemuInvertSgroiAtSprice(sprice, lp, ship) {
        const s = Number(sprice);
        const cost = Number(lp);
        const shipN = Number(ship);
        if (!(s > 0) || !(cost > 0) || !isFinite(s) || !isFinite(cost)) return null;
        const shipUse = isFinite(shipN) && shipN > 0 ? shipN : 0;
        const mult = 1.1364;
        const candidates = [(s - 2.99) / mult, s / mult];
        let best = 0;
        let bestErr = Infinity;
        candidates.forEach(function(base) {
            if (!(base > 0)) return;
            let full = base * mult;
            if (full <= 26.99) full += 2.99;
            const err = Math.abs(full - s);
            if (err < bestErr - 1e-6) {
                bestErr = err;
                best = base;
            } else if (Math.abs(err - bestErr) <= 1e-6 && base > best) {
                best = base;
            }
        });
        if (!(best > 0)) return null;
        const sR = best <= 26.99 ? best + 2.99 : best;
        return ((sR * 0.95 - shipUse - cost) / cost) * 100;
    }
    /** Same Temu SGROI back-solve as /temu2-decrease so Sprc Dil uses temu_data_view math. */
    function chPromoSpriceFromTargetRoi(d, roiPct) {
        const lp = parseFloat(d && (d.LP_productmaster != null ? d.LP_productmaster : d.lp)) || 0;
        if (!(lp > 0)) return 0;
        const ship = parseFloat(d && (d.temu_ship != null ? d.temu_ship : d.Ship_productmaster)) || 0;
        const roi = isFinite(Number(roiPct)) ? Number(roiPct) : 0;
        const targetSR = (lp * (1 + roi / 100) + ship) / 0.95;
        if (!(targetSR > 0) || !isFinite(targetSR)) return 0;
        const base = targetSR > 26.99 ? targetSR : Math.max(0.01, targetSR - 2.99);
        let seed = base * 1.1364;
        if (seed <= 26.99) seed += 2.99;
        seed = chPromoRound2(seed);
        const seedSgroi = chPromoTemuInvertSgroiAtSprice(seed, lp, ship);
        if (seedSgroi != null && Math.abs(seedSgroi - roi) <= 1.5) return seed;
        let lo = Math.max(0.01, seed * 0.35);
        let hi = Math.max(seed * 2.8, seed + 20);
        for (let expand = 0; expand < 10; expand++) {
            const gLo = chPromoTemuInvertSgroiAtSprice(lo, lp, ship);
            const gHi = chPromoTemuInvertSgroiAtSprice(hi, lp, ship);
            if (gLo == null || gHi == null) break;
            if (gLo <= roi && roi <= gHi) break;
            if (roi < gLo) { hi = lo; lo = Math.max(0.01, lo * 0.5); }
            else { lo = hi; hi = hi * 1.8; }
        }
        let best = seed;
        let bestErr = Infinity;
        for (let i = 0; i < 40; i++) {
            const mid = (lo + hi) / 2;
            const g = chPromoTemuInvertSgroiAtSprice(mid, lp, ship);
            if (g == null) break;
            const err = Math.abs(g - roi);
            if (err < bestErr) { bestErr = err; best = mid; }
            if (g < roi) lo = mid;
            else hi = mid;
        }
        return (isFinite(best) && best > 0) ? chPromoRound2(best) : 0;
    }
    window.chPromoSpriceFromTargetRoi = chPromoSpriceFromTargetRoi;

    @include('partials.ebay-sprc-dil', [
        'ebaySprcDilPart' => 'script',
        'ebaySprcDilChannel' => 'temu',
        'ebaySprcDilZeroSoldUsesMinGroi' => false,
    ])

    function temuParseMoney(v) {
        const n = parseFloat(v);
        return (isFinite(n) && n > 0) ? n : 0;
    }

    function temuAmzRefPrice(row) {
        return temuParseMoney(row && (row.a_price != null ? row.a_price
            : (row['A Price'] != null ? row['A Price'] : row.amazon_price)));
    }

    function temuEbayRefPrice(row) {
        const e = temuParseMoney(row && (row.e_price != null ? row.e_price : row.ebay_price));
        const e2 = temuParseMoney(row && (row.e2_price != null ? row.e2_price : row.ebay2_price));
        if (e > 0 && e2 > 0) return Math.min(e, e2);
        return e > 0 ? e : e2;
    }

    function temuLmpRefPrice(row) {
        return temuParseMoney(row && (row.lmp_raw != null ? row.lmp_raw : row.lmp));
    }

    /** Discounted Price = Sprc Dil from the /temu1-data Dil table (Temu 2: Dil slab including 0 Sold). */
    function temuDiscountedPrice(row) {
        if (!row) return 0;
        if (typeof ebaySprcDilForRow === 'function') {
            const sprcDil = Number(ebaySprcDilForRow(row));
            if (sprcDil > 0) return +sprcDil.toFixed(2);
        }
        if (typeof chPromoTemuZeroSoldSprice === 'function') {
            const zeroSold = Number(chPromoTemuZeroSoldSprice(row));
            if (zeroSold > 0) return +zeroSold.toFixed(2);
        }
        if (typeof chPromoTemuSpriceFromStdPrmtCpn === 'function') {
            const combo = Number(chPromoTemuSpriceFromStdPrmtCpn(row));
            if (combo > 0) return +combo.toFixed(2);
        }
        if (typeof chPromoSpriceFromStdTPromo === 'function') {
            const calc = chPromoSpriceFromStdTPromo(row, { skip_lmp_cap: true });
            if (calc > 0) return +Number(calc).toFixed(2);
        }
        const fallback = parseFloat(row.sprc_dil);
        return fallback > 0 ? +fallback.toFixed(2) : 0;
    }

    // The cap chain runs a GROI bisection per call, and a dozen formatters plus the badge
    // pass ask for the same row's cap. Memoize per render pass; every event that can change
    // a cap (redraw after a Sprc Dil edit, new data, refilter) clears it first.
    const temuCapMemo = new Map();

    function temuClearCapMemo() {
        temuCapMemo.clear();
    }

    /** Memoized entry point. Callers passing an ad-hoc row must use temuSpriceCapCompute. */
    function temuSpriceCapResult(row, rawSprice, extra) {
        if (rawSprice !== undefined || extra !== undefined) {
            return temuSpriceCapCompute(row, rawSprice, extra);
        }
        const key = row && (row['(Child) sku'] || row.sku);
        if (!key) return temuSpriceCapCompute(row);
        if (temuCapMemo.has(key)) return temuCapMemo.get(key);
        const cap = temuSpriceCapCompute(row);
        temuCapMemo.set(key, cap);
        return cap;
    }

    /** S PRC = Discounted Price, then the lowest of eBay / Amazon / LMP when cheaper. Same as /temu2-decrease. */
    function temuSpriceCapCompute(row, rawSprice, extra) {
        extra = extra || {};
        const liveDiscounted = temuDiscountedPrice(row);
        const passed = parseFloat(rawSprice);
        let discounted = 0;
        if (extra.use_passed_as_discounted && passed > 0) {
            discounted = +passed.toFixed(2);
        } else if (liveDiscounted > 0) {
            discounted = +liveDiscounted.toFixed(2);
        }
        let sprice = discounted > 0 ? discounted : 0;
        if (!(sprice > 0)) return { sprice: 0, labels: [], lmpAlert: false, amz: 0, ebay: 0, lmp: 0 };

        const ebay = +temuEbayRefPrice(row).toFixed(2);
        const amz = +temuAmzRefPrice(row).toFixed(2);
        const lmp = extra.skip_lmp_cap ? 0 : +temuLmpRefPrice(row).toFixed(2);
        const zeroSoldOwns = typeof chPromoTemuZeroSoldOwnsSprice === 'function'
            && chPromoTemuZeroSoldOwnsSprice(row);

        if (!zeroSoldOwns) {
            if (ebay > 0 && sprice > ebay) sprice = +ebay.toFixed(2);
            if (amz > 0 && sprice > amz) sprice = +amz.toFixed(2);
            if (lmp > 0 && sprice > lmp) sprice = +lmp.toFixed(2);
        }

        const labels = [];
        if (ebay > 0 && +sprice.toFixed(2) === ebay && discounted > ebay) labels.push('EB');
        if (amz > 0 && +sprice.toFixed(2) === amz && discounted > amz) labels.push('Amz');
        const lmpAlert = lmp > 0 && +sprice.toFixed(2) === lmp && discounted > lmp;
        return { sprice: +sprice.toFixed(2), labels: labels, lmpAlert: lmpAlert, amz: amz, ebay: ebay, lmp: lmp };
    }

    function temuSpriceCellModel(row) {
        if (!row) return { value: 0, labels: [], lmpAlert: false, lmp: 0, amz: 0, ebay: 0 };
        const cap = temuSpriceCapResult(row);
        const value = (cap && cap.sprice > 0) ? +Number(cap.sprice).toFixed(2) : 0;
        if (value > 0) {
            row.SPRICE = value;
            row.sprice = value;
        }
        return {
            value: value,
            labels: (cap && cap.labels) || [],
            lmpAlert: !!(cap && cap.lmpAlert),
            lmp: (cap && cap.lmp) || 0,
            amz: (cap && cap.amz) || 0,
            ebay: (cap && cap.ebay) || 0,
        };
    }

    function temuDisplayedSprice(row) {
        const cap = temuSpriceCapResult(row);
        return (cap && cap.sprice > 0) ? +Number(cap.sprice).toFixed(2) : 0;
    }

    /** S Base Prc: invert S PRC through the T Price rule — the Base Price equivalent of S PRC. */
    function temuSBaseFromSprice(sprice) {
        const s = parseFloat(sprice) || 0;
        if (!(s > 0)) return 0;
        const mult = 1.1364;
        const candidates = [(s - 2.99) / mult, s / mult];
        let best = 0;
        let bestErr = Infinity;
        candidates.forEach(function(base) {
            if (!(base > 0)) return;
            let full = base * mult;
            if (full <= 26.99) full += 2.99;
            const err = Math.abs(full - s);
            if (err < bestErr - 1e-6) {
                bestErr = err;
                best = base;
            } else if (Math.abs(err - bestErr) <= 1e-6 && base > best) {
                best = base;
            }
        });
        return best > 0 ? +best.toFixed(2) : 0;
    }

    /** S R Prc: S Base Prc + $2.99 when that base ≤ $26.99 — same rule as R Price. */
    function temuSRPriceFromSprice(sprice) {
        const base = temuSBaseFromSprice(sprice);
        if (!(base > 0)) return 0;
        return +(base <= 26.99 ? base + 2.99 : base).toFixed(2);
    }
    window.temuSBaseFromSprice = temuSBaseFromSprice;
    window.temuSRPriceFromSprice = temuSRPriceFromSprice;

    const TEMU_MARGIN_FALLBACK = {{ (float) $temuMargin }};

    /** Take-home margin from marketplace_percentages "Temu" (passed per row). */
    function temuRowMargin(row) {
        const raw = parseFloat(row && row.percentage);
        if (isFinite(raw) && raw > 0) return raw > 1 ? raw / 100 : raw;
        return TEMU_MARGIN_FALLBACK;
    }

    /** Gpft$ = (R Price × margin) − Temu Ship − LP. */
    function temuGpftDollars(row) {
        const rPrice = parseFloat(row && row.r_price) || 0;
        if (!(rPrice > 0)) return null;
        const lp = parseFloat(row && row.lp) || 0;
        const ship = parseFloat(row && row.temu_ship) || 0;
        return (rPrice * temuRowMargin(row)) - ship - lp;
    }

    /** SPFT$ = (S R Prc × margin) − Temu Ship − LP. */
    function temuSpftDollars(row, spriceOverride) {
        const sprice = spriceOverride != null
            ? (parseFloat(spriceOverride) || 0)
            : temuDisplayedSprice(row);
        const sR = temuSRPriceFromSprice(sprice);
        if (!(sR > 0)) return null;
        const lp = parseFloat(row && row.lp) || 0;
        const ship = parseFloat(row && row.temu_ship) || 0;
        return (sR * temuRowMargin(row)) - ship - lp;
    }

    function temuGpftPercent(row) {
        const gpft = temuGpftDollars(row);
        const tPrice = parseFloat(row && row.t_price) || 0;
        if (gpft == null || !(tPrice > 0)) return null;
        return (gpft / tPrice) * 100;
    }

    function temuGroiPercent(row) {
        const gpft = temuGpftDollars(row);
        const lp = parseFloat(row && row.lp) || 0;
        if (gpft == null || !(lp > 0)) return null;
        return (gpft / lp) * 100;
    }

    function temuSgpftPercent(row) {
        const sprice = temuDisplayedSprice(row);
        const spft = temuSpftDollars(row, sprice);
        if (spft == null || !(sprice > 0)) return null;
        return (spft / sprice) * 100;
    }

    function temuSgroiPercent(row) {
        const spft = temuSpftDollars(row);
        const lp = parseFloat(row && row.lp) || 0;
        if (spft == null || !(lp > 0)) return null;
        return (spft / lp) * 100;
    }

    // Channel-level Ads% (same value as the Ads badge) — Temu 2 nets every row against
    // the one channel Ads%, not a per-SKU spend.
    const TEMU_ADS_PERCENT = {{ (float) ($temuAds['percent'] ?? 0) }};

    function temuAdsPercentForNet() {
        const n = parseFloat(TEMU_ADS_PERCENT);
        return Number.isFinite(n) ? n : 0;
    }

    /** NPFT$ = Gpft$ − (T Price × Ads%). */
    function temuNpftDollars(row) {
        const gpft = temuGpftDollars(row);
        if (gpft == null) return null;
        const tPrice = parseFloat(row && row.t_price) || 0;
        return gpft - (tPrice * (temuAdsPercentForNet() / 100));
    }

    /** SNPFT$ = SPFT$ − (S PRC × Ads%). */
    function temuSnpftDollars(row, spriceOverride) {
        const spft = temuSpftDollars(row, spriceOverride);
        if (spft == null) return null;
        const sprice = spriceOverride != null
            ? (parseFloat(spriceOverride) || 0)
            : temuDisplayedSprice(row);
        return spft - ((sprice > 0 ? sprice : 0) * (temuAdsPercentForNet() / 100));
    }

    function temuNpftPercent(row) {
        const npft = temuNpftDollars(row);
        const tPrice = parseFloat(row && row.t_price) || 0;
        if (npft == null || !(tPrice > 0)) return null;
        return (npft / tPrice) * 100;
    }

    function temuNroiPercent(row) {
        const npft = temuNpftDollars(row);
        const lp = parseFloat(row && row.lp) || 0;
        if (npft == null || !(lp > 0)) return null;
        return (npft / lp) * 100;
    }

    function temuSnpftPercent(row) {
        const sprice = temuDisplayedSprice(row);
        const snpft = temuSnpftDollars(row, sprice);
        if (snpft == null || !(sprice > 0)) return null;
        return (snpft / sprice) * 100;
    }

    function temuSnroiPercent(row) {
        const snpft = temuSnpftDollars(row);
        const lp = parseFloat(row && row.lp) || 0;
        if (snpft == null || !(lp > 0)) return null;
        return (snpft / lp) * 100;
    }

    // CVR 30 vs CVR 60, ±0.1pp dead band. Same rule the Amazon tabulator uses for its
    // CVR arrow, and the same trend that shifts the Sprc Dil Target GROI by ±10.
    const TEMU_CVR_TREND_TOL = 0.1;

    function temuCvrTrend(cvr, cvrPrior) {
        const val = parseFloat(cvr) || 0;
        const prior = parseFloat(cvrPrior) || 0;
        if (val === 0 || val < prior - TEMU_CVR_TREND_TOL) return 'down';
        if (val > prior + TEMU_CVR_TREND_TOL) return 'up';
        return 'flat';
    }

    function temuCvrTrendArrowHtml(cvr, cvrPrior) {
        const val = parseFloat(cvr) || 0;
        const prior = parseFloat(cvrPrior) || 0;
        const trend = temuCvrTrend(val, prior);
        const priorLabel = prior.toFixed(1) + '%';
        let icon = 'fa-minus';
        let color = '#ffc107';
        let tip = 'Flat vs CVR 60 ' + priorLabel;
        if (trend === 'down') {
            icon = 'fa-arrow-down';
            color = '#a00211';
            tip = val === 0 ? 'CVR 30 is 0 → Down' : 'Down vs CVR 60 ' + priorLabel;
        } else if (trend === 'up') {
            icon = 'fa-arrow-up';
            color = '#28a745';
            tip = 'Up vs CVR 60 ' + priorLabel;
        }
        return ' <span title="' + tip + '" style="vertical-align: middle;">'
            + '<i class="fas ' + icon + '" style="color: ' + color + '; font-size: 12px;"></i></span>';
    }

    /** Band + hex for a metric. kind 'pft' = GPFT/SGPFT slabs, 'roi' = GROI/SGROI slabs. */
    function temuPercentBand(value, kind) {
        if (!window.MetricPctColors) return { band: '', color: '' };
        const band = kind === 'pft'
            ? MetricPctColors.gpftBand(value)
            : MetricPctColors.groiBand(value);
        const color = kind === 'pft'
            ? MetricPctColors.gpftColor(value)
            : MetricPctColors.groiColor(value);
        return { band: band || '', color: color || '' };
    }

    /** Colored text only. Yellow keeps a filled pill — yellow text is unreadable on white. */
    function temuPercentCell(value, kind, tip) {
        if (value == null || !isFinite(value)) return '<span style="color: #6c757d;">—</span>';
        const meta = temuPercentBand(value, kind);
        const label = Math.round(value) + '%';
        const titleAttr = ' title="' + String(tip).replace(/"/g, '&quot;') + '"';
        if (meta.band === 'yellow') {
            return '<span class="dil-percent-value yellow"' + titleAttr + '>' + label + '</span>';
        }
        return '<span style="color: ' + (meta.color || '#6c757d') + '; font-weight: 700;"' + titleAttr + '>'
            + label + '</span>';
    }
    window.temuDisplayedSprice = temuDisplayedSprice;
    window.temuSpriceCapResult = temuSpriceCapResult;
    window.temuDiscountedPrice = temuDiscountedPrice;

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

    function temuLmpRecovery(price) {
        const p = parseFloat(price);
        if (!(p > 0)) return null;
        if (p <= 27) return +((p * 0.85) + 2.99).toFixed(2);
        return +(p * 0.85).toFixed(2);
    }

    function temuLmpEntryEffective(entry) {
        if (!entry || entry.ignored) return null;
        const base = parseFloat(entry.price);
        if (!(base > 0)) return null;
        const dRaw = parseFloat(entry.delivery);
        let delivery = (isFinite(dRaw) && dRaw > 0) ? dRaw : 0;
        if (delivery <= 0 && base < 27) delivery = 2.99;
        return { raw: +(base + delivery).toFixed(2) };
    }

    let lmpModalSku = '';
    let lmpSaveTimer = null;
    let lmpSaveInFlight = false;
    let lmpSaveQueued = false;

    function openLmpModal(sku, entries) {
        lmpModalSku = sku || '';
        document.getElementById('lmpModalSku').textContent = lmpModalSku;
        $('#lmpNewPrice').val('');
        $('#lmpNewDelivery').val('');
        $('#lmpNewLink').val('');
        const tbody = $('#lmpEntriesContainer');
        tbody.empty();
        (Array.isArray(entries) ? entries : []).forEach(function(entry) {
            appendLmpTableRow(
                tbody,
                entry.price !== undefined && entry.price !== null ? entry.price : '',
                entry.delivery !== undefined && entry.delivery !== null ? entry.delivery : '',
                entry.link || '',
                !!entry.ignored,
                entry.source_sku || ''
            );
        });
        updateLmpLowestHighlight();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('lmpModal')).show();
    }

    function getLmpRowEffectivePrice(tr) {
        const num = parseFloat($(tr).find('.lmp-price').val());
        if (isNaN(num)) return null;
        let delivery = parseFloat($(tr).find('.lmp-delivery').val());
        if (isNaN(delivery) || delivery < 0) delivery = 0;
        if (delivery <= 0 && num < 27) delivery = 2.99;
        return num + delivery;
    }

    function appendLmpTableRow(tbody, price, delivery, link, ignored, sourceSku) {
        const tr = $('<tr class="lmp-entry-row">' +
            '<td class="lmp-num text-center align-middle"></td>' +
            '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-price border-0 bg-transparent" style="max-width:100px" placeholder="Price"> <span class="lmp-lowest-badge"></span></td>' +
            '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm lmp-delivery border-0 bg-transparent" style="max-width:90px" placeholder="0.00"></td>' +
            '<td class="align-middle text-center"><span class="lmp-price-d text-muted">—</span></td>' +
            '<td class="align-middle"><input type="text" class="form-control form-control-sm lmp-link d-inline-block me-1" style="max-width:200px" placeholder="https://..."> <a href="#" class="btn btn-sm btn-outline-primary lmp-open-link" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i></a></td>' +
            '<td class="align-middle text-center"><input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"></td>' +
            '<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger lmp-remove-row"><i class="fas fa-trash-alt"></i></button></td></tr>');
        tr.find('.lmp-price').val(price !== '' && price != null ? price : '');
        tr.find('.lmp-delivery').val(delivery !== '' && delivery != null ? delivery : '');
        tr.find('.lmp-link').val(link || '');
        tr.data('source-sku', sourceSku || lmpModalSku || '');
        tr.data('ignored', ignored ? 1 : 0);
        if (ignored) {
            tr.addClass('lmp-ignored-row');
            tr.find('.lmp-ignore-cb').prop('checked', true);
        }
        tbody.append(tr);
        updateLmpPriceD(tr);
        renumberLmpRows();
    }

    function updateLmpPriceD(tr) {
        const total = getLmpRowEffectivePrice(tr);
        const $el = $(tr).find('.lmp-price-d');
        if (total === null) $el.text('—').addClass('text-muted');
        else $el.text('$' + Number(total).toFixed(2)).removeClass('text-muted');
    }

    function renumberLmpRows() {
        $('#lmpEntriesContainer .lmp-entry-row').each(function(i) {
            $(this).find('.lmp-num').text(i + 1);
        });
    }

    function updateLmpLowestHighlight() {
        let minVal = null;
        let minTr = null;
        $('#lmpEntriesContainer .lmp-entry-row').each(function() {
            const tr = $(this);
            tr.removeClass('lmp-lowest-row');
            tr.find('.lmp-lowest-badge').empty();
            if (tr.hasClass('lmp-ignored-row') || tr.data('ignored') == 1) return;
            const total = getLmpRowEffectivePrice(tr);
            if (total === null) return;
            if (minVal === null || total < minVal) {
                minVal = total;
                minTr = tr;
            }
        });
        if (minTr) {
            minTr.addClass('lmp-lowest-row');
            minTr.find('.lmp-lowest-badge').html(' <span class="badge bg-info">LOWEST</span>');
        }
    }

    function collectLmpModalEntries() {
        const entries = [];
        $('#lmpEntriesContainer .lmp-entry-row').each(function() {
            const $tr = $(this);
            const price = $tr.find('.lmp-price').val();
            const delivery = $tr.find('.lmp-delivery').val();
            const link = $tr.find('.lmp-link').val();
            if (!price && !link && !delivery) return;
            const deliveryNum = delivery !== '' && delivery != null ? parseFloat(delivery) : 0;
            entries.push({
                price: price ? parseFloat(price) : null,
                delivery: (!isNaN(deliveryNum) && deliveryNum > 0) ? deliveryNum : 0,
                link: link ? String(link).trim() : null,
                ignored: $tr.hasClass('lmp-ignored-row') || $tr.data('ignored') == 1,
                source_sku: $tr.data('source-sku') || lmpModalSku
            });
        });
        return entries;
    }

    function syncLmpModalToTable(entries) {
        if (!table || !lmpModalSku) return;
        // A new LMP changes this SKU's cap, so the memoized value is no longer valid.
        temuClearCapMemo();
        let best = null;
        (entries || []).forEach(function(e) {
            const meta = temuLmpEntryEffective(e);
            if (!meta) return;
            if (!best || meta.raw < best.raw) best = meta;
        });
        const raw = best ? best.raw : null;
        const recovery = raw != null ? temuLmpRecovery(raw) : null;
        let lowestLink = null;
        (entries || []).forEach(function(e) {
            const meta = temuLmpEntryEffective(e);
            if (meta && raw != null && +Number(meta.raw).toFixed(2) === +Number(raw).toFixed(2) && !lowestLink) {
                lowestLink = e.link || null;
            }
        });
        table.getRows().forEach(function(row) {
            const d = row.getData() || {};
            const sku = String(d.sku || d['(Child) sku'] || '');
            if (sku !== String(lmpModalSku)) return;
            const next = {
                lmp_entries: entries,
                lmp_raw: raw,
                lmp: recovery,
                lmp_link: lowestLink
            };
            const cap = typeof temuSpriceCapCompute === 'function'
                ? temuSpriceCapCompute(Object.assign({}, d, next))
                : null;
            if (cap && cap.sprice > 0) {
                next.sprice = cap.sprice;
                next.SPRICE = cap.sprice;
                next.sprice_labels = cap.labels;
                next.sprice_lmp_alert = cap.lmpAlert;
                next.s_base_price = temuSBaseFromSprice(cap.sprice) || null;
                next.s_r_price = temuSRPriceFromSprice(cap.sprice) || null;
                const merged = Object.assign({}, d, next);
                const sgpft = temuSgpftPercent(merged);
                const sgroi = temuSgroiPercent(merged);
                const snpft = temuSnpftPercent(merged);
                const snroi = temuSnroiPercent(merged);
                next.sgpft_percent = sgpft != null ? +sgpft.toFixed(2) : null;
                next.sgroi_percent = sgroi != null ? +sgroi.toFixed(2) : null;
                next.snpft_percent = snpft != null ? +snpft.toFixed(2) : null;
                next.snroi_percent = snroi != null ? +snroi.toFixed(2) : null;
            }
            row.update(next);
            row.reformat();
        });
        if (typeof updateSummary === 'function') updateSummary();
    }

    function saveLmpEntriesNow() {
        if (!lmpModalSku) return;
        if (lmpSaveInFlight) {
            lmpSaveQueued = true;
            return;
        }
        const entries = collectLmpModalEntries();
        lmpSaveInFlight = true;
        $.ajax({
            url: '{{ route("temu.lmp.save") }}',
            method: 'POST',
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            data: JSON.stringify({
                sku: lmpModalSku,
                lmp_entries: entries
            }),
            success: function(response) {
                if (response && response.success) {
                    syncLmpModalToTable(entries);
                } else {
                    showToast((response && (response.message || response.error)) || 'Failed to save LMP', 'error');
                }
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Failed to save LMP';
                showToast(msg, 'error');
            },
            complete: function() {
                lmpSaveInFlight = false;
                if (lmpSaveQueued) {
                    lmpSaveQueued = false;
                    saveLmpEntriesNow();
                }
            }
        });
    }

    function scheduleLmpAutosave() {
        clearTimeout(lmpSaveTimer);
        lmpSaveTimer = setTimeout(saveLmpEntriesNow, 400);
    }

    function openNewTemuoneEditLinksModal(row) {
        newTemuoneEditLinksRow = row;
        const d = row.getData();
        const sku = d['(Child) sku'] || d.sku || '';
        document.getElementById('newTemuoneEditLinksSku').textContent = sku;
        document.getElementById('newTemuoneSellerLinkInput').value = d['S Link'] || '';
        document.getElementById('newTemuoneBuyerLinkInput').value = d['B Link'] || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('newTemuoneEditLinksModal')).show();
    }

    let blueTriangleFilterActive = false;
    let lmpCapFilterActive = false;
    let amzCapFilterActive = false;
    let ebCapFilterActive = false;
    let zeroSoldFilterActive = false;
    let moreSoldFilterActive = false;
    let priceGtLmpFilterActive = false;
    let priceLt80LmpFilterActive = false;

    // Same LMP basis /temu2-decrease passes in: lmp is the recovery price, lmp_raw the landed one.
    function temuBadgeLmpValue(row) {
        return parseFloat(row && (row.lmp_price || row.lmp || row.LMP)) || 0;
    }

    function temuHasPriceGtLmp(row) {
        return !!(window.PriceGtLmpBadge
            && PriceGtLmpBadge.hasRedTriangle(row, 'temu_price', temuBadgeLmpValue));
    }

    function temuHasPriceLt80Lmp(row) {
        return !!(window.PriceLt80LmpBadge
            && PriceLt80LmpBadge.hasPurpleTriangle(row, 'temu_price'));
    }

    function temuRowCapLabels(row) {
        const cap = temuSpriceCapResult(row);
        return (cap && cap.labels) || [];
    }

    function temuHasAmzCap(row) {
        return temuRowCapLabels(row).indexOf('Amz') !== -1;
    }

    function temuHasEbCap(row) {
        return temuRowCapLabels(row).indexOf('EB') !== -1;
    }

    function temuHasLmpCap(row) {
        const cap = temuSpriceCapResult(row);
        if (!cap || !(cap.sprice > 0)) return false;
        return !!cap.lmpAlert || (cap.lmp > 0 && cap.sprice >= cap.lmp);
    }

    /** Blue triangle: S PRC differs from the live T Price. */
    function temuHasBlueTriangle(row) {
        const sprice = temuDisplayedSprice(row);
        const live = parseFloat(row && (row.temu_price || row.t_price)) || 0;
        return sprice > 0 && live > 0 && Math.round(sprice * 100) !== Math.round(live * 100);
    }

    function syncBadgeOutlines() {
        const pairs = [
            ['#zero-sold-count-badge', zeroSoldFilterActive],
            ['#more-sold-count-badge', moreSoldFilterActive],
            ['#newtemuone-blue-triangle-badge', blueTriangleFilterActive],
            ['#newtemuone-lmp-cap-badge', lmpCapFilterActive],
            ['#temu-amz-cap-badge', amzCapFilterActive],
            ['#temu-eb-cap-badge', ebCapFilterActive]
        ];
        pairs.forEach(function(p) {
            $(p[0]).css('box-shadow', p[1] ? '0 0 0 3px rgba(13,110,253,0.55)' : 'none');
        });
        // These two paint their own active state (yellow outline) via the shared modules.
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.setOutline(
                document.getElementById('newtemuone-price-gt-lmp-badge'), priceGtLmpFilterActive
            );
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.setOutline(
                document.getElementById('newtemuone-price-lt80-lmp-badge'), priceLt80LmpFilterActive
            );
        }
    }

    /** Badge row — all totals follow the filters currently applied to the table. */
    function updateSummary() {
        if (!table) return;
        const rows = table.getData('active') || [];

        let rowsCount = 0;
        let zeroSold = 0;
        let moreSold = 0;
        let totalL30 = 0;
        let totalViews = 0;
        let gpftSum = 0;
        let gpftBase = 0;
        let gpftLp = 0;
        let blueTriangle = 0;
        let lmpCapped = 0;
        let amzCap = 0;
        let ebCap = 0;

        rows.forEach(function(row) {
            rowsCount++;
            const inv = parseFloat(row.INV) || 0;
            const l30 = parseInt(row.temu_l30, 10) || 0;
            const lp = parseFloat(row.lp) || 0;
            const tPrice = parseFloat(row.t_price) || 0;

            totalL30 += l30;
            totalViews += parseInt(row.views, 10) || 0;
            if (inv > 0 && l30 === 0) zeroSold++;
            if (inv > 0 && l30 > 0) moreSold++;

            const gpft = temuGpftDollars(row);
            if (gpft != null && tPrice > 0) {
                gpftSum += gpft;
                gpftBase += tPrice;
                gpftLp += lp;
            }

            // One cap lookup feeds all four flags — the helpers would each redo it.
            const cap = temuSpriceCapResult(row);
            const sprice = (cap && cap.sprice > 0) ? cap.sprice : 0;
            if (sprice > 0) {
                const live = parseFloat(row.temu_price || row.t_price) || 0;
                if (live > 0 && Math.round(sprice * 100) !== Math.round(live * 100)) blueTriangle++;
                if (cap.lmpAlert || (cap.lmp > 0 && sprice >= cap.lmp)) lmpCapped++;
                const labels = cap.labels || [];
                if (labels.indexOf('Amz') !== -1) amzCap++;
                if (labels.indexOf('EB') !== -1) ebCap++;
            }
        });

        const cvr = totalViews > 0 ? (totalL30 / totalViews) * 100 : 0;
        const gpftPct = gpftBase > 0 ? (gpftSum / gpftBase) * 100 : 0;
        const groiPct = gpftLp > 0 ? (gpftSum / gpftLp) * 100 : 0;
        $('#rows-count-badge').text('Rows: ' + rowsCount.toLocaleString());
        $('#zero-sold-count-badge').text('0 Sold: ' + zeroSold.toLocaleString());
        $('#more-sold-count-badge').text('> 0 Sold: ' + moreSold.toLocaleString());
        $('#total-l30-badge').text('L30: ' + totalL30.toLocaleString());
        $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
        $('#avg-cvr-badge').text('CVR: ' + cvr.toFixed(1) + '%');
        $('#avg-gpft-badge').text('GPFT: ' + Math.round(gpftPct) + '%');
        $('#avg-groi-badge').text('GROI: ' + Math.round(groiPct) + '%');
        $('#newtemuone-blue-triangle-badge').html(
            '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangle.toLocaleString()
        );
        $('#newtemuone-lmp-cap-badge').text('LMP cap ' + lmpCapped.toLocaleString());
        $('#temu-amz-cap-badge').text('Amz ' + amzCap.toLocaleString());
        $('#temu-eb-cap-badge').text('EB ' + ebCap.toLocaleString());

        // Counted over the whole dataset, not the filtered view — same as /temu2-decrease.
        const allRows = table.getData();
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.update(
                '#newtemuone-price-gt-lmp-badge', allRows, '', 'temu_price', temuBadgeLmpValue
            );
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.update('#newtemuone-price-lt80-lmp-badge', allRows, '', 'temu_price');
        }
        syncBadgeOutlines();
    }
    window.updateSummary = updateSummary;

    function applyFilters() {
        if (!table) return;

        const inventoryFilter = $('#inventory-filter').val();
        const dilFilter = $('#dil-filter').val();
        const skuSearch = $('#sku-search').val() || '';
        const parentSearch = $('#parent-search').val() || '';

        table.clearFilter();

        if (inventoryFilter === 'zero') {
            table.addFilter('INV', '=', 0);
        } else if (inventoryFilter === 'more') {
            table.addFilter('INV', '>', 0);
        }

        if (skuSearch) {
            table.addFilter('(Child) sku', 'like', skuSearch);
        }
        if (parentSearch) {
            table.addFilter('Parent', 'like', parentSearch);
        }

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

        if (zeroSoldFilterActive) {
            table.addFilter(function(data) {
                return (parseFloat(data.INV) || 0) > 0 && (parseInt(data.temu_l30, 10) || 0) === 0;
            });
        }
        if (moreSoldFilterActive) {
            table.addFilter(function(data) {
                return (parseFloat(data.INV) || 0) > 0 && (parseInt(data.temu_l30, 10) || 0) > 0;
            });
        }
        if (blueTriangleFilterActive) {
            table.addFilter(function(data) { return temuHasBlueTriangle(data); });
        }
        if (lmpCapFilterActive) {
            table.addFilter(function(data) { return temuHasLmpCap(data); });
        }
        if (amzCapFilterActive) {
            table.addFilter(function(data) { return temuHasAmzCap(data); });
        }
        if (ebCapFilterActive) {
            table.addFilter(function(data) { return temuHasEbCap(data); });
        }
        if (priceGtLmpFilterActive) {
            table.addFilter(function(data) { return temuHasPriceGtLmp(data); });
        }
        if (priceLt80LmpFilterActive) {
            table.addFilter(function(data) { return temuHasPriceLt80Lmp(data); });
        }

        updateSummary();
    }

    $(document).ready(function() {
        table = new Tabulator('#new-temuone-table', {
            ajaxURL: '{{ route("newtemuone.data.json") }}',
            ajaxSorting: false,
            layout: 'fitData',
            layoutColumnsOnNewData: true,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            langs: {
                default: {
                    pagination: {
                        page_size: 'SKU Count'
                    }
                }
            },
            initialSort: [{
                column: 'temu_l30',
                dir: 'desc'
            }],
            columns: [
                {
                    title: 'SKU',
                    field: '(Child) sku',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Search SKU...',
                    cssClass: 'text-primary fw-bold',
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue() || '';
                        return `<span>${sku}</span><i class="fa fa-copy text-secondary copy-sku-btn"
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;"
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                    }
                },
                {
                    title: 'Links',
                    field: 'links_column',
                    frozen: true,
                    width: 55,
                    hozAlign: 'center',
                    visible: true,
                    headerSort: false,
                    tooltip: 'Double-click to add / edit links',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData['B Link'] || '';
                        const sellerLink = rowData['S Link'] || '';

                        let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                        if (sellerLink) {
                            html += '<a href="' + String(sellerLink).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                        }
                        if (buyerLink) {
                            html += '<a href="' + String(buyerLink).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        openNewTemuoneEditLinksModal(cell.getRow());
                    }
                },
                {
                    title: 'INV',
                    field: 'INV',
                    hozAlign: 'center',
                    width: 50,
                    sorter: 'number'
                },
                {
                    title: 'OV L30',
                    field: 'L30',
                    hozAlign: 'center',
                    width: 50,
                    sorter: 'number'
                },
                {
                    title: 'L30',
                    field: 'temu_l30',
                    hozAlign: 'center',
                    width: 50,
                    sorter: 'number',
                    headerTooltip: 'Temu L30 qty from temu_orders — same sales table and Pacific L30 window as /temu-tabulator Qty Purchased'
                },
                {
                    title: 'Views',
                    field: 'views',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'Same as /temu1-data Views: SUM(temu_view_data.product_clicks) by Goods ID; Ads API fallback when the sheet has no row'
                },
                {
                    title: 'CVR',
                    field: 'cvr_percent',
                    hozAlign: 'center',
                    width: 78,
                    sorter: 'number',
                    headerTooltip: 'CVR = (Temu L30 / Views) × 100 — same as /temu1-data. Arrow compares CVR 30 against CVR 60 (prior 30 days), the same up/down rule as the Amazon tabulator. Down + CVR < 7% subtracts 10 from the Sprc Dil Target GROI; Up + CVR > 10% adds 10.',
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        const label = (val > 3.5 ? String(Math.round(val)) : val.toFixed(1)) + '%';
                        const cell1 = '<span style="color: ' + color + '; font-weight: 600;">' + label + '</span>';
                        const row = cell.getRow().getData() || {};
                        if (row.is_parent_summary) return cell1;
                        return cell1 + temuCvrTrendArrowHtml(val, parseFloat(row.cvr_60) || 0);
                    }
                },
                {
                    title: 'Dil',
                    field: 'Dil%',
                    hozAlign: 'center',
                    sorter: 'number',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;

                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                        const dil = (OVL30 / INV) * 100;
                        let color = '';

                        if (dil < 25) color = '#dc3545';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';

                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: 'Base Price',
                    field: 'base_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'temu_metrics.base_price',
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === undefined || isNaN(value) || value === 0) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        return '<span style="font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'R Price',
                    field: 'r_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Normal Temu price (base + $2.99 when base ≤ $26.99)',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const base = parseFloat(row.base_price) || 0;
                        const rPrice = parseFloat(cell.getValue()) || 0;
                        if (!(rPrice > 0)) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        const tip = base <= 26.99
                            ? ('R Price = Base + $2.99 → $' + base.toFixed(2) + ' + $2.99 = $' + rPrice.toFixed(2))
                            : ('R Price = Base (no +$2.99, base > $26.99) → $' + base.toFixed(2));
                        return '<span style="font-weight: 600;" title="' + tip + '">$' + rPrice.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'T Price',
                    field: 't_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Temu Price = (Base × 1.1364); +$2.99 if that result ≤ $26.99',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const base = parseFloat(row.base_price) || 0;
                        const tPrice = parseFloat(cell.getValue()) || 0;
                        if (!(tPrice > 0) || !(base > 0)) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        const afterMult = +(base * 1.1364).toFixed(2);
                        const tip = afterMult <= 26.99
                            ? ('T Price = (Base × 1.1364) + $2.99 → $' + base.toFixed(2) + ' × 1.1364 = $' + afterMult.toFixed(2) + ' + $2.99 = $' + tPrice.toFixed(2))
                            : ('T Price = (Base × 1.1364) → $' + base.toFixed(2) + ' × 1.1364 = $' + tPrice.toFixed(2) + ' (no +$2.99, result > $26.99)');
                        return '<span style="font-weight: 600;" title="' + tip + '">$' + tPrice.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'Std Price',
                    field: 'STANDARD_PRICE',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Standard Price — same amazon_data_view.STANDARD_PRICE source as /temu2-decrease',
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === undefined || isNaN(value) || value <= 0) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        return '<span style="font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'LMP',
                    field: 'lmp_raw',
                    hozAlign: 'center',
                    width: 88,
                    sorter: 'number',
                    headerTooltip: 'Lowest LMP from the modal: Price + Delivery (Del $2.99 when Price < $27). Same as Temu 2.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const raw = parseFloat(cell.getValue());
                        const displayVal = raw > 0 ? raw : null;
                        const display = displayVal != null
                            ? (displayVal % 1 === 0 ? displayVal.toLocaleString() : displayVal.toFixed(2))
                            : '-';
                        const sku = String(row.sku || row['(Child) sku'] || '').replace(/"/g, '&quot;');
                        const count = Array.isArray(row.lmp_entries) ? row.lmp_entries.length : 0;
                        const title = count > 0
                            ? ('Lowest LMP $' + (displayVal != null ? Number(displayVal).toFixed(2) : '-') + ' (' + count + ' entries) - click to edit')
                            : 'Click to add LMP';
                        return '<span class="lmp-display" title="' + title.replace(/"/g, '&quot;') + '">'
                            + (display !== '-' ? display : '<span style="color: #999;">-</span>')
                            + '</span> <button type="button" class="btn btn-sm btn-link p-0 lmp-eye-btn" data-sku="' + sku + '" title="' + title.replace(/"/g, '&quot;') + '"><i class="fas fa-info-circle text-info"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest('.lmp-eye-btn')) {
                            e.stopPropagation();
                            const row = cell.getRow().getData();
                            openLmpModal(row.sku || row['(Child) sku'], row.lmp_entries || []);
                        }
                    }
                },
                {
                    title: 'S PRC',
                    field: 'sprice',
                    hozAlign: 'center',
                    width: 88,
                    sorter: 'number',
                    headerTooltip: 'Same formula as /temu2-decrease: Sprc Dil from the /temu1-data Dil table (including Temu L30 = 0), then the lowest of eBay, Amazon, and LMP. Saved on temu_data_view.',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const model = typeof temuSpriceCellModel === 'function'
                            ? temuSpriceCellModel(rowData)
                            : { value: parseFloat(cell.getValue()) || 0, labels: [], lmpAlert: false, lmp: 0, amz: 0, ebay: 0 };
                        const value = model.value;
                        const live = parseFloat(rowData.temu_price || rowData.t_price) || 0;
                        const lmp = model.lmp || temuParseMoney(rowData.lmp_raw);
                        if (!(value > 0)) return '<span style="color: #6c757d;">—</span>';
                        const formatted = '$' + value.toFixed(2);
                        const overLmp = model.lmpAlert || (lmp > 0 && value >= lmp);
                        const priceHtml = overLmp
                            ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                            : formatted;
                        const redTri = overLmp
                            ? '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP $'
                                + Number(lmp || 0).toFixed(2) + '"></i>'
                            : '';
                        const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ T Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        let capHtml = '';
                        (model.labels || []).forEach(function(lbl) {
                            const ref = lbl === 'Amz' ? model.amz : model.ebay;
                            const name = lbl === 'Amz' ? 'Amazon' : 'eBay';
                            capHtml += '<span class="temu-sprice-cap-lbl" title="S PRC capped to ' + name + ' $'
                                + Number(ref).toFixed(2) + '">' + lbl + '</span>';
                        });
                        return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                            + priceHtml + capHtml + redTri + blueTri + '</span>';
                    }
                },
                {
                    title: 'S Base Prc',
                    field: 's_base_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'Base Price equivalent of S PRC: S PRC inverted through the T Price rule (÷ 1.1364, less $2.99 when S PRC included it)',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = typeof temuDisplayedSprice === 'function'
                            ? temuDisplayedSprice(rowData)
                            : (parseFloat(rowData.sprice) || 0);
                        const sBase = temuSBaseFromSprice(sprice);
                        if (!(sBase > 0)) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        const tip = 'S Base Prc from S PRC $' + sprice.toFixed(2)
                            + ' → $' + sBase.toFixed(2);
                        return '<span style="font-weight: 600;" title="' + tip + '">$' + sBase.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'S R Prc',
                    field: 's_r_price',
                    hozAlign: 'center',
                    width: 70,
                    sorter: 'number',
                    headerTooltip: 'R Price equivalent of S PRC: S Base Prc + $2.99 when that base ≤ $26.99',
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = typeof temuDisplayedSprice === 'function'
                            ? temuDisplayedSprice(rowData)
                            : (parseFloat(rowData.sprice) || 0);
                        const sBase = temuSBaseFromSprice(sprice);
                        const sRPrice = temuSRPriceFromSprice(sprice);
                        if (!(sRPrice > 0)) {
                            return '<span style="color: #6c757d;">—</span>';
                        }
                        const tip = sBase <= 26.99
                            ? ('S R Prc = S Base Prc + $2.99 → $' + sBase.toFixed(2) + ' + $2.99 = $' + sRPrice.toFixed(2))
                            : ('S R Prc = S Base Prc (no +$2.99, base > $26.99) → $' + sBase.toFixed(2));
                        return '<span style="font-weight: 600;" title="' + tip + '">$' + sRPrice.toFixed(2) + '</span>';
                    }
                },
                {
                    title: 'GPFT',
                    field: 'profit_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'GPFT% = Gpft ÷ T Price. Gpft = (R Price × Temu margin) − Temu Ship − LP. Margin from marketplace_percentages "Temu".',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuGpftPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const gpft = temuGpftDollars(row);
                        const tip = 'Gpft $' + gpft.toFixed(2)
                            + ' ÷ T Price $' + (parseFloat(row.t_price) || 0).toFixed(2)
                            + ' (margin ' + Math.round(temuRowMargin(row) * 100) + '%)';
                        return temuPercentCell(value, 'pft', tip);
                    }
                },
                {
                    title: 'GROI',
                    field: 'roi_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'GROI% = Gpft ÷ LP. Gpft = (R Price × Temu margin) − Temu Ship − LP. Margin from marketplace_percentages "Temu".',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuGroiPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const gpft = temuGpftDollars(row);
                        const tip = 'Gpft $' + gpft.toFixed(2)
                            + ' ÷ LP $' + (parseFloat(row.lp) || 0).toFixed(2)
                            + ' (margin ' + Math.round(temuRowMargin(row) * 100) + '%)';
                        return temuPercentCell(value, 'roi', tip);
                    }
                },
                {
                    title: 'SGPFT',
                    field: 'sgpft_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'SGPFT% = SPFT ÷ S PRC. SPFT = (S R Prc × Temu margin) − Temu Ship − LP. Margin from marketplace_percentages "Temu".',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuSgpftPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const spft = temuSpftDollars(row);
                        const tip = 'SPFT $' + spft.toFixed(2)
                            + ' ÷ S PRC $' + temuDisplayedSprice(row).toFixed(2)
                            + ' (margin ' + Math.round(temuRowMargin(row) * 100) + '%)';
                        return temuPercentCell(value, 'pft', tip);
                    }
                },
                {
                    title: 'SGROI',
                    field: 'sgroi_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'SGROI% = SPFT ÷ LP. SPFT = (S R Prc × Temu margin) − Temu Ship − LP. Sprc Dil back-solves S PRC so this matches the Dil slab Target GROI.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuSgroiPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const spft = temuSpftDollars(row);
                        const tip = 'SPFT $' + spft.toFixed(2)
                            + ' ÷ LP $' + (parseFloat(row.lp) || 0).toFixed(2)
                            + ' (margin ' + Math.round(temuRowMargin(row) * 100) + '%)';
                        return temuPercentCell(value, 'roi', tip);
                    }
                },
                {
                    title: 'NPFT',
                    field: 'npft_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'NPFT% = NPFT ÷ T Price. NPFT = Gpft − (T Price × Ads%). Ads% is the channel Ads badge — same formula as /temu2-decrease.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuNpftPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const npft = temuNpftDollars(row);
                        const tip = 'NPFT $' + npft.toFixed(2)
                            + ' ÷ T Price $' + (parseFloat(row.t_price) || 0).toFixed(2)
                            + ' (Ads ' + temuAdsPercentForNet().toFixed(2) + '%)';
                        return temuPercentCell(value, 'pft', tip);
                    }
                },
                {
                    title: 'NROI',
                    field: 'nroi_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'NROI% = NPFT ÷ LP. NPFT = Gpft − (T Price × Ads%). Same formula as /temu2-decrease.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuNroiPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const npft = temuNpftDollars(row);
                        const tip = 'NPFT $' + npft.toFixed(2)
                            + ' ÷ LP $' + (parseFloat(row.lp) || 0).toFixed(2)
                            + ' (Ads ' + temuAdsPercentForNet().toFixed(2) + '%)';
                        return temuPercentCell(value, 'roi', tip);
                    }
                },
                {
                    title: 'SNPFT',
                    field: 'snpft_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'SNPFT% = SNPFT ÷ S PRC. SNPFT = SPFT − (S PRC × Ads%). Same formula as /temu2-decrease.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuSnpftPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const sprice = temuDisplayedSprice(row);
                        const snpft = temuSnpftDollars(row, sprice);
                        const tip = 'SNPFT $' + snpft.toFixed(2)
                            + ' ÷ S PRC $' + sprice.toFixed(2)
                            + ' (Ads ' + temuAdsPercentForNet().toFixed(2) + '%)';
                        return temuPercentCell(value, 'pft', tip);
                    }
                },
                {
                    title: 'SNROI',
                    field: 'snroi_percent',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    headerTooltip: 'SNROI% = SNPFT ÷ LP. SNPFT = SPFT − (S PRC × Ads%). Same formula as /temu2-decrease.',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const value = temuSnroiPercent(row);
                        if (value == null) return '<span style="color: #6c757d;">—</span>';
                        const snpft = temuSnpftDollars(row);
                        const tip = 'SNPFT $' + snpft.toFixed(2)
                            + ' ÷ LP $' + (parseFloat(row.lp) || 0).toFixed(2)
                            + ' (Ads ' + temuAdsPercentForNet().toFixed(2) + '%)';
                        return temuPercentCell(value, 'roi', tip);
                    }
                }
            ]
        });

        table.on('dataLoaded', function() {
            temuClearCapMemo();
            applyFilters();
        });

        // Every render starts from a clean memo, so a Sprc Dil slab edit (which redraws
        // the table) can never be served a stale cap.
        table.on('renderStarted', temuClearCapMemo);
        table.on('dataChanged', temuClearCapMemo);

        // Authoritative badge refresh: fires after Tabulator finishes filtering.
        table.on('dataFiltered', function() {
            updateSummary();
        });

        $('#sku-search, #parent-search').on('keyup', applyFilters);
        $('#inventory-filter, #dil-filter').on('change', applyFilters);

        // Badge filters — one at a time, click again to clear (same as /temu2-decrease).
        const badgeFilters = [
            ['#zero-sold-count-badge', 'zeroSold'],
            ['#more-sold-count-badge', 'moreSold'],
            ['#newtemuone-blue-triangle-badge', 'blueTriangle'],
            ['#newtemuone-lmp-cap-badge', 'lmpCap'],
            ['#temu-amz-cap-badge', 'amzCap'],
            ['#temu-eb-cap-badge', 'ebCap'],
            ['#newtemuone-price-gt-lmp-badge', 'priceGtLmp'],
            ['#newtemuone-price-lt80-lmp-badge', 'priceLt80Lmp']
        ];
        badgeFilters.forEach(function(pair) {
            $(document).on('click', pair[0], function(e) {
                // The trend dot belongs to rolling history, not the filter.
                if (e.target && e.target.closest && e.target.closest('.summary-trend-dot')) return;
                const want = pair[1];
                const next = {
                    zeroSold: false,
                    moreSold: false,
                    blueTriangle: false,
                    lmpCap: false,
                    amzCap: false,
                    ebCap: false,
                    priceGtLmp: false,
                    priceLt80Lmp: false
                };
                const current = {
                    zeroSold: zeroSoldFilterActive,
                    moreSold: moreSoldFilterActive,
                    blueTriangle: blueTriangleFilterActive,
                    lmpCap: lmpCapFilterActive,
                    amzCap: amzCapFilterActive,
                    ebCap: ebCapFilterActive,
                    priceGtLmp: priceGtLmpFilterActive,
                    priceLt80Lmp: priceLt80LmpFilterActive
                };
                next[want] = !current[want];
                zeroSoldFilterActive = next.zeroSold;
                moreSoldFilterActive = next.moreSold;
                blueTriangleFilterActive = next.blueTriangle;
                lmpCapFilterActive = next.lmpCap;
                amzCapFilterActive = next.amzCap;
                ebCapFilterActive = next.ebCap;
                priceGtLmpFilterActive = next.priceGtLmp;
                priceLt80LmpFilterActive = next.priceLt80Lmp;
                applyFilters();
            });
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        $(document).on('click', '#newTemuoneSaveLinksBtn', function() {
            if (!newTemuoneEditLinksRow) return;
            const d = newTemuoneEditLinksRow.getData();
            const sku = d['(Child) sku'] || d.sku || '';
            const sellerLink = document.getElementById('newTemuoneSellerLinkInput').value.trim();
            const buyerLink = document.getElementById('newTemuoneBuyerLinkInput').value.trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: '{{ route("newtemuone.save.links") }}',
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
                        newTemuoneEditLinksRow.update({
                            'S Link': res.seller_link || '',
                            'B Link': res.buyer_link || ''
                        }).then(function() {
                            newTemuoneEditLinksRow.reformat();
                        }).catch(function() {
                            newTemuoneEditLinksRow.reformat();
                        });
                        showToast('Links saved', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('newTemuoneEditLinksModal')).hide();
                    } else {
                        showToast((res && res.message) || 'Error saving links', 'error');
                    }
                },
                error: function(xhr) {
                    let msg = 'Error saving links';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $(document).on('click', '#lmpAddRowBtn', function() {
            const price = $('#lmpNewPrice').val();
            if (!price) {
                showToast('Price is required', 'error');
                return;
            }
            appendLmpTableRow(
                $('#lmpEntriesContainer'),
                price,
                $('#lmpNewDelivery').val(),
                $('#lmpNewLink').val(),
                false,
                lmpModalSku
            );
            $('#lmpNewPrice').val('');
            $('#lmpNewDelivery').val('');
            $('#lmpNewLink').val('');
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('input change', '#lmpEntriesContainer .lmp-price, #lmpEntriesContainer .lmp-delivery, #lmpEntriesContainer .lmp-link', function() {
            updateLmpPriceD($(this).closest('tr'));
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('change', '#lmpEntriesContainer .lmp-ignore-cb', function() {
            const $tr = $(this).closest('tr');
            if (this.checked) {
                $tr.addClass('lmp-ignored-row').data('ignored', 1);
            } else {
                $tr.removeClass('lmp-ignored-row').data('ignored', 0);
            }
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('click', '#lmpEntriesContainer .lmp-remove-row', function() {
            $(this).closest('tr').remove();
            renumberLmpRows();
            updateLmpLowestHighlight();
            scheduleLmpAutosave();
        });

        $(document).on('click', '#lmpEntriesContainer .lmp-open-link', function(e) {
            const href = $(this).closest('tr').find('.lmp-link').val();
            if (!href) {
                e.preventDefault();
                return;
            }
            $(this).attr('href', href);
        });
    });
</script>
@endsection
