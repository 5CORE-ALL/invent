{{--
  Sprc Dil — same Dil → Target GROI slabs as Amazon.
  Store: {channel}_dil_vs_groi via /channel-promo-pricing/{channel}/dil-groi.
  Dil = listing Dil (Σ OV L30 ÷ Σ INV), same as the Dil column.
  Amazon / eBay 1–3 / Temu 2–3 / Doba Pickup: every INV > 0 SKU uses the Dil-matching slab (including 0 Sold).
  eBay 1–3: Dil below the first slab or above the last slab uses the nearest slab (0 Sold Dil = 0 and fast-seller Dil > last To).
  eBay 1–3 CVR overlay is level-only (CVR < Down → −10 GROI; CVR > Up → +10 GROI). Temu 1–2 also use the overlay; Reverb / Faire / TikTok / Shopify B2C are level-only.
  Macys: Dil-matching when Dil is in a slab. If Dil is out of box and 0 Sold, use min Target GROI.
  If that Dil / min-ROI S PRC is below A Price, S PRC = A Price (do not keep Std Prc).
  Every other Sprc Dil page: Dil-matching when sold > 0; 0 Sold uses the minimum Target GROI in the table.
  Dil slab edits and table load recalculate display only.
  Save and Apply deletes old S PRC (saves 0), then writes the new Dil S PRC.
  Macys / Purchasing Power persist in the background (page can close).
  Purchasing Power also pushes listed price via MCM when S PRC ≠ PP Price.
  Live push on other pages is only for saved S PRC ≠ Price.
--}}
@php
    $ebaySprcDilPart = $ebaySprcDilPart ?? 'all';
    $ebaySprcDilChannel = $ebaySprcDilChannel ?? 'ebay1';
    $ebaySprcDilZeroSoldUsesMinGroi = !in_array($ebaySprcDilChannel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3', 'doba_withoutship', 'macys', 'macy', 'temu2', 'temu3'], true);
    $ebaySprcDilCvrGroiAdj = in_array($ebaySprcDilChannel, ['ebay1', 'ebay2', 'ebay2op', 'ebay3', 'temu', 'temu2', 'reverb', 'faire', 'tiktok', 'tiktok2', 'shopify_b2c', 'shopify_b2b'], true);
    $ebaySprcDilIsMacys = in_array($ebaySprcDilChannel, ['macys', 'macy'], true);
    $ebaySprcDilHideCvrPie = in_array($ebaySprcDilChannel, ['macys', 'macy', 'purchasing_power', 'wayfair', 'doba', 'doba_withoutship', 'aliexpress', 'shein', 'bestbuy', 'newegg', 'topdawg', 'walmart', 'pls', 'depop', 'vinted', 'mercari_wship', 'mercari_woship'], true);
    $ebaySprcDilExcludeShip = in_array($ebaySprcDilChannel, ['purchasing_power', 'wayfair', 'doba_withoutship', 'faire', 'topdawg', 'fb_marketplace', 'shopify_b2b', 'mercari_woship'], true);
    $ebaySprcDilSoldLabel = match ($ebaySprcDilChannel) {
        'temu', 'temu2', 'temu3' => 'Temu L30',
        'macys', 'macy' => 'MC L30',
        'purchasing_power' => 'PP L30',
        'wayfair' => 'A L30',
        'reverb' => 'RV L30',
        'doba', 'doba_withoutship' => 'Doba L30',
        'aliexpress', 'shein', 'faire' => 'AL30',
        'tiktok', 'tiktok2' => 'TT L30',
        'fb_marketplace' => 'FB L30',
        'topdawg' => 'TD L30',
        'shopify_b2c' => 'B2C L30',
        'shopify_b2b' => 'B2B L30',
        'bestbuy' => 'BB L30',
        'newegg' => 'L30',
        'walmart' => 'W L30',
        'pls' => 'P L30',
        'depop' => 'L30',
        'vinted' => 'V L30',
        'mercari_wship', 'mercari_woship' => 'L30',
        default => 'E L30',
    };
    $ebaySprcDilPageLabel = match ($ebaySprcDilChannel) {
        'temu' => 'Temu',
        'temu2' => 'Temu 2',
        'temu3' => 'Temu 3',
        'ebay2' => 'eBay 2',
        'ebay3' => 'eBay 3',
        'macys', 'macy' => 'Macys',
        'purchasing_power' => 'Purchasing Power',
        'wayfair' => 'Wayfair',
        'reverb' => 'Reverb',
        'doba' => 'Doba',
        'doba_withoutship' => 'Doba Pickup',
        'aliexpress' => 'AliExpress',
        'shein' => 'Shein',
        'faire' => 'Faire',
        'tiktok' => 'TikTok',
        'tiktok2' => 'TikTok 2',
        'fb_marketplace' => 'FB Marketplace',
        'topdawg' => 'TopDawg',
        'shopify_b2c' => 'Shopify B2C',
        'shopify_b2b' => 'Shopify B2B',
        'bestbuy' => 'Best Buy',
        'newegg' => 'Newegg',
        'walmart' => 'Walmart',
        'pls' => 'PLS',
        'depop' => 'Depop',
        'vinted' => 'Vinted',
        'mercari_wship' => 'Mercari w Ship',
        'mercari_woship' => 'Mercari Pickup',
        'ebay2op' => 'eBay 2 OP',
        default => 'eBay',
    };
@endphp

@if($ebaySprcDilPart === 'css' || $ebaySprcDilPart === 'all')
        #ebay-dil-groi-table .ebay-dil-groi-input {
            max-width: 90px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
        }
        #ebay-dil-groi-table .ebay-dg-min,
        #ebay-dil-groi-table .ebay-dg-max {
            margin-left: 0;
        }
        #ebay-dil-groi-table .ebay-dil-groi-row-del {
            border: none;
            background: none;
            color: #dc3545;
            padding: 0 4px;
            line-height: 1;
            cursor: pointer;
        }
        #ebayDilGroiModal .ebay-dg-add-btn { font-size: 12px; }
        #ebay-cvr-groi-table .ebay-cvr-groi-input {
            max-width: 72px;
            display: inline-block;
            text-align: right;
            font-weight: 600;
        }
        #ebay-cvr-groi-table .ebay-dg-count {
            font-weight: 700;
            text-align: center;
        }
        #ebay-dil-groi-table .ebay-dg-count {
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        #ebayDilGroiModal .ebay-dg-rules {
            margin: 0 0 10px;
            padding-left: 1.15rem;
        }
        #ebayDilGroiModal .ebay-dg-rules li { margin-bottom: 0.28rem; }
        #ebayDilGroiModal .ebay-dg-rules-title {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        #ebayDilGroiModal .ebay-dg-pies {
            display: flex;
            gap: 10px;
            margin: 0 0 10px;
            flex-wrap: wrap;
        }
        #ebayDilGroiModal .ebay-dg-pie-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex: 1 1 280px;
            min-width: 260px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        #ebayDilGroiModal .ebay-dg-pie-canvas-wrap {
            width: 140px;
            height: 140px;
            flex: 0 0 140px;
        }
        #ebayDilGroiModal .ebay-dg-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
            max-height: 168px;
            overflow-y: auto;
        }
        #ebayDilGroiModal .ebay-dg-pie-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            line-height: 1.3;
            padding: 1px 0;
        }
        #ebayDilGroiModal .ebay-dg-pie-swatch {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex: 0 0 8px;
        }
        #ebayDilGroiModal .ebay-dg-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        #ebayDilGroiModal .ebay-dg-pie-count { font-weight: 700; min-width: 24px; text-align: right; }
        #ebayDilGroiModal .ebay-dg-pie-pct { color: #64748b; min-width: 24px; text-align: right; }
        #ebayDilGroiModal .ebay-dg-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            flex: 0 0 8px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        #ebayDilGroiModal .ebay-dg-hist-dot:hover { transform: scale(1.35); }
        #ebayDilGroiModal .ebay-dg-hist-wrap {
            display: none;
            margin: 0 0 10px;
            padding: 6px 8px 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
        }
        #ebayDilGroiModal .ebay-dg-hist-wrap.is-open { display: block; }
        #ebayDilGroiModal .ebay-dg-hist-canvas-wrap { height: 160px; }
        #ebay-dil-groi-btn {
            background: #6f42c1;
            border-color: #6f42c1;
            color: #fff;
        }
@endif

@if($ebaySprcDilPart === 'buttons' || $ebaySprcDilPart === 'all')
                    <button type="button" class="btn btn-sm" id="ebay-dil-groi-btn"
                        title="{{ !empty($ebaySprcDilIsMacys)
                            ? 'Dil-matching slab, or min GROI when Dil is out of box and 0 Sold. If that S PRC < A Price, use A Price.'
                            : ($ebaySprcDilZeroSoldUsesMinGroi
                            ? 'Dil slabs → Target GROI%.'.(!empty($ebaySprcDilCvrGroiAdj) ? ' CVR overlay (editable, with Count) adjusts Target GROI.' : '').' '.$ebaySprcDilSoldLabel.' = 0 uses the minimum Target GROI from the slabs.'
                            : 'Dil slabs → Target GROI%.'.(!empty($ebaySprcDilCvrGroiAdj) ? ' CVR overlay (editable, with Count) adjusts Target GROI.' : '').' Every INV > 0 SKU uses the Dil-matching slab.') }}">
                        <i class="fas fa-sliders-h"></i> Sprc Dil
                    </button>
@endif

@if($ebaySprcDilPart === 'modals' || $ebaySprcDilPart === 'all')
    <div class="modal fade" id="ebayDilGroiModal" tabindex="-1" aria-labelledby="ebayDilGroiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="ebayDilGroiModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs Target GROI — Sprc Dil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="ebay-dg-pies">
                        <div class="ebay-dg-pie-wrap">
                            <div class="ebay-dg-pie-canvas-wrap">
                                <canvas id="ebay-dg-dil-pie"></canvas>
                            </div>
                            <div class="ebay-dg-pie-legend" id="ebay-dg-dil-legend"></div>
                        </div>
                        @unless(!empty($ebaySprcDilHideCvrPie))
                        <div class="ebay-dg-pie-wrap">
                            <div class="ebay-dg-pie-canvas-wrap">
                                <canvas id="ebay-dg-cvr-pie"></canvas>
                            </div>
                            <div class="ebay-dg-pie-legend" id="ebay-dg-cvr-legend"></div>
                        </div>
                        @endunless
                    </div>
                    <div class="ebay-dg-hist-wrap" id="ebay-dg-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="ebay-dg-hist-title">Slab history</span>
                            <button type="button" class="btn-close" id="ebay-dg-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="ebay-dg-hist-canvas-wrap">
                            <canvas id="ebay-dg-hist"></canvas>
                        </div>
                    </div>
                    <div class="ebay-dg-rules-title">Rules — when each condition applies</div>
                    <ul class="small text-muted ebay-dg-rules">
@if($ebaySprcDilZeroSoldUsesMinGroi)
                        <li>
                            <strong>When</strong> {{ $ebaySprcDilSoldLabel }} = 0 (0 Sold) and INV &gt; 0:
                            take the <strong>minimum Target GROI from the slabs</strong>
                            (not the Dil-matching slab).
                        </li>
                        <li>
                            <strong>When</strong> {{ $ebaySprcDilSoldLabel }} &gt; 0 and Dil sits in a From–To range:
                            use that slab’s Target GROI (first match; last slab includes the To value).
                        </li>
                        @if(!empty($ebaySprcDilCvrGroiAdj))
                        <li>
                            <strong>When</strong> a SKU matches a row in the <strong>CVR overlay</strong> table:
                            apply that Adj GROI to the Target GROI (Count updates as you edit).
                        </li>
                        @endif
                        <li>
                            <strong>When</strong> a price is calculated (slab match or 0 Sold min GROI):
                            it auto-applies to <strong>S PRC</strong> and is <strong>queued for Push Prc</strong>
                            (page close OK).
                        </li>
@else
                        <li>
                            <strong>When</strong> Dil sits in a From–To range (INV &gt; 0):
                            use that slab’s Target GROI (first match; last slab includes the To value).
                        </li>
                        @if(in_array($ebaySprcDilChannel, ['ebay1', 'ebay2', 'ebay3'], true))
                        <li>
                            <strong>When</strong> Dil is below the first From or above the last To (INV &gt; 0):
                            use the <strong>nearest slab</strong> so 0 Sold and high-Dil SKUs still get a Target GROI.
                        </li>
                        @endif
                        @if(!empty($ebaySprcDilCvrGroiAdj))
                        <li>
                            <strong>When</strong> a SKU matches a row in the <strong>CVR overlay</strong> table:
                            apply that Adj GROI to the Dil slab Target GROI
                            @if(in_array($ebaySprcDilChannel, ['ebay1', 'ebay2', 'ebay3'], true))
                            from <strong>CVR% only</strong> (Down &lt; threshold decreases; Up &gt; threshold increases)
                            @endif
                            (Count updates as you edit).
                        </li>
                        @endif
                        @if(!empty($ebaySprcDilIsMacys))
                        <li>
                            <strong>When</strong> Dil is <strong>out of box</strong> (no From–To match) and
                            {{ $ebaySprcDilSoldLabel }} = 0 (0 Sold):
                            take the <strong>minimum Target GROI</strong> from the slabs.
                        </li>
                        <li>
                            <strong>When</strong> that Dil / min-ROI S PRC is <strong>below A Price</strong>:
                            do not keep the slab price — S PRC uses <strong>A Price</strong>.
                            If it is at or above A Price, keep the Dil / min-ROI price.
                        </li>
                        <li>
                            <strong>When</strong> Dil is <strong>out of box</strong> and {{ $ebaySprcDilSoldLabel }} &gt; 0:
                            S PRC uses <strong>Std Prc</strong>. If Std Prc is below A Price, S PRC = A Price.
                        </li>
                        @endif
                        <li>
                            <strong>When</strong> a price is calculated from a Dil slab match:
                            it auto-applies to <strong>S PRC</strong> and is <strong>queued for Push Prc</strong>
                            (page close OK).
                        </li>
@endif
@if($ebaySprcDilChannel === 'doba_withoutship')
                        <li>
                            <strong>When</strong> S PRC is set:
                            use <strong>S Pick Price from /doba-tabulator</strong>
                            (with-ship SPRICE − Ship). That amount is pushed as Pick Up.
                        </li>
@elseif(!empty($ebaySprcDilExcludeShip))
                        <li>
                            <strong>When</strong> S PRC is calculated:
                            <code>S PRC = (LP × (1 + GROI%/100)) / margin</code> (Ship not used).
                        </li>
@endif
                        <li>
                            <strong>When</strong> you change the first Target GROI%:
                            later rows fill as first +5, +10, … (increasing down the table).
                        </li>
                        <li>
                            <strong>When</strong> you click <strong>Save and Apply</strong>:
                            {{ $ebaySprcDilPageLabel }}’s table is stored via <strong>API only</strong>, then old <strong>S PRC</strong> is deleted and the new Dil S PRC is written.
                        </li>
                        <li>
                            <strong>When</strong> INV ≤ 0: Count and pies skip that SKU.
                        </li>
                    </ul>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ebay-dil-groi-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:90px;">From</th>
                                    <th class="text-center" style="width:90px;">To</th>
                                    <th class="text-center" style="width:80px;" title="Child SKUs with INV &gt; 0 whose Dil is in this slab">Count</th>
                                    <th class="text-end" style="width:130px;">Target GROI%</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="ebay-dil-groi-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary ebay-dg-add-btn mt-2" id="ebay-dil-groi-add-btn">
                        <i class="fas fa-plus me-1"></i> Add slab
                    </button>
                    @if(!empty($ebaySprcDilCvrGroiAdj))
                    <div class="ebay-dg-rules-title mt-3">CVR overlay — Target GROI</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ebay-cvr-groi-table">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th class="text-center">CVR%</th>
                                    <th class="text-end">Adj GROI</th>
                                    <th class="text-center" style="width:80px;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr data-row="down">
                                    <td>Down</td>
                                    <td class="text-center">
                                        &lt;
                                        <input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-cvr-groi-input ebay-cvr-groi-down-lt" value="7">
                                    </td>
                                    <td class="text-end">
                                        <input type="number" step="1" class="form-control form-control-sm ebay-cvr-groi-input ebay-cvr-groi-down-adj" value="-10">
                                    </td>
                                    <td class="ebay-dg-count"><span class="ebay-cvr-groi-down-count">0</span></td>
                                </tr>
                                <tr data-row="up">
                                    <td>Up</td>
                                    <td class="text-center">
                                        &gt;
                                        <input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-cvr-groi-input ebay-cvr-groi-up-gt" value="10">
                                    </td>
                                    <td class="text-end">
                                        <input type="number" step="1" class="form-control form-control-sm ebay-cvr-groi-input ebay-cvr-groi-up-adj" value="10">
                                    </td>
                                    <td class="ebay-dg-count"><span class="ebay-cvr-groi-up-count">0</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    @endif
                    <div class="small text-muted mt-2" id="ebay-dil-groi-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="ebay-dil-groi-save-btn"
                        title="Save Dil → Target GROI% slabs via API and apply S PRC on matching SKUs.">
                        <i class="fas fa-save me-1"></i> Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($ebaySprcDilPart === 'script' || $ebaySprcDilPart === 'all')
        const EBAY_DIL_GROI_CHANNEL = @json($ebaySprcDilChannel);
        const EBAY_DIL_GROI_ZERO_SOLD_MIN = @json($ebaySprcDilZeroSoldUsesMinGroi);
        const EBAY_DIL_GROI_CVR_ADJ = @json(!empty($ebaySprcDilCvrGroiAdj));
        const EBAY_DIL_GROI_HIDE_CVR_PIE = @json(!empty($ebaySprcDilHideCvrPie));
        function ebayDgIsMacys() {
            return EBAY_DIL_GROI_CHANNEL === 'macys' || EBAY_DIL_GROI_CHANNEL === 'macy';
        }
        function ebayDgIsPurchasingPower() {
            return EBAY_DIL_GROI_CHANNEL === 'purchasing_power';
        }
        function ebayDgIsWayfair() {
            return EBAY_DIL_GROI_CHANNEL === 'wayfair';
        }
        function ebayDgIsReverb() {
            return EBAY_DIL_GROI_CHANNEL === 'reverb';
        }
        function ebayDgIsDoba() {
            return EBAY_DIL_GROI_CHANNEL === 'doba';
        }
        function ebayDgIsDobaWithoutship() {
            return EBAY_DIL_GROI_CHANNEL === 'doba_withoutship';
        }
        function ebayDgIsAliexpress() {
            return EBAY_DIL_GROI_CHANNEL === 'aliexpress';
        }
        function ebayDgIsShein() {
            return EBAY_DIL_GROI_CHANNEL === 'shein';
        }
        function ebayDgIsFaire() {
            return EBAY_DIL_GROI_CHANNEL === 'faire';
        }
        function ebayDgIsTiktok() {
            return EBAY_DIL_GROI_CHANNEL === 'tiktok' || EBAY_DIL_GROI_CHANNEL === 'tiktok2';
        }
        function ebayDgIsFbMarketplace() {
            return EBAY_DIL_GROI_CHANNEL === 'fb_marketplace';
        }
        function ebayDgIsTopdawg() {
            return EBAY_DIL_GROI_CHANNEL === 'topdawg';
        }
        function ebayDgIsShopifyB2c() {
            return EBAY_DIL_GROI_CHANNEL === 'shopify_b2c';
        }
        function ebayDgUsesClearThenApply() {
            return ebayDgIsTiktok() || ebayDgIsFbMarketplace() || ebayDgIsShopifyB2c()
                || ebayDgIsDoba() || ebayDgIsDobaWithoutship() || ebayDgIsTopdawg()
                || ebayDgIsMacys();
        }
        function ebayDgUsesBackgroundRuleApply() {
            return ebayDgIsMacys() || ebayDgIsPurchasingPower();
        }
        function ebayDgIsBestbuy() {
            return EBAY_DIL_GROI_CHANNEL === 'bestbuy';
        }
        function ebayDgIsNewegg() {
            return EBAY_DIL_GROI_CHANNEL === 'newegg';
        }
        function ebayDgUsesSkuDil() {
            return ebayDgIsMacys() || ebayDgIsPurchasingPower() || ebayDgIsWayfair() || ebayDgIsReverb()
                || ebayDgIsDoba() || ebayDgIsDobaWithoutship() || ebayDgIsAliexpress() || ebayDgIsShein()
                || ebayDgIsFaire() || ebayDgIsTiktok() || ebayDgIsFbMarketplace() || ebayDgIsShopifyB2c()
                || ebayDgIsBestbuy() || ebayDgIsNewegg() || ebayDgIsTopdawg()
                || EBAY_DIL_GROI_CHANNEL === 'walmart'
                || EBAY_DIL_GROI_CHANNEL === 'shopify_b2b'
                || EBAY_DIL_GROI_CHANNEL === 'pls'
                || EBAY_DIL_GROI_CHANNEL === 'depop'
                || EBAY_DIL_GROI_CHANNEL === 'vinted'
                || EBAY_DIL_GROI_CHANNEL === 'mercari_wship'
                || EBAY_DIL_GROI_CHANNEL === 'mercari_woship';
        }
        function ebayDgAutoApplies() {
            return true;
        }
        function ebayDgExcludeShip() {
            return ebayDgIsPurchasingPower() || ebayDgIsWayfair() || ebayDgIsDobaWithoutship() || ebayDgIsFaire() || ebayDgIsTopdawg() || ebayDgIsFbMarketplace()
                || EBAY_DIL_GROI_CHANNEL === 'shopify_b2b'
                || EBAY_DIL_GROI_CHANNEL === 'mercari_woship';
        }
        function ebayDgRulesUrl() {
            return '/channel-promo-pricing/' + encodeURIComponent(EBAY_DIL_GROI_CHANNEL) + '/dil-groi';
        }
        function ebayDgHistKey(kind) {
            return EBAY_DIL_GROI_CHANNEL + '_dil_groi_' + kind + '_hist';
        }
        const EBAY_DIL_GROI_DEFAULTS = [
            { key: '0.1-5', label: '0.1–5%', min: 0.1, max: 5, groi: 50 },
            { key: '5-10', label: '5–10%', min: 5, max: 10, groi: 55 },
            { key: '10-15', label: '10–15%', min: 10, max: 15, groi: 60 },
            { key: '15-20', label: '15–20%', min: 15, max: 20, groi: 65 },
            { key: '20-25', label: '20–25%', min: 20, max: 25, groi: 70 },
        ];
        let ebayDilGroiRules = EBAY_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let ebayDgDilPieChart = null;
        let ebayDgCvrPieChart = null;
        let ebayDgHistChart = null;
        let ebayDgDilLiveCounts = {};
        let ebayDgCvrLiveCounts = {};
        let ebayDgDilSlices = [];
        const EBAY_DG_SLAB_COLORS = [
            '#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16',
            '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c',
            '#7c3aed', '#0ea5e9',
        ];
        let ebayCvrGroiAdj = { down_lt: 7, down_adj: -10, up_gt: 10, up_adj: 10 };
        function ebayNormalizeCvrGroiAdj(raw) {
            const out = { down_lt: 7, down_adj: -10, up_gt: 10, up_adj: 10 };
            if (!raw) return out;
            const downLt = Number(raw.down_lt);
            const downAdj = Number(raw.down_adj);
            const upGt = Number(raw.up_gt);
            const upAdj = Number(raw.up_adj);
            if (isFinite(downLt) && downLt >= 0) out.down_lt = ebayDgRound2(downLt);
            if (isFinite(downAdj)) out.down_adj = ebayDgRound2(downAdj);
            if (isFinite(upGt) && upGt >= 0) out.up_gt = ebayDgRound2(upGt);
            if (isFinite(upAdj)) out.up_adj = ebayDgRound2(upAdj);
            return out;
        }
        function ebayCvrGroiAdjNow() {
            const $tbl = $('#ebay-cvr-groi-table');
            if ($tbl.length) {
                return ebayNormalizeCvrGroiAdj({
                    down_lt: parseFloat($tbl.find('.ebay-cvr-groi-down-lt').val()),
                    down_adj: parseFloat($tbl.find('.ebay-cvr-groi-down-adj').val()),
                    up_gt: parseFloat($tbl.find('.ebay-cvr-groi-up-gt').val()),
                    up_adj: parseFloat($tbl.find('.ebay-cvr-groi-up-adj').val()),
                });
            }
            return ebayNormalizeCvrGroiAdj(ebayCvrGroiAdj);
        }
        function ebayPaintCvrGroiAdjTable(cfg) {
            cfg = ebayNormalizeCvrGroiAdj(cfg);
            ebayCvrGroiAdj = cfg;
            const $tbl = $('#ebay-cvr-groi-table');
            if (!$tbl.length) return;
            $tbl.find('.ebay-cvr-groi-down-lt').val(cfg.down_lt);
            $tbl.find('.ebay-cvr-groi-down-adj').val(cfg.down_adj);
            $tbl.find('.ebay-cvr-groi-up-gt').val(cfg.up_gt);
            $tbl.find('.ebay-cvr-groi-up-adj').val(cfg.up_adj);
        }
        function ebayDgCvrBands() {
            const cfg = ebayCvrGroiAdjNow();
            const lo = cfg.down_lt;
            const hi = cfg.up_gt;
            const mid = lo + '–' + hi;
            const downLabel = EBAY_DIL_GROI_CVR_ADJ
                ? ('Down · < ' + lo + '% (' + cfg.down_adj + ' GROI)')
                : ('Down · < ' + lo + '%');
            const upLabel = EBAY_DIL_GROI_CVR_ADJ
                ? ('UP · > ' + hi + '% (' + (cfg.up_adj > 0 ? '+' : '') + cfg.up_adj + ' GROI)')
                : ('UP · > ' + hi + '%');
            return [
                { key: 'down-lt', label: downLabel, color: '#dc3545' },
                { key: 'down-mid', label: 'Down · ' + mid + '%', color: '#fd7e14' },
                { key: 'down-gt', label: 'Down · > ' + hi + '%', color: '#f59e0b' },
                { key: 'flat-lt', label: 'Flat · < ' + lo + '%', color: '#94a3b8' },
                { key: 'flat-mid', label: 'Flat · ' + mid + '%', color: '#64748b' },
                { key: 'flat-gt', label: 'Flat · > ' + hi + '%', color: '#475569' },
                { key: 'up-lt', label: 'UP · < ' + lo + '%', color: '#86efac' },
                { key: 'up-mid', label: 'UP · ' + mid + '%', color: '#20c997' },
                { key: 'up-gt', label: upLabel, color: '#198754' },
            ];
        }

        function ebayDgRound2(n) {
            return Math.round((Number(n) || 0) * 100) / 100;
        }
        function ebayDgToast(type, msg) {
            if (typeof chPromoToast === 'function') chPromoToast(type, msg);
            else if (typeof showToast === 'function') showToast(type, msg);
            else console.log(type, msg);
        }
        function ebayDgCsrf() {
            if (typeof chPromoCsrf === 'function') return chPromoCsrf();
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        function ebayDgFmtNum(n) {
            const x = Number(n);
            if (!isFinite(x)) return '0';
            return ebayDgRound2(x).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
        }
        function ebayDgIsChild(d) {
            if (typeof chPromoIsChildRow === 'function') return chPromoIsChildRow(d);
            return !!(d && !d.is_parent_summary && d['(Child) sku'] && String(d['(Child) sku']).indexOf('PARENT') === -1);
        }
        function ebayDgInv(d) {
            if (typeof chPromoInv === 'function') return chPromoInv(d);
            return Number(d && d.INV) || 0;
        }
        function ebayDgDil(d) {
            if (ebayDgUsesSkuDil() && typeof chPromoDil === 'function') return chPromoDil(d);
            if (typeof chPromoListingDil === 'function') return chPromoListingDil(d);
            if (typeof chPromoDil === 'function') return chPromoDil(d);
            const inv = ebayDgInv(d);
            if (!(inv > 0)) return 0;
            const ov = Number(d && (d.L30 != null ? d.L30 : d['eBay L30'])) || 0;
            return (ov / inv) * 100;
        }
        function ebayDgCvr30(d) {
            if (EBAY_DIL_GROI_CHANNEL === 'reverb') {
                if (d && d.CVR != null && d.CVR !== '') {
                    const n = Number(d.CVR);
                    if (isFinite(n) && n >= 0) return n;
                }
                const l30 = Number(d && d['RV L30']) || 0;
                const views = Number(d && d.Views) || 0;
                return views > 0 ? (l30 / views) * 100 : 0;
            }
            if (EBAY_DIL_GROI_CHANNEL === 'faire') {
                if (d && d.cvr != null && d.cvr !== '') {
                    const n = Number(d.cvr);
                    if (isFinite(n) && n >= 0) return n;
                }
                const views = Number(d && d.views) || 0;
                const units = Number(d && (d.units_sold != null ? d.units_sold : d.al30)) || 0;
                return views > 0 ? (units / views) * 100 : 0;
            }
            if (EBAY_DIL_GROI_CHANNEL === 'tiktok' || EBAY_DIL_GROI_CHANNEL === 'tiktok2') {
                if (typeof ttListingCvr === 'function') return Number(ttListingCvr(d)) || 0;
                const raw = (d && d.cvr != null && d.cvr !== '' && d.cvr !== '-') ? d.cvr : (d && d['CVR%']);
                const stored = Number(raw);
                if (isFinite(stored) && raw !== '' && raw !== '-' && stored >= 0) return stored;
                const views = Number(d && (d.t_views != null ? d.t_views : d.views)) || 0;
                const sold = Number(d && d['TT L30']) || 0;
                return views > 0 ? (sold / views) * 100 : 0;
            }
            if (EBAY_DIL_GROI_CHANNEL === 'shopify_b2c') {
                const l30 = Number(d && (d['B2B L30'] != null ? d['B2B L30'] : d['B2C L30'])) || 0;
                const views = Number(d && (d.Views != null ? d.Views : d.views)) || 0;
                return views > 0 ? (l30 / views) * 100 : 0;
            }
            if (d && d.cvr_30 != null && d.cvr_30 !== '') {
                const n = Number(d.cvr_30);
                if (isFinite(n) && n >= 0) return n;
            }
            if (typeof chPromoCvr === 'function') return Number(chPromoCvr(d)) || 0;
            return Number(d && (d.SCVR != null ? d.SCVR : d.cvr_percent)) || 0;
        }
        function ebayDgHasCvrPrior(d) {
            if (!d) return false;
            const raw = (d.CVR_60 != null && d.CVR_60 !== '') ? d.CVR_60
                : ((d.cvr_60 != null && d.cvr_60 !== '') ? d.cvr_60
                    : ((d.CVR_45 != null && d.CVR_45 !== '') ? d.CVR_45
                        : d.cvr_45));
            if (raw == null || raw === '') return false;
            const n = Number(raw);
            return isFinite(n) && n > 0;
        }
        function ebayDgCvr60(d) {
            return Number(d && (d.CVR_60 != null ? d.CVR_60 : d.cvr_60)) || 0;
        }
        function ebayDgCvrTrend(d) {
            const cvr = ebayDgCvr30(d);
            const cvr60 = ebayDgCvr60(d);
            const tol = 0.1;
            if (cvr === 0 || cvr < cvr60 - tol) return 'down';
            if (cvr > cvr60 + tol) return 'up';
            return 'flat';
        }
        function ebayDgCvrBandKey(d) {
            const cvr = ebayDgCvr30(d);
            const trend = ebayDgCvrTrend(d);
            const cfg = ebayCvrGroiAdjNow();
            const bucket = cvr < cfg.down_lt ? 'lt' : (cvr > cfg.up_gt ? 'gt' : 'mid');
            return trend + '-' + bucket;
        }
        function ebayDgUsesCvrLevelOnly() {
            return EBAY_DIL_GROI_CHANNEL === 'ebay1'
                || EBAY_DIL_GROI_CHANNEL === 'ebay2'
                || EBAY_DIL_GROI_CHANNEL === 'ebay2op'
                || EBAY_DIL_GROI_CHANNEL === 'ebay3'
                || EBAY_DIL_GROI_CHANNEL === 'reverb'
                || EBAY_DIL_GROI_CHANNEL === 'faire'
                || EBAY_DIL_GROI_CHANNEL === 'tiktok'
                || EBAY_DIL_GROI_CHANNEL === 'tiktok2'
                || EBAY_DIL_GROI_CHANNEL === 'shopify_b2c'
                || EBAY_DIL_GROI_CHANNEL === 'shopify_b2b';
        }
        function ebayDgClampsDilToNearestSlab() {
            return EBAY_DIL_GROI_CHANNEL === 'ebay1'
                || EBAY_DIL_GROI_CHANNEL === 'ebay2'
                || EBAY_DIL_GROI_CHANNEL === 'ebay2op'
                || EBAY_DIL_GROI_CHANNEL === 'ebay3';
        }
        function ebayDilGroiCvrAdj(d) {
            if (!EBAY_DIL_GROI_CVR_ADJ) return 0;
            const cvr = ebayDgCvr30(d);
            const cfg = ebayCvrGroiAdjNow();
            if (ebayDgUsesCvrLevelOnly() || !ebayDgHasCvrPrior(d)) {
                if (cvr < cfg.down_lt) return cfg.down_adj;
                if (cvr > cfg.up_gt) return cfg.up_adj;
                return 0;
            }
            const trend = ebayDgCvrTrend(d);
            if (trend === 'down' && cvr < cfg.down_lt) return cfg.down_adj;
            if (trend === 'up' && cvr > cfg.up_gt) return cfg.up_adj;
            return 0;
        }
        function ebayDilGroiApplyCvrAdj(slabGroi, d) {
            const groi = ebayDgRound2((Number(slabGroi) || 0) + ebayDilGroiCvrAdj(d));
            return groi < 0 ? 0 : groi;
        }
        function ebayDgIsZeroSold(d) {
            if (typeof chPromoIsZeroSoldRow === 'function') return chPromoIsZeroSoldRow(d);
            if (!ebayDgIsChild(d) || !(ebayDgInv(d) > 0)) return false;
            const sold = Number(d && (d['eBay L30'] != null ? d['eBay L30'] : d.ebay_l30)) || 0;
            return !(sold > 0);
        }
        function ebayNormalizeDilGroiRule(raw) {
            if (!raw) return null;
            let min = Number(raw.min);
            let max = Number(raw.max);
            if (!isFinite(min) || !isFinite(max)) {
                const m = String(raw.key || '').match(/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/);
                if (!m) return null;
                min = Number(m[1]);
                max = Number(m[2]);
            }
            if (!isFinite(min) || !isFinite(max) || min < 0 || max <= min) return null;
            min = ebayDgRound2(min);
            max = ebayDgRound2(max);
            let groi = Number(raw.groi);
            if (!isFinite(groi) || groi < 0) groi = 0;
            groi = ebayDgRound2(groi);
            return {
                key: ebayDgFmtNum(min) + '-' + ebayDgFmtNum(max),
                label: ebayDgFmtNum(min) + '–' + ebayDgFmtNum(max) + '%',
                min: min,
                max: max,
                groi: groi,
            };
        }
        function ebayNormalizeDilGroiList(list) {
            const out = [];
            (Array.isArray(list) ? list : []).forEach(function(item) {
                const rule = ebayNormalizeDilGroiRule(item);
                if (rule) out.push(rule);
            });
            out.sort(function(a, b) { return a.min - b.min; });
            return out;
        }
        function ebayDilGroiCurrentList() {
            const fromModal = [];
            $('#ebay-dil-groi-tbody tr').each(function() {
                const rule = ebayNormalizeDilGroiRule({
                    min: parseFloat($(this).find('.ebay-dg-min').val()),
                    max: parseFloat($(this).find('.ebay-dg-max').val()),
                    groi: parseFloat($(this).find('.ebay-dg-groi').val()),
                });
                if (rule) fromModal.push(rule);
            });
            const list = fromModal.length ? fromModal : ebayNormalizeDilGroiList(ebayDilGroiRules);
            return list.length ? list : EBAY_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        }
        function ebayDilGroiMatch(dil) {
            const n = Number(dil);
            const list = ebayDilGroiCurrentList();
            if (!isFinite(n) || n < 0 || !list.length) return null;
            const last = list.length - 1;
            for (let i = 0; i < list.length; i++) {
                const rule = list[i];
                const hiOk = (i === last) ? (n <= rule.max) : (n < rule.max);
                if (n >= rule.min && hiOk) return rule;
            }
            return null;
        }
        /** Exact slab, or nearest slab on eBay 1–3 so 0 Sold (Dil 0) and Dil above last To still get GROI. */
        function ebayDilGroiResolve(dil) {
            const exact = ebayDilGroiMatch(dil);
            if (exact) return exact;
            if (!ebayDgClampsDilToNearestSlab()) return null;
            const n = Number(dil);
            const list = ebayDilGroiCurrentList();
            if (!isFinite(n) || n < 0 || !list.length) return null;
            if (n < list[0].min) return list[0];
            return list[list.length - 1];
        }
        function ebayDilGroiMinSlab() {
            const list = ebayDilGroiCurrentList();
            let best = null;
            list.forEach(function(r) {
                const g = Number(r.groi);
                if (!isFinite(g)) return;
                if (!best || g < best.groi || (g === best.groi && Number(r.min) < Number(best.min))) {
                    best = r;
                }
            });
            return best;
        }
        function ebayDgDobaTabulatorSPick(d) {
            const copied = Number(d && (d.doba_tabulator_s_pick != null
                ? d.doba_tabulator_s_pick
                : d.DOBA_TABULATOR_S_PICK)) || 0;
            if (copied > 0) return ebayDgRound2(copied);
            const sprice = Number(d && (d.doba_tabulator_sprice != null ? d.doba_tabulator_sprice : 0)) || 0;
            const ship = parseFloat(d && d.Ship_productmaster) || 0;
            if (sprice > 0) return ebayDgRound2(Math.max(0, sprice - ship));
            return 0;
        }
        function ebaySpriceFromGroi(d, groi) {
            if (ebayDgIsDobaWithoutship()) {
                const copied = ebayDgDobaTabulatorSPick(d);
                if (copied > 0) return copied;
                const lp = parseFloat(d && d.LP_productmaster) || 0;
                if (!(lp > 0)) return 0;
                const ship = parseFloat(d && d.Ship_productmaster) || 0;
                const margin = (typeof CHANNEL_PROMO_TAKEHOME === 'number' && CHANNEL_PROMO_TAKEHOME > 0)
                    ? CHANNEL_PROMO_TAKEHOME
                    : 0.95;
                const delivery = (lp * (1 + (Number(groi) || 0) / 100) + ship) / margin;
                return (isFinite(delivery) && delivery > 0)
                    ? ebayDgRound2(Math.max(0, delivery - ship))
                    : 0;
            }
            if (typeof chPromoSpriceFromTargetRoi === 'function') {
                const p = chPromoSpriceFromTargetRoi(d, groi);
                return p > 0 ? p : 0;
            }
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            if (!(lp > 0)) return 0;
            const ship = ebayDgExcludeShip() ? 0 : (parseFloat(d && d.Ship_productmaster) || 0);
            const margin = (typeof CHANNEL_PROMO_TAKEHOME === 'number' && CHANNEL_PROMO_TAKEHOME > 0)
                ? CHANNEL_PROMO_TAKEHOME
                : ((typeof EBAY_TAKEHOME !== 'undefined' && Number(EBAY_TAKEHOME) > 0) ? Number(EBAY_TAKEHOME)
                    : ((typeof EBAY2_TAKEHOME !== 'undefined' && Number(EBAY2_TAKEHOME) > 0) ? Number(EBAY2_TAKEHOME)
                        : ((typeof EBAY3_TAKEHOME !== 'undefined' && Number(EBAY3_TAKEHOME) > 0) ? Number(EBAY3_TAKEHOME)
                            : 0.80)));
            const price = (lp * (1 + (Number(groi) || 0) / 100) + ship) / margin;
            return (isFinite(price) && price > 0) ? ebayDgRound2(price) : 0;
        }
        function ebayDilGroiMetaForRow(d) {
            if (!ebayDgIsChild(d) || !(ebayDgInv(d) > 0)) return null;
            const dil = ebayDgDil(d);
            let groi = null;
            let label = '';
            let key = '';
            let zeroSoldMin = false;
            let clamped = false;
            const exact = ebayDilGroiMatch(dil);
            const rule = exact || ebayDilGroiResolve(dil);
            clamped = !exact && !!rule && ebayDgClampsDilToNearestSlab();
            if (EBAY_DIL_GROI_ZERO_SOLD_MIN && ebayDgIsZeroSold(d)) {
                const minSlab = ebayDilGroiMinSlab();
                if (!minSlab) return null;
                groi = minSlab.groi;
                label = '0 Sold · min GROI ' + minSlab.groi + '% from ' + minSlab.label;
                key = minSlab.key || 'zero-sold-min';
                zeroSoldMin = true;
            } else if (rule) {
                groi = rule.groi;
                label = rule.label;
                key = rule.key;
            } else if (ebayDgIsMacys() && ebayDgIsZeroSold(d)) {
                const minSlab = ebayDilGroiMinSlab();
                if (!minSlab) return null;
                groi = minSlab.groi;
                label = 'out of box · 0 Sold · min GROI ' + minSlab.groi + '% from ' + minSlab.label;
                key = minSlab.key || 'zero-sold-min';
                zeroSoldMin = true;
            } else {
                return null;
            }
            const slabGroi = groi;
            const cvrAdj = ebayDilGroiCvrAdj(d);
            groi = ebayDilGroiApplyCvrAdj(slabGroi, d);
            const rawSprc = ebaySpriceFromGroi(d, groi);
            if (!(rawSprc > 0)) return null;
            let sprc = rawSprc;
            let amzApplied = false;
            if (ebayDgIsShopifyB2c()) {
                const floored = (typeof chPromoFinalSpriceToSave === 'function')
                    ? Number(chPromoFinalSpriceToSave(d, rawSprc))
                    : ((typeof chPromoFloorShopifySpriceToAmz === 'function')
                        ? Number(chPromoFloorShopifySpriceToAmz(d, rawSprc))
                        : rawSprc);
                if (floored > 0) {
                    amzApplied = floored > rawSprc + 0.001;
                    sprc = ebayDgRound2(floored);
                }
            } else if (ebayDgIsMacys()) {
                const amz = (typeof chPromoAmazonPrice === 'function')
                    ? ebayDgRound2(chPromoAmazonPrice(d))
                    : ebayDgRound2(d && (d['A Price'] != null ? d['A Price'] : (d.a_price || d.amazon_price)));
                if (rawSprc > 0 && amz > 0 && rawSprc < amz) {
                    amzApplied = true;
                    sprc = amz;
                }
            }
            return {
                dil: dil,
                key: key,
                label: label,
                slabGroi: slabGroi,
                groi: groi,
                cvrAdj: cvrAdj,
                sprc: sprc,
                rawSprc: rawSprc,
                amzApplied: amzApplied,
                zeroSoldMin: zeroSoldMin,
                clamped: clamped,
            };
        }
        function ebayDilGroiTipText(meta, opts) {
            opts = opts || {};
            if (!meta || !(meta.sprc > 0)) return '';
            const slabGroi = (meta.slabGroi != null) ? meta.slabGroi : meta.groi;
            const head = meta.zeroSoldMin
                ? (opts.zeroSoldLabel || '0 Sold → min Target GROI')
                : ('Dil ' + (isFinite(meta.dil) ? Number(meta.dil).toFixed(1) : '0') + '%'
                    + (meta.clamped ? ' (nearest slab)' : ''));
            let tip = head + ' → ' + meta.label + ' → GROI ' + slabGroi + '%';
            if (meta.cvrAdj) {
                const sign = meta.cvrAdj > 0 ? '+' : '';
                const cfg = ebayCvrGroiAdjNow();
                const why = meta.cvrAdj > 0
                    ? ('CVR Up > ' + cfg.up_gt + '%')
                    : ('CVR Down < ' + cfg.down_lt + '%');
                tip += ' ' + sign + meta.cvrAdj + ' (' + why + ') → ' + meta.groi + '%';
            }
            return tip + ' → $' + Number(meta.sprc).toFixed(2);
        }
        function ebaySprcDilForRow(d) {
            const meta = ebayDilGroiMetaForRow(d);
            return meta ? meta.sprc : 0;
        }
        function ebayDilGroiOwnsRow(d) {
            const meta = ebayDilGroiMetaForRow(d);
            return !!(meta && meta.sprc > 0);
        }
        window.ebayDilGroiMetaForRow = ebayDilGroiMetaForRow;
        window.ebaySprcDilForRow = ebaySprcDilForRow;
        window.ebayDilGroiOwnsRow = ebayDilGroiOwnsRow;
        window.ebayDilGroiTipText = ebayDilGroiTipText;
        window.ebayCvrGroiAdjNow = ebayCvrGroiAdjNow;

        function ebayDgEachInvChild(fn) {
            const walk = function(row, d) {
                const data = d || (row && typeof row.getData === 'function' ? row.getData() : row);
                if (!ebayDgIsChild(data) || !(ebayDgInv(data) > 0)) return;
                fn(data);
            };
            if (typeof chPromoEachTableRow === 'function') {
                chPromoEachTableRow(walk);
                return;
            }
            if (typeof table !== 'undefined' && table && typeof table.getData === 'function') {
                (table.getData('all') || []).forEach(function(d) { walk(null, d); });
            }
        }
        function ebayDilGroiCollectCounts(list) {
            const rules = list || ebayDilGroiCurrentList();
            const counts = { _outside: 0 };
            rules.forEach(function(r) { counts[r.key] = 0; });
            ebayDgEachInvChild(function(d) {
                const rule = ebayDilGroiResolve(ebayDgDil(d));
                if (rule) counts[rule.key] = (counts[rule.key] || 0) + 1;
                else counts._outside++;
            });
            return counts;
        }
        function ebayDilGroiCollectCvrCounts() {
            const counts = {};
            ebayDgCvrBands().forEach(function(b) { counts[b.key] = 0; });
            ebayDgEachInvChild(function(d) {
                const key = ebayDgCvrBandKey(d);
                counts[key] = (counts[key] || 0) + 1;
            });
            return counts;
        }
        function ebayDilGroiCollectCvrAdjCounts() {
            const counts = { down: 0, up: 0 };
            if (!EBAY_DIL_GROI_CVR_ADJ) return counts;
            ebayDgEachInvChild(function(d) {
                const adj = ebayDilGroiCvrAdj(d);
                if (adj < 0) counts.down++;
                else if (adj > 0) counts.up++;
            });
            return counts;
        }
        function ebayPaintCvrGroiAdjCounts() {
            if (!EBAY_DIL_GROI_CVR_ADJ) return;
            const counts = ebayDilGroiCollectCvrAdjCounts();
            $('#ebay-cvr-groi-table .ebay-cvr-groi-down-count').text(counts.down);
            $('#ebay-cvr-groi-table .ebay-cvr-groi-up-count').text(counts.up);
        }
        function ebayDgSlabColor(idx) {
            return EBAY_DG_SLAB_COLORS[idx % EBAY_DG_SLAB_COLORS.length];
        }
        function ebayDgHistDotHtml(chart, key, color, label) {
            return '<button type="button" class="ebay-dg-hist-dot" data-chart="' + String(chart).replace(/"/g, '&quot;') + '" '
                + 'data-band="' + String(key).replace(/"/g, '&quot;') + '" '
                + 'style="background:' + color + ';" title="' + String(label).replace(/"/g, '&quot;') + ' daily history"></button>';
        }
        function ebayDgTodayKey() {
            try {
                return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
            } catch (e) {
                return new Date().toISOString().slice(0, 10);
            }
        }
        function ebayDgSnapLocal(storeKey, counts) {
            try {
                const today = ebayDgTodayKey();
                let hist = {};
                try { hist = JSON.parse(localStorage.getItem(storeKey) || '{}') || {}; } catch (e) { hist = {}; }
                hist[today] = counts;
                const keys = Object.keys(hist).sort();
                while (keys.length > 90) delete hist[keys.shift()];
                localStorage.setItem(storeKey, JSON.stringify(hist));
            } catch (e) { /* ignore */ }
        }
        function ebayDgLocalHistory(storeKey) {
            try {
                const hist = JSON.parse(localStorage.getItem(storeKey) || '{}') || {};
                return Object.keys(hist).sort().map(function(date) {
                    return Object.assign({ date: date, label: date.slice(5) }, hist[date] || {});
                });
            } catch (e) {
                return [];
            }
        }
        function ebayDgWithChart(fn) {
            if (typeof window.loadChartJs === 'function') {
                window.loadChartJs().then(fn).catch(fn);
                return;
            }
            if (typeof Chart !== 'undefined') {
                fn();
                return;
            }
            if (window._ebayDgChartJsPromise) {
                window._ebayDgChartJsPromise.then(fn).catch(fn);
                return;
            }
            window._ebayDgChartJsPromise = new Promise(function(resolve, reject) {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
                s.onload = function() { resolve(); };
                s.onerror = reject;
                document.head.appendChild(s);
            });
            window._ebayDgChartJsPromise.then(fn).catch(fn);
        }
        function ebayDgPieLegendHtml(title, slices, counts, chart) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            return '<div class="ebay-dg-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                + '<span class="ebay-dg-pie-swatch" style="visibility:hidden;"></span>'
                + '<span class="ebay-dg-pie-name">' + title + '</span>'
                + '<span class="ebay-dg-pie-count">count</span>'
                + '<span class="ebay-dg-pie-pct">of total</span>'
                + '<span class="ebay-dg-hist-dot" style="visibility:hidden;"></span>'
                + '</div>'
                + slices.map(function(s) {
                    const n = counts[s.key] || 0;
                    const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                    return '<div class="ebay-dg-pie-row">'
                        + '<span class="ebay-dg-pie-swatch" style="background:' + s.color + ';"></span>'
                        + '<span class="ebay-dg-pie-name">' + s.label + '</span>'
                        + '<span class="ebay-dg-pie-count">' + n + '</span>'
                        + '<span class="ebay-dg-pie-pct" title="' + pct + ' of total">' + pct + '</span>'
                        + ebayDgHistDotHtml(chart, s.key, s.color, s.label)
                        + '</div>';
                }).join('');
        }
        function ebayDgDrawHist(chart, band, rows) {
            const slices = chart === 'cvr' ? ebayDgCvrBands() : ebayDgDilSlices;
            const spec = slices.find(function(s) { return s.key === band; })
                || { key: band, label: band, color: '#6f42c1' };
            $('#ebay-dg-hist-title').text((chart === 'cvr' ? 'CVR ' : 'Dil ') + spec.label + ' count');
            $('#ebay-dg-hist-wrap').addClass('is-open');
            ebayDgWithChart(function() {
                const canvas = document.getElementById('ebay-dg-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (ebayDgHistChart) {
                    ebayDgHistChart.destroy();
                    ebayDgHistChart = null;
                }
                ebayDgHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: rows.map(function(r) { return r.label || r.date; }),
                        datasets: [{
                            data: rows.map(function(r) { return Number(r[band]) || 0; }),
                            borderColor: spec.color,
                            backgroundColor: spec.color + '22',
                            fill: true,
                            tension: 0.3,
                            borderWidth: 1.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: spec.color,
                            pointBorderColor: spec.color,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { size: 9 }, precision: 0 } },
                            x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } },
                        },
                    },
                });
            });
        }
        function ebayDgOpenHist(chart, band) {
            const storeKey = chart === 'cvr' ? ebayDgHistKey('cvr') : ebayDgHistKey('dil');
            const live = chart === 'cvr' ? ebayDgCvrLiveCounts : ebayDgDilLiveCounts;
            const applyToday = function(rows) {
                const list = (rows || []).slice();
                const today = ebayDgTodayKey();
                const rec = Object.assign({ date: today, label: today.slice(5) }, live);
                const last = list[list.length - 1];
                if (last && last.date === today) {
                    Object.assign(last, rec);
                } else {
                    list.push(rec);
                }
                return list;
            };
            ebayDgDrawHist(chart, band, applyToday(ebayDgLocalHistory(storeKey)));
        }
        function ebayDgDrawPie(canvasId, chartRefName, slices, counts) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            ebayDgWithChart(function() {
                const canvas = document.getElementById(canvasId);
                if (!canvas || typeof Chart === 'undefined') return;
                if (chartRefName === 'dil' && ebayDgDilPieChart) {
                    ebayDgDilPieChart.destroy();
                    ebayDgDilPieChart = null;
                }
                if (chartRefName === 'cvr' && ebayDgCvrPieChart) {
                    ebayDgCvrPieChart.destroy();
                    ebayDgCvrPieChart = null;
                }
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: slices.map(function(s) { return s.label; }),
                        datasets: [{
                            data: slices.map(function(s) { return counts[s.key] || 0; }),
                            backgroundColor: slices.map(function(s) { return s.color; }),
                            borderColor: '#fff',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const n = Number(ctx.raw) || 0;
                                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                                        return ' ' + n + '  ·  ' + pct + ' of total';
                                    },
                                },
                            },
                        },
                    },
                });
                if (chartRefName === 'dil') ebayDgDilPieChart = chart;
                else ebayDgCvrPieChart = chart;
            });
        }
        function renderEbayDilGroiPies() {
            try {
                const rules = ebayDilGroiCurrentList();
                const dilCounts = ebayDilGroiCollectCounts(rules);
                const dilSlices = rules.map(function(r, i) {
                    return { key: r.key, label: r.label, color: ebayDgSlabColor(i) };
                });
                if ((dilCounts._outside || 0) > 0 || !dilSlices.length) {
                    dilSlices.push({ key: '_outside', label: 'Outside', color: '#cbd5e1' });
                }
                ebayDgDilSlices = dilSlices;
                ebayDgDilLiveCounts = dilCounts;
                ebayDgSnapLocal(ebayDgHistKey('dil'), dilCounts);
                const dilLegend = document.getElementById('ebay-dg-dil-legend');
                if (dilLegend) dilLegend.innerHTML = ebayDgPieLegendHtml('Dil', dilSlices, dilCounts, 'dil');
                ebayDgDrawPie('ebay-dg-dil-pie', 'dil', dilSlices, dilCounts);

                if (EBAY_DIL_GROI_HIDE_CVR_PIE) {
                    $('#ebay-dil-groi-tbody tr').each(function(i) {
                        const r = rules[i];
                        const $cell = $(this).find('.ebay-dg-count');
                        $cell.find('.ebay-dg-count-n').text(r ? (dilCounts[r.key] || 0) : 0);
                        $cell.find('.ebay-dg-hist-dot').remove();
                        if (r) {
                            $cell.append(' ' + ebayDgHistDotHtml('dil', r.key, ebayDgSlabColor(i), r.label));
                        }
                    });
                    return;
                }

                const cvrBands = ebayDgCvrBands();
                const cvrCounts = ebayDilGroiCollectCvrCounts();
                ebayDgCvrLiveCounts = cvrCounts;
                ebayDgSnapLocal(ebayDgHistKey('cvr'), cvrCounts);
                const cvrLegend = document.getElementById('ebay-dg-cvr-legend');
                if (cvrLegend) cvrLegend.innerHTML = ebayDgPieLegendHtml('CVR Up / Down · CVR%', cvrBands, cvrCounts, 'cvr');
                ebayDgDrawPie('ebay-dg-cvr-pie', 'cvr', cvrBands, cvrCounts);
                ebayPaintCvrGroiAdjCounts();

                $('#ebay-dil-groi-tbody tr').each(function(i) {
                    const r = rules[i];
                    const $cell = $(this).find('.ebay-dg-count');
                    $cell.find('.ebay-dg-count-n').text(r ? (dilCounts[r.key] || 0) : 0);
                    $cell.find('.ebay-dg-hist-dot').remove();
                    if (r) {
                        $cell.append(' ' + ebayDgHistDotHtml('dil', r.key, ebayDgSlabColor(i), r.label));
                    }
                });
            } catch (e) {
                console.warn('Sprc Dil pies', e);
            }
        }
        function destroyEbayDilGroiPies() {
            if (ebayDgDilPieChart) { ebayDgDilPieChart.destroy(); ebayDgDilPieChart = null; }
            if (ebayDgCvrPieChart) { ebayDgCvrPieChart.destroy(); ebayDgCvrPieChart = null; }
            if (ebayDgHistChart) { ebayDgHistChart.destroy(); ebayDgHistChart = null; }
            $('#ebay-dg-hist-wrap').removeClass('is-open');
        }
        function renderEbayDilGroiCounts() {
            if ($('#ebayDilGroiModal').hasClass('show')) {
                renderEbayDilGroiPies();
                return;
            }
            const rules = ebayDilGroiCurrentList();
            const counts = ebayDilGroiCollectCounts(rules);
            $('#ebay-dil-groi-tbody tr').each(function(i) {
                const r = rules[i];
                $(this).find('.ebay-dg-count-n').text(r ? (counts[r.key] || 0) : 0);
            });
            ebayPaintCvrGroiAdjCounts();
        }
        function renderEbayDilGroiModalTable() {
            const $tb = $('#ebay-dil-groi-tbody');
            if (!$tb.length) return;
            $tb.empty();
            const list = ebayNormalizeDilGroiList(ebayDilGroiRules);
            ebayDilGroiRules = list.length
                ? list
                : EBAY_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            const canDelete = ebayDilGroiRules.length > 1;
            ebayDilGroiRules.forEach(function(r, idx) {
                const first = idx === 0;
                $tb.append(
                    '<tr data-idx="' + idx + '" data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-dil-groi-input ebay-dg-min" value="' + r.min + '"></td>'
                    + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-dil-groi-input ebay-dg-max" value="' + r.max + '"></td>'
                    + '<td class="ebay-dg-count"><span class="ebay-dg-count-n">0</span></td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ebay-dil-groi-input ebay-dg-groi" step="0.1" value="' + r.groi + '"'
                    + (first ? ' title="Changing this sets following slabs to +5 each"' : '') + '>'
                    + '</td>'
                    + '<td class="text-center">'
                    + (canDelete
                        ? '<button type="button" class="ebay-dil-groi-row-del" data-idx="' + idx + '" title="Remove slab">&times;</button>'
                        : '')
                    + '</td></tr>'
                );
            });
            if ($('#ebayDilGroiModal').hasClass('show')) {
                renderEbayDilGroiPies();
            } else {
                renderEbayDilGroiCounts();
            }
        }
        function readEbayDilGroiRulesFromModal() {
            const rules = [];
            $('#ebay-dil-groi-tbody tr').each(function() {
                const rule = ebayNormalizeDilGroiRule({
                    min: parseFloat($(this).find('.ebay-dg-min').val()),
                    max: parseFloat($(this).find('.ebay-dg-max').val()),
                    groi: parseFloat($(this).find('.ebay-dg-groi').val()),
                });
                if (rule) rules.push(rule);
            });
            ebayDilGroiRules = rules.length
                ? ebayNormalizeDilGroiList(rules)
                : EBAY_DIL_GROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            return ebayDilGroiRules.map(function(r) {
                return { key: r.key, label: r.label, min: r.min, max: r.max, groi: Number(r.groi) || 0 };
            });
        }
        function cascadeEbayDilGroiFromFirst() {
            const $rows = $('#ebay-dil-groi-tbody tr');
            if (!$rows.length) return;
            const firstVal = parseFloat($rows.eq(0).find('.ebay-dg-groi').val());
            if (!isFinite(firstVal)) return;
            $rows.each(function(i) {
                if (i === 0) return;
                $(this).find('.ebay-dg-groi').val(ebayDgRound2(firstVal + (i * 5)));
            });
            readEbayDilGroiRulesFromModal();
        }
        function ebayDilGroiAddSlab() {
            readEbayDilGroiRulesFromModal();
            let nextMin = 0.1;
            let lastGroi = 50;
            ebayDilGroiRules.forEach(function(r) {
                if (isFinite(Number(r.max)) && Number(r.max) > nextMin) nextMin = Number(r.max);
                if (isFinite(Number(r.groi))) lastGroi = Number(r.groi);
            });
            const added = ebayNormalizeDilGroiRule({
                min: nextMin,
                max: ebayDgRound2(nextMin + 5),
                groi: Math.max(0, ebayDgRound2(lastGroi + 5)),
            });
            if (!added) return;
            ebayDilGroiRules.push(added);
            ebayDilGroiRules = ebayNormalizeDilGroiList(ebayDilGroiRules);
            renderEbayDilGroiModalTable();
            ebayAfterDilGroiRulesChanged();
        }
        function ebayDilGroiDeleteSlab(idx) {
            const rules = readEbayDilGroiRulesFromModal();
            if (rules.length <= 1) {
                ebayDgToast('error', 'Keep at least one Dil slab');
                return;
            }
            if (!isFinite(idx) || idx < 0 || idx >= rules.length) return;
            rules.splice(idx, 1);
            ebayDilGroiRules = ebayNormalizeDilGroiList(rules);
            renderEbayDilGroiModalTable();
            ebayAfterDilGroiRulesChanged();
        }
        function redrawEbaySprcDilColumn() {
            if (typeof table === 'undefined' || !table) return;
            try { table.redraw(true); } catch (e) { /* ignore */ }
            if (typeof window.updateEbay3Summary === 'function') {
                try { window.updateEbay3Summary(); } catch (e) { /* ignore */ }
            } else if (typeof window.updateSummary === 'function') {
                try { window.updateSummary(); } catch (e) { /* ignore */ }
            }
        }
        let ebayDgAutoApplyTimer = null;
        let ebayDgAutoApplyWaits = 0;
        let ebayDgApplyBusy = false;
        let ebayDgApplyPending = false;
        function ebaySprcDilRowAdapter(d) {
            if (typeof chPromoDilPrmtRowAdapter === 'function') return chPromoDilPrmtRowAdapter(d);
            return {
                getData: function() { return d; },
                update: function(patch) { Object.assign(d, patch || {}); },
                reformat: function() {},
            };
        }
        function ebaySprcDilCatalogRows() {
            try {
                if (typeof window !== 'undefined' && Array.isArray(window.allTableData) && window.allTableData.length) {
                    return window.allTableData;
                }
            } catch (e) { /* ignore */ }
            try {
                if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) {
                    return allTableData;
                }
            } catch (e) { /* TDZ before let allTableData */ }
            return [];
        }
        function ebaySprcDilEachCatalogRow(fn) {
            const seen = new Set();
            function consider(row, d) {
                const sku = (typeof chPromoSku === 'function')
                    ? String(chPromoSku(d) || '').trim().toUpperCase()
                    : String((d && d['(Child) sku']) || '').trim().toUpperCase();
                if (!sku || seen.has(sku)) return;
                seen.add(sku);
                fn(row, d, sku);
            }
            if (typeof chPromoEachTableRow === 'function') {
                chPromoEachTableRow(function(row, d) { consider(row, d); });
            }
            ebaySprcDilCatalogRows().forEach(function(d) {
                if (!d) return;
                consider(ebaySprcDilRowAdapter(d), d);
            });
        }
        function ebayAfterDilGroiRulesChanged() {
            redrawEbaySprcDilColumn();
            ebayScheduleSprcDilAutoApply({ delay: 250 });
        }
        /** Macys / Purchasing Power load / slab edit: write Dil S PRC in the grid only. No catalog wipe or batch POST. */
        function ebayDgPaintMacysRuleSprice() {
            if (typeof table === 'undefined' || !table) return 0;
            const nearly = typeof chPromoNearlyEqual === 'function'
                ? chPromoNearlyEqual
                : function(a, b) { return Math.abs((Number(a) || 0) - (Number(b) || 0)) < 0.005; };
            const blocked = typeof table.blockRedraw === 'function';
            let n = 0;
            if (blocked) table.blockRedraw();
            try {
                ebaySprcDilEachCatalogRow(function(row, d) {
                    if (!ebayDgIsChild(d) || !row || typeof row.update !== 'function') return;
                    const price = ebayTiktokRuleDiscount(d);
                    const current = typeof chPromoGetSprice === 'function'
                        ? chPromoGetSprice(d)
                        : (Number(d && d.SPRICE) || 0);
                    const live = Number(d && (d['MC Price'] != null ? d['MC Price'] : d.price)) || 0;
                    if (price > 0) {
                        if (nearly(current, price)) return;
                        const patch = (typeof chPromoSpricePatch === 'function')
                            ? chPromoSpricePatch(price)
                            : { SPRICE: price, sprice: price, has_custom_sprice: true };
                        row.update(Object.assign({}, patch, ebayTiktokMetricsPatch(d, price), {
                            SPRICE_STATUS: 'applied',
                        }));
                        n++;
                        return;
                    }
                    if (current > 0 && live > 0 && nearly(current, live)) return;
                    if (current > 0) {
                        if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
                        else row.update({ SPRICE: 0, sprice: 0, has_custom_sprice: false, SGPFT: 0, SROI: 0, SPFT: 0 });
                        n++;
                    }
                });
            } finally {
                if (blocked) table.restoreRedraw();
            }
            redrawEbaySprcDilColumn();
            return n;
        }
        /** Blank SPRICE in the grid only (no POST of zeros) so the clear is visible. */
        function ebayDgWipeMacysSpriceCells() {
            if (typeof macysWipeAllSpriceCells === 'function') {
                macysWipeAllSpriceCells();
            } else {
                ebaySprcDilEachCatalogRow(function(row, d) {
                    if (!ebayDgIsChild(d) || !row) return;
                    if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
                    else if (typeof row.update === 'function') {
                        row.update({ SPRICE: 0, sprice: 0, has_custom_sprice: false, SGPFT: 0, SROI: 0, SPFT: 0 });
                    }
                });
            }
            redrawEbaySprcDilColumn();
        }
        let ebayDgMacysFlashRunning = false;
        function ebayDgFlashThenPaintMacysRuleSprice(opts) {
            opts = opts || {};
            if (ebayDgMacysFlashRunning) {
                return Promise.resolve(ebayDgPaintMacysRuleSprice());
            }
            ebayDgMacysFlashRunning = true;
            const wait = opts.delay != null ? Number(opts.delay) : 350;
            ebayDgWipeMacysSpriceCells();
            if (opts.toast !== false) {
                ebayDgToast('info', 'SPRICE cleared');
            }
            return new Promise(function(resolve) {
                setTimeout(function() {
                    const n = ebayDgPaintMacysRuleSprice();
                    ebayDgMacysFlashRunning = false;
                    resolve(n);
                }, isFinite(wait) && wait > 0 ? wait : 350);
            });
        }
        function ebayScheduleSprcDilAutoApply(opts) {
            if (!ebayDgAutoApplies()) return;
            opts = opts || {};
            const delay = opts.delay != null ? opts.delay : 400;
            clearTimeout(ebayDgAutoApplyTimer);
            ebayDgAutoApplyTimer = setTimeout(function() {
                if (typeof table === 'undefined' || !table) {
                    if (ebayDgAutoApplyWaits++ < 40) ebayScheduleSprcDilAutoApply(opts);
                    return;
                }
                const n = (typeof table.getDataCount === 'function') ? table.getDataCount() : 0;
                if (!(n > 0) && !ebaySprcDilCatalogRows().length) {
                    if (ebayDgAutoApplyWaits++ < 40) ebayScheduleSprcDilAutoApply(opts);
                    return;
                }
                ebayDgAutoApplyWaits = 0;
                if (typeof ebayDgUsesBackgroundRuleApply === 'function' && ebayDgUsesBackgroundRuleApply()) {
                    if (opts.flashClear) {
                        Promise.resolve(ebayDgFlashThenPaintMacysRuleSprice({
                            toast: opts.toast !== false,
                        })).catch(function() { /* ignore */ });
                    } else {
                        ebayDgPaintMacysRuleSprice();
                    }
                    return;
                }
                Promise.resolve(ebayApplySprcDilToTable({ persist: false, push: false })).catch(function() { /* retry on next change */ });
            }, delay);
        }
        window.ebayScheduleSprcDilAutoApply = ebayScheduleSprcDilAutoApply;
        function bindEbaySprcDilAutofill() {
            if (!ebayDgAutoApplies()) return;
            if (typeof table === 'undefined' || !table || !table.on) {
                setTimeout(bindEbaySprcDilAutofill, 400);
                return;
            }
            if (table._ebaySprcDilAutofillBound) return;
            table._ebaySprcDilAutofillBound = true;
            table.on('dataLoaded', function() {
                ebayScheduleSprcDilAutoApply({ delay: 500, flashClear: true, toast: true });
            });
            try {
                if ((typeof table.getDataCount === 'function' ? table.getDataCount() : 0) > 0) {
                    ebayScheduleSprcDilAutoApply({ delay: 500, flashClear: true, toast: true });
                }
            } catch (e) { /* wait for dataLoaded */ }
        }
        async function ebayTiktokSaveSpriceChunks(updates, extra) {
            extra = extra || {};
            if (!updates.length) return;
            const payload = ebayDgIsShopifyB2c()
                ? updates.map(function(u) { return Object.assign({ amz_sugg: 0 }, u); })
                : updates;
            const size = 200;
            for (let i = 0; i < payload.length; i += size) {
                const chunk = payload.slice(i, i + size);
                if (typeof saveChannelSpriceBatch === 'function'
                    && typeof chPromoCfg !== 'undefined' && chPromoCfg.saveSpriceBatchUrl) {
                    await saveChannelSpriceBatch(chunk, extra);
                } else {
                    await $.ajax({
                        url: '/shopify/save-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': ebayDgCsrf(),
                            'Accept': 'application/json',
                        },
                        data: { updates: chunk, _token: ebayDgCsrf(), skip_push: 1 },
                    });
                }
            }
        }
        function ebayTiktokMetricsPatch(d, sprice) {
            const margin = Number(d && d.percentage)
                || (typeof CHANNEL_PROMO_TAKEHOME === 'number' && CHANNEL_PROMO_TAKEHOME > 0
                    ? CHANNEL_PROMO_TAKEHOME
                    : ((typeof DEFAULT_TIKTOK_MARGIN_FACTOR === 'number' && DEFAULT_TIKTOK_MARGIN_FACTOR > 0)
                        ? DEFAULT_TIKTOK_MARGIN_FACTOR
                        : 0.8));
            const lp = Number(d && d.LP_productmaster) || 0;
            const ship = ebayDgExcludeShip() ? 0 : (Number(d && d.Ship_productmaster) || 0);
            const sgpft = sprice > 0 ? Math.round(((sprice * margin - ship - lp) / sprice) * 10000) / 100 : 0;
            const sroi = lp > 0 ? Math.round(((sprice * margin - lp - ship) / lp) * 10000) / 100 : 0;
            const patch = { SGPFT: sgpft, SPFT: sgpft, SROI: sroi, sgpft: sgpft, sroi: sroi, spft: sgpft };
            if (ebayDgIsDoba() || ebayDgIsDobaWithoutship()) {
                patch.s_self_pick = Math.max(0, ebayDgRound2(sprice - ship));
            }
            return patch;
        }
        function ebayTiktokRuleDiscount(d) {
            if (ebayDgIsDobaWithoutship()) {
                const copied = ebayDgDobaTabulatorSPick(d);
                if (copied > 0) return copied;
            }
            const meta = ebayDilGroiMetaForRow(d);
            if (meta && meta.sprc > 0) {
                let price = ebayDgIsFbMarketplace() && typeof fbMpRoundSprice === 'function'
                    ? fbMpRoundSprice(meta.sprc)
                    : ebayDgRound2(meta.sprc);
                if ((ebayDgIsShopifyB2c() || ebayDgIsMacys()) && typeof chPromoFinalSpriceToSave === 'function') {
                    price = chPromoFinalSpriceToSave(d, price);
                }
                if (!(price > 0)) return 0;
                return ebayDgIsFbMarketplace() && typeof fbMpRoundSprice === 'function'
                    ? fbMpRoundSprice(price)
                    : ebayDgRound2(price);
            }
            if (ebayDgIsMacys()) {
                const std = (typeof chPromoStdBase === 'function')
                    ? Number(chPromoStdBase(d))
                    : (Number(d && d.STANDARD_PRICE) || 0);
                if (std > 0) {
                    const price = (typeof chPromoFinalSpriceToSave === 'function')
                        ? Number(chPromoFinalSpriceToSave(d, std))
                        : ebayDgRound2(std);
                    return price > 0 ? ebayDgRound2(price) : 0;
                }
                return 0;
            }
            if (typeof chPromoSpriceFromStdTPromo === 'function') {
                const cvr = Number(chPromoSpriceFromStdTPromo(d, { skip_lmp_cap: true })) || 0;
                if (cvr > 0) {
                    let price = ebayDgRound2(cvr);
                    if (ebayDgIsShopifyB2c() && typeof chPromoFinalSpriceToSave === 'function') {
                        price = chPromoFinalSpriceToSave(d, price);
                    }
                    return price > 0 ? ebayDgRound2(price) : 0;
                }
            }
            return 0;
        }
        /**
         * TikTok / TikTok 2 / FB Marketplace / Shopify B2C / Doba / Doba Pickup / TopDawg / Macys:
         * wipe every stored S PRC and save 0, then insert the Dil / 0 Sold / CVR discount (not the LMP Diff).
         */
        async function ebayTiktokClearThenApplyAllRules(opts) {
            opts = opts || {};
            if (opts.persist !== true) return 0;
            const items = [];
            ebaySprcDilEachCatalogRow(function(row, d) {
                if (!ebayDgIsChild(d)) return;
                const sku = (typeof chPromoSku === 'function')
                    ? chPromoSku(d)
                    : String((d && d['(Child) sku']) || '').trim();
                if (!sku) return;
                items.push({ row: row, d: d, sku: sku });
            });
            if (!items.length) return 0;

            const blocked = typeof table !== 'undefined' && table && typeof table.blockRedraw === 'function';
            if (blocked) table.blockRedraw();
            try {
                items.forEach(function(item) {
                    if (item.row && typeof chPromoWipeSpriceRow === 'function') {
                        chPromoWipeSpriceRow(item.row);
                    } else if (item.row && typeof item.row.update === 'function') {
                        item.row.update({ SPRICE: 0, sprice: 0, has_custom_sprice: false, SGPFT: 0, SROI: 0, SPFT: 0 });
                    }
                    if (ebayDgIsShopifyB2c() && item.row && typeof item.row.update === 'function') {
                        item.row.update({ AMZ_SUGG_APPLIED: false });
                    }
                });
            } finally {
                if (blocked) table.restoreRedraw();
            }

            await ebayTiktokSaveSpriceChunks(
                items.map(function(i) { return { sku: i.sku, sprice: 0 }; }),
                { skip_push: true, queue_push: false }
            );

            const fills = [];
            const livePushOn = typeof chPromoPageReloadPushAllowed === 'function'
                && chPromoPageReloadPushAllowed();
            if (blocked) table.blockRedraw();
            try {
                items.forEach(function(item) {
                    const d = (item.row && typeof item.row.getData === 'function')
                        ? (item.row.getData() || item.d)
                        : item.d;
                    const price = ebayTiktokRuleDiscount(d);
                    if (!(price > 0)) return;
                    if (item.row && typeof item.row.update === 'function') {
                        const patch = (typeof chPromoSpricePatch === 'function')
                            ? chPromoSpricePatch(price)
                            : { SPRICE: price, sprice: price, has_custom_sprice: true };
                        item.row.update(Object.assign({}, patch, ebayTiktokMetricsPatch(d, price), {
                            SPRICE_STATUS: 'applied',
                        }));
                    }
                    fills.push({ sku: item.sku, sprice: price, row: item.row });
                });
            } finally {
                if (blocked) table.restoreRedraw();
            }

            if (fills.length) {
                await ebayTiktokSaveSpriceChunks(
                    fills.map(function(f) { return { sku: f.sku, sprice: f.sprice }; }),
                    { skip_push: true, queue_push: false }
                );
            }

            if (opts.push === true && livePushOn && fills.length) {
                if (typeof enqueueChannelPushSpriceAfterSave === 'function') {
                    fills.forEach(function(f) {
                        const d = (f.row && typeof f.row.getData === 'function') ? f.row.getData() : {};
                        const pushPrice = (window.SpriceLmpCap && typeof SpriceLmpCap.prepare === 'function')
                            ? SpriceLmpCap.prepare(d, f.sprice)
                            : f.sprice;
                        enqueueChannelPushSpriceAfterSave(f.sku, pushPrice, f.row);
                    });
                }
                ebayDgToast('success', 'S PRC cleared, then discount saved on ' + fills.length + ' SKU(s)');
            } else if (fills.length) {
                ebayDgToast('success', 'S PRC cleared, then discount saved on ' + fills.length + ' SKU(s)');
            }
            redrawEbaySprcDilColumn();
            try { if (typeof table !== 'undefined' && table) table.redraw(true); } catch (e) { /* ignore */ }
            return fills.length;
        }
        async function ebayApplySprcDilToTable(opts) {
            opts = opts || {};
            const persist = opts.persist === true;
            const allowPush = opts.push === true;
            if (!persist && !allowPush) {
                return ebayDgPaintMacysRuleSprice();
            }
            if (ebayDgApplyBusy) {
                ebayDgApplyPending = true;
                return 0;
            }
            ebayDgApplyBusy = true;
            try {
                if (ebayDgUsesClearThenApply()) {
                    return await ebayTiktokClearThenApplyAllRules({ persist: persist, push: allowPush });
                }
                const jobs = [];
                const nearly = typeof chPromoNearlyEqual === 'function'
                    ? chPromoNearlyEqual
                    : function(a, b) { return Math.abs((Number(a) || 0) - (Number(b) || 0)) < 0.005; };
                const livePushOn = typeof chPromoPageReloadPushAllowed === 'function'
                    && chPromoPageReloadPushAllowed();
                ebaySprcDilEachCatalogRow(function(row, d) {
                    const meta = ebayDilGroiMetaForRow(d);
                    if (!meta || !(meta.sprc > 0)) return;
                    const sku = (typeof chPromoSku === 'function')
                        ? chPromoSku(d)
                        : String((d && d['(Child) sku']) || '').trim();
                    if (!sku) return;
                    let price = meta.sprc;
                    if (typeof chPromoFinalSpriceToSave === 'function') {
                        price = chPromoFinalSpriceToSave(d, price);
                    } else if (typeof chPromoCapSpriceToLmp === 'function') {
                        price = chPromoCapSpriceToLmp(d, price);
                    }
                    if (!(price > 0)) return;
                    const current = typeof chPromoGetSprice === 'function'
                        ? chPromoGetSprice(d)
                        : (Number(d && d.SPRICE) || 0);
                    const live = typeof chPromoPrice === 'function'
                        ? chPromoPrice(d)
                        : (Number(d && (d['MC Price'] != null ? d['MC Price'] : d.price)) || 0);
                    const ended = typeof chPromoIsEndedListing === 'function' && chPromoIsEndedListing(d);
                    const needsFill = persist && !nearly(current, price);
                    const needsPush = !!(allowPush && livePushOn && !ended && current > 0 && live > 0 && !nearly(current, live));
                    if (!needsFill && !needsPush) return;
                    jobs.push({ row: row, sku: sku, price: price, needsFill: needsFill, needsPush: needsPush });
                });
                if (!jobs.length) return 0;
                const fillJobs = jobs.filter(function(j) { return j.needsFill; });
                async function ebayPersistSpriceUpdates(updates) {
                    if (!updates.length) return;
                    if (typeof saveChannelSpriceBatch === 'function'
                        && typeof chPromoCfg !== 'undefined' && chPromoCfg.saveSpriceBatchUrl) {
                        await saveChannelSpriceBatch(updates, { skip_push: true, queue_push: false });
                        return;
                    }
                    if (typeof saveChannelSprice !== 'function') return;
                    const run = function(u) {
                        return saveChannelSprice(u.sku, u.sprice, true, {
                            skip_push: true,
                            queue_push: false,
                        });
                    };
                    if (typeof chPromoMapLimit === 'function') {
                        await chPromoMapLimit(updates, 6, run);
                    } else {
                        for (let i = 0; i < updates.length; i++) {
                            await Promise.resolve(run(updates[i]));
                        }
                    }
                }
                const blocked = typeof table !== 'undefined' && table && typeof table.blockRedraw === 'function';
                if (fillJobs.length) {
                    if (blocked) table.blockRedraw();
                    try {
                        fillJobs.forEach(function(job) {
                            if (job.row && typeof chPromoWipeSpriceRow === 'function') {
                                chPromoWipeSpriceRow(job.row);
                            } else if (job.row && typeof job.row.update === 'function') {
                                job.row.update({ SPRICE: 0, sprice: 0, has_custom_sprice: false });
                            }
                        });
                    } finally {
                        if (blocked) table.restoreRedraw();
                    }
                    await ebayPersistSpriceUpdates(fillJobs.map(function(j) {
                        return { sku: j.sku, sprice: 0 };
                    }));
                }
                if (blocked) table.blockRedraw();
                try {
                    jobs.forEach(function(job) {
                        if (!job.row || typeof job.row.update !== 'function') return;
                        const status = job.needsPush ? 'queued' : 'applied';
                        if (typeof chPromoSpricePatch === 'function') {
                            job.row.update(Object.assign({}, chPromoSpricePatch(job.price), { SPRICE_STATUS: status }));
                        } else {
                            job.row.update({ SPRICE: job.price, sprice: job.price, has_custom_sprice: true, SPRICE_STATUS: status });
                        }
                    });
                } finally {
                    if (blocked) table.restoreRedraw();
                }
                await ebayPersistSpriceUpdates(fillJobs.map(function(j) {
                    return { sku: j.sku, sprice: j.price };
                }));
                const toQueue = jobs.filter(function(j) { return j.needsPush; });
                if (toQueue.length) {
                    if (typeof chPromoIsTemuPromoChannel === 'function' && chPromoIsTemuPromoChannel()
                        && typeof enqueueTemuListingPushAfterSave === 'function') {
                        toQueue.forEach(function(j) {
                            enqueueTemuListingPushAfterSave(j.sku, j.price, j.row);
                        });
                    } else if (typeof enqueueChannelPushSpriceAfterSave === 'function') {
                        toQueue.forEach(function(j) {
                            enqueueChannelPushSpriceAfterSave(j.sku, j.price, j.row);
                        });
                    } else if (typeof enqueueChannelPushSprice === 'function') {
                        enqueueChannelPushSprice(
                            toQueue.map(function(j) { return { sku: j.sku, price: j.price }; }),
                            { silent: true }
                        );
                    }
                    ebayDgToast('success', 'S PRC queued: ' + toQueue.length + ' SKU(s) — page close OK');
                }
                redrawEbaySprcDilColumn();
                return jobs.length;
            } finally {
                ebayDgApplyBusy = false;
                if (ebayDgApplyPending) {
                    ebayDgApplyPending = false;
                    ebayScheduleSprcDilAutoApply();
                }
            }
        }
        async function loadEbayDilGroiRules() {
            if (!$('#ebay-dil-groi-tbody tr').length) renderEbayDilGroiModalTable();
            $('#ebay-dil-groi-status').text('Loading saved slabs…');
            try {
                const res = await $.ajax({
                    url: ebayDgRulesUrl(),
                    method: 'GET',
                    dataType: 'json',
                    headers: { 'Accept': 'application/json' },
                });
                const fromServer = ebayNormalizeDilGroiList(
                    (res && Array.isArray(res.rules)) ? res.rules
                        : (res && res.rules && Array.isArray(res.rules.rules) ? res.rules.rules : [])
                );
                if (fromServer.length) ebayDilGroiRules = fromServer;
                if (res && res.cvr_adj) ebayPaintCvrGroiAdjTable(res.cvr_adj);
                renderEbayDilGroiModalTable();
                redrawEbaySprcDilColumn();
                ebayScheduleSprcDilAutoApply();
                $('#ebay-dil-groi-status').text(
                    fromServer.length && !(res && res.is_default)
                        ? 'Loaded saved Dil → GROI slabs from API.'
                        : 'Using first-time defaults (0.1–5 → 50 … 20–25 → 70, +5 each). Then Save and Apply.'
                );
            } catch (e) {
                renderEbayDilGroiModalTable();
                const reason = (e && e.responseJSON && e.responseJSON.message)
                    || (e && e.status ? ('HTTP ' + e.status) : 'network error');
                $('#ebay-dil-groi-status').text('Could not load saved rules from API (' + reason + ') — using defaults.');
                ebayScheduleSprcDilAutoApply();
            }
        }
        function saveEbayDilGroiRules() {
            const rules = readEbayDilGroiRulesFromModal();
            const cvrAdj = ebayCvrGroiAdjNow();
            ebayCvrGroiAdj = cvrAdj;
            return $.ajax({
                url: ebayDgRulesUrl(),
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': ebayDgCsrf(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify({ rules: rules, cvr_adj: cvrAdj, _token: ebayDgCsrf() }),
            }).then(async function(res) {
                if (res && Array.isArray(res.rules)) {
                    const saved = ebayNormalizeDilGroiList(res.rules);
                    if (saved.length) ebayDilGroiRules = saved;
                    renderEbayDilGroiModalTable();
                }
                if (res && res.cvr_adj) ebayPaintCvrGroiAdjTable(res.cvr_adj);
                const n = ebayDgUsesBackgroundRuleApply()
                    ? await ebayDgFlashThenPaintMacysRuleSprice({ toast: true })
                    : await ebayApplySprcDilToTable({ persist: true, push: true });
                $('#ebay-dil-groi-status').text(ebayDgIsPurchasingPower()
                    ? 'Saved via API. SPRICE cleared, then Dil painted on ' + n + ' SKU(s); apply + MCM price push queued in the background.'
                    : (ebayDgIsMacys()
                        ? 'Saved via API. SPRICE cleared, then Dil painted on ' + n + ' SKU(s); persist queued in the background.'
                        : ('Saved via API. S PRC applied on ' + n + ' SKU(s); only S PRC ≠ Price were queued.')));
                return res;
            });
        }
        function openEbayDilGroiModal() {
            const modalEl = document.getElementById('ebayDilGroiModal');
            if (!modalEl) return;
            renderEbayDilGroiModalTable();
            loadEbayDilGroiRules();
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        window.openEbayDilGroiModal = openEbayDilGroiModal;

        $(function() {
            $('#ebay-dil-groi-btn').off('click.ebaydg').on('click.ebaydg', function(e) {
                e.preventDefault();
                openEbayDilGroiModal();
            });
            $('#ebay-dil-groi-add-btn').off('click.ebaydg').on('click.ebaydg', function(e) {
                e.preventDefault();
                ebayDilGroiAddSlab();
            });
            $(document).off('click.ebaydg', '.ebay-dil-groi-row-del').on('click.ebaydg', '.ebay-dil-groi-row-del', function() {
                ebayDilGroiDeleteSlab(parseInt($(this).attr('data-idx'), 10));
            });
            $('#ebay-dil-groi-save-btn').off('click.ebaydg').on('click.ebaydg', async function(e) {
                e.preventDefault();
                const $btn = $(this);
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');
                try {
                    await saveEbayDilGroiRules();
                    ebayDgToast('success', 'Sprc Dil saved and applied');
                } catch (xhr) {
                    ebayDgToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
                } finally {
                    $btn.prop('disabled', false).html(html);
                }
            });
            $(document).off('input.ebayDilGroi change.ebayDilGroi', '#ebay-dil-groi-tbody .ebay-dil-groi-input')
                .on('input.ebayDilGroi change.ebayDilGroi', '#ebay-dil-groi-tbody .ebay-dil-groi-input', function() {
                    const first = $('#ebay-dil-groi-tbody .ebay-dg-groi').get(0);
                    if (this === first) cascadeEbayDilGroiFromFirst();
                    else readEbayDilGroiRulesFromModal();
                    renderEbayDilGroiCounts();
                    ebayAfterDilGroiRulesChanged();
                });
            $(document).off('input.ebayCvrGroi change.ebayCvrGroi', '#ebay-cvr-groi-table .ebay-cvr-groi-input')
                .on('input.ebayCvrGroi change.ebayCvrGroi', '#ebay-cvr-groi-table .ebay-cvr-groi-input', function() {
                    ebayCvrGroiAdj = ebayCvrGroiAdjNow();
                    renderEbayDilGroiCounts();
                    ebayAfterDilGroiRulesChanged();
                });
            $('#ebayDilGroiModal').off('shown.bs.modal.ebaydg').on('shown.bs.modal.ebaydg', function() {
                setTimeout(function() { renderEbayDilGroiPies(); }, 50);
            });
            $('#ebayDilGroiModal').off('hidden.bs.modal.ebaydg').on('hidden.bs.modal.ebaydg', function() {
                destroyEbayDilGroiPies();
            });
            $(document).off('click.ebaydghist', '.ebay-dg-hist-dot').on('click.ebaydghist', '.ebay-dg-hist-dot', function() {
                const chart = String($(this).data('chart') || 'dil');
                const band = String($(this).data('band') || '');
                if (!band) return;
                ebayDgOpenHist(chart, band);
            });
            $('#ebay-dg-hist-close').off('click.ebaydghist').on('click.ebaydghist', function() {
                $('#ebay-dg-hist-wrap').removeClass('is-open');
            });
            Promise.resolve(loadEbayDilGroiRules()).catch(function() { /* defaults */ });
            bindEbaySprcDilAutofill();
        });
@endif
