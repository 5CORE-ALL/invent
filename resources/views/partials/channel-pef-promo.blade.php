{{--
  Channel-independent Dil vs PRMT / CVR vs CPN + PRMT% / CPN% / Appr / DSC% / Push Prc / sprice ?
  Data stored on each channel's *_data_view.value with Amazon-format keys
  (PEF_PRMT_PCT, PEF_CPN_PCT, PEF_DSC_PCT, PEF_APPR, PUSH_PRC_STATUS, PUSH_PRC_VALUE, PUSH_PRC_PUSHED_AT).
  eBay1 → ebay_data_view  |  eBay2 → ebay_two_data_views  |  eBay3 → ebay3_data_view
  Include: css | buttons | modals | script | all  + channelPromoChannel key.
--}}
@php
    $channelPromoPart = $channelPromoPart ?? 'all';
    $channelPromoChannel = $channelPromoChannel ?? 'ebay1';
@endphp

@if($channelPromoPart === 'css' || $channelPromoPart === 'all')
        /* Dil vs PRMT / CVR vs CPN — channel promo (ch-promo-*) */
        .ch-pef-promo-cell {
            font-size: inherit;
            font-weight: 600;
            color: #64748b;
        }
        .ch-pef-promo-cell.has-val { color: #0f172a; }
        .tabulator-row .tabulator-cell[tabulator-field="prmt_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="cpn_pct"],
        .tabulator-row .tabulator-cell[tabulator-field="dsc"],
        .tabulator-row .tabulator-cell[tabulator-field="appr"] {
            padding: 2px 4px !important;
        }
        #ch-promo-dil-prmt-table .ch-promo-dil-prmt-input,
        #ch-promo-cvr-cpn-table .ch-promo-cvr-cpn-input,
        #ch-promo-prmt-menu-btn {
            background: #198754;
            border-color: #198754;
            color: #fff;
        }
        #ch-promo-prmt-menu-btn:hover,
        #ch-promo-prmt-menu-btn:focus,
        #ch-promo-prmt-menu-btn.show {
            background: #157347;
            border-color: #146c43;
            color: #fff;
        }
        #ch-promo-cpn-menu-btn {
            background: #20c997;
            border-color: #20c997;
            color: #fff;
        }
        #ch-promo-cpn-menu-btn:hover,
        #ch-promo-cpn-menu-btn:focus,
        #ch-promo-cpn-menu-btn.show {
            background: #1aa179;
            border-color: #1aa179;
            color: #fff;
        }
        #ch-promo-sprice-recalc-btn {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        #ch-promo-sprice-recalc-btn:hover,
        #ch-promo-sprice-recalc-btn:focus {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        #ch-promo-sprice-recalc-btn:disabled {
            opacity: 0.65;
        }
        #ch-promo-push-prc-progress {
            display: none;
            width: 100%;
            min-width: 220px;
            max-width: 420px;
            margin: 4px 0 0;
            padding: 6px 10px;
            border: 1px solid #ffe08a;
            border-radius: 8px;
            background: #fffbeb;
        }
        #ch-promo-push-prc-progress.active { display: block; }
        #ch-promo-push-prc-progress.done {
            border-color: #86efac;
            background: #f0fdf4;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-progress-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 4px;
            font-size: 12px;
            line-height: 1.2;
        }
        #ch-promo-push-prc-progress-pct {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #b45309;
            min-width: 2.5em;
        }
        #ch-promo-push-prc-progress.done #ch-promo-push-prc-progress-pct {
            color: #15803d;
        }
        #ch-promo-push-prc-progress-msg {
            color: #64748b;
            flex: 1;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-bar {
            height: 10px;
            border-radius: 999px;
            background: #fde68a;
            overflow: hidden;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-bar {
            background: #bbf7d0;
        }
        #ch-promo-push-prc-progress .ch-promo-push-prc-bar > span {
            display: block;
            height: 100%;
            width: 0%;
            background: #f59e0b;
            transition: width 0.2s ease, background 0.25s ease;
            border-radius: 999px;
        }
        #ch-promo-push-prc-progress.done .ch-promo-push-prc-bar > span {
            background: #22c55e;
        }
        #ch-promo-push-prc-progress.has-fail.done .ch-promo-push-prc-bar > span {
            background: linear-gradient(90deg, #22c55e 70%, #f59e0b 100%);
        }
        #chPromoCvrVsCpnModal .modal-content {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        #chPromoCvrVsCpnModal .modal-header,
        #chPromoCvrVsCpnModal .modal-footer {
            background: #f3e8ff;
            border-color: #e9d5ff;
        }
        #chPromoCvrVsCpnModal .modal-body {
            background: #f3e8ff;
        }
        #chPromoCvrVsCpnModal .table {
            background: #fff;
        }
        #chPromoCvrVsCpnModal .table thead.table-light th {
            background: #ede9fe;
        }
@endif

@if($channelPromoPart === 'buttons' || $channelPromoPart === 'all')
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="ch-promo-prmt-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="Dil vs PRMT rules + apply/push Prmt%">
                            <i class="fas fa-sliders-h"></i> Prmt%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="ch-promo-prmt-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="ch-promo-dil-vs-prmt-btn">
                                    <i class="fas fa-sliders-h me-1 text-success"></i> Dil vs PRMT…
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="ch-promo-push-prmt-btn">
                                    <i class="fas fa-upload me-1 text-success"></i> Push Prmt%
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm dropdown-toggle" id="ch-promo-cpn-menu-btn"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="CVR Vs CPN rules + Push CPN% (eBay1 → public coded coupon)">
                            CVR Vs CPN
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="ch-promo-cpn-menu-btn">
                            <li>
                                <a class="dropdown-item" href="#" id="ch-promo-cvr-vs-cpn-btn">
                                    CVR vs CPN…
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" id="ch-promo-push-cpn-btn">
                                    <i class="fas fa-upload me-1" style="color:#20c997;"></i> Push CPN%
                                </a>
                            </li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-sm" id="ch-promo-sprice-recalc-btn"
                        title="Clear S PRC, then refill using Push Prc formula (Std − PRMT%) — no marketplace push. Skips INV = 0. Selected SKUs if checked; otherwise all visible.">
                        sprice ?
                    </button>
                    <div id="ch-promo-push-prc-progress" aria-live="polite" title="Push Prc sequential progress">
                        <div class="ch-promo-push-prc-progress-meta">
                            <span id="ch-promo-push-prc-progress-pct">0%</span>
                            <span id="ch-promo-push-prc-progress-msg">Ready</span>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" id="ch-promo-push-prc-cancel-btn"
                                style="display:none;font-size:11px;line-height:1.2;" title="Cancel remaining Push Prc">
                                Cancel
                            </button>
                        </div>
                        <div class="ch-promo-push-prc-bar"><span id="ch-promo-push-prc-progress-bar"></span></div>
                    </div>
@endif

@if($channelPromoPart === 'modals' || $channelPromoPart === 'all')

    <div class="modal fade" id="chPromoCvrVsCpnModal" tabindex="-1" aria-labelledby="chPromoCvrVsCpnModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoCvrVsCpnModalLabel">
                        CVR vs CPN
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map CVR% slabs to <strong>CPN %</strong>. Apply saves rules and CPN% to the database only
                        (no eBay coupon). Use <strong>Push CPN%</strong> to create/add public coupons.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-cvr-cpn-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">CVR%</th>
                                    <th style="width:45%;" class="text-end">CPN %</th>
                                </tr>
                            </thead>
                            <tbody id="ch-promo-cvr-cpn-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-cvr-cpn-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-cvr-cpn-apply-btn"
                        title="Save CVR→CPN rules and CPN% to the database only — does not create eBay coupons">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="chPromoDilVsPrmtModal" tabindex="-1" aria-labelledby="chPromoDilVsPrmtModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chPromoDilVsPrmtModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs PRMT
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Map Dil% slabs to PRMT%. First-time defaults: <strong>&gt; 100% → 0</strong> up to
                        <strong>0–10% → 10</strong>. <strong>Apply</strong> saves rules for this channel
                        and fills <strong>PRMT %</strong> from each row’s Dil% / discounts <strong>S PRC</strong>.
                        If <strong>INV = 0</strong>, PRMT% is forced to <strong>0</strong>.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ch-promo-dil-prmt-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:55%;">Dil%</th>
                                    <th style="width:45%;" class="text-end">PRMT %</th>
                                </tr>
                            </thead>
                            <tbody id="ch-promo-dil-prmt-tbody"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="ch-promo-dil-prmt-status"></div>
                </div>
                <div class="modal-footer py-2 flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="ch-promo-dil-prmt-apply-btn"
                        title="Save Dil→PRMT rules, then apply PRMT% — selected rows if checked, otherwise all visible">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($channelPromoPart === 'script' || $channelPromoPart === 'all')

        // ==================== Channel PEF Promo (channel_promo_pricing) ====================
        const CHANNEL_PROMO_CHANNEL = @json($channelPromoChannel ?? 'ebay1');
        const CHANNEL_PROMO_CFG = {
            ebay1: {
                label: 'eBay',
                saveSpriceUrl: '/ebay-one/save-sprice',
                pushPriceUrl: '/push-ebay-price-tabulator',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            ebay2: {
                label: 'eBay2',
                saveSpriceUrl: '/save-ebay2-sprice',
                pushPriceUrl: '/push-ebay2-price',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            ebay2op: {
                label: 'eBay2 OP',
                saveSpriceUrl: '/save-ebay2-sprice',
                pushPriceUrl: '/push-ebay2-price',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            ebay3: {
                label: 'eBay3',
                saveSpriceUrl: '/ebay3/save-sprice',
                pushPriceUrl: '/push-ebay3-price-tabulator',
                priceField: 'eBay Price',
                cvrField: 'SCVR',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            shopify_b2c: {
                label: 'Shopify B2C',
                saveSpriceUrl: '/shopify/save-sprice',
                pushPriceUrl: '/push-shopify-b2c-price',
                priceField: 'Price',
                cvrField: 'CVR%',
                dilField: 'DIL%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            shopify_b2b: {
                label: 'Shopify B2B',
                saveSpriceUrl: '/shopify-b2b/save-sprice',
                pushPriceUrl: null,
                priceField: 'Price',
                cvrField: 'CVR%',
                dilField: 'DIL%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            macys: {
                label: 'Macys',
                saveSpriceUrl: '/macys-save-sprice-tabulator',
                pushPriceUrl: null,
                priceField: 'MC Price',
                cvrField: 'CVR%',
                dilField: 'MC Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            reverb: {
                label: 'Reverb',
                saveSpriceUrl: '/reverb-save-sprice',
                pushPriceUrl: null,
                priceField: 'RV Price',
                cvrField: 'CVR%',
                dilField: 'RV Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'updates',
            },
            walmart: {
                label: 'Walmart',
                saveSpriceUrl: '/save-walmart-sprice',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR_L30',
                dilField: 'E Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            wayfair: {
                label: 'Wayfair',
                saveSpriceUrl: '/wayfair/pricing-save-sprice',
                pushPriceUrl: null,
                priceField: 'price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: 'sku',
                saveSpriceMode: 'updates',
            },
            temu: {
                label: 'Temu',
                saveSpriceUrl: '/temu-pricing/save-sprice',
                pushPriceUrl: '/temu/push-price',
                priceField: 'temu_price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: 'sku',
                saveSpriceMode: 'sku',
            },
            temu2: {
                label: 'Temu 2',
                saveSpriceUrl: '/temu2-pricing/save-sprice',
                pushPriceUrl: '/temu2/push-price',
                priceField: 'temu_price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: 'sku',
                saveSpriceMode: 'sku',
            },
            doba: {
                label: 'Doba',
                saveSpriceUrl: '/doba/save-sprice',
                pushPriceUrl: '/doba/push-price',
                priceField: 'doba Price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
            doba_withoutship: {
                label: 'Doba (no ship)',
                saveSpriceUrl: '/doba/save-sprice-withoutship',
                pushPriceUrl: '/doba/push-price',
                priceField: 'doba Price',
                cvrField: 'CVR%',
                dilField: 'Dil%',
                invField: 'INV',
                skuField: '(Child) sku',
                saveSpriceMode: 'sku',
            },
        };
        CHANNEL_PROMO_CFG.macy = CHANNEL_PROMO_CFG.macys;
        const chPromoCfg = CHANNEL_PROMO_CFG[CHANNEL_PROMO_CHANNEL] || {};
        const CH_PROMO_SAVE_URL = '/channel-promo-pricing/save';
        const CH_PROMO_RULES_BASE = '/channel-promo-pricing/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL);
        const CH_PROMO_EBAY1_COUPON_URL = '/pricing-errors-fix-ebay1-coupon';
        const CH_PROMO_EBAY1_PROMOTION_URL = '/pricing-errors-fix-ebay1-promotion';

        function chPromoPushPrcHasSaleCoupon() {
            return CHANNEL_PROMO_CHANNEL === 'ebay1';
        }

        /** eBay1: create/update ORDER_DISCOUNT sale event from PRMT%. 0 pauses. */
        function syncEbay1Promotion(sku, percent) {
            return new Promise(function(resolve) {
                $.ajax({
                    url: CH_PROMO_EBAY1_PROMOTION_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                    data: { sku: sku, percent: percent, _token: chPromoCsrf() },
                }).done(function(res) {
                    resolve({
                        ok: !!(res && res.success),
                        message: (res && res.message) || '',
                        promotion_id: (res && res.promotion_id) || null,
                    });
                }).fail(function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'eBay1 sale event API error';
                    resolve({ ok: false, message: msg, promotion_id: null });
                });
            });
        }

        /** eBay1: push CPN% to public coded coupon (create or add SKU to same-% campaign). */
        function syncEbay1CodedCoupon(sku, percent) {
            return new Promise(function(resolve) {
                $.ajax({
                    url: CH_PROMO_EBAY1_COUPON_URL,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                    data: { sku: sku, percent: percent, _token: chPromoCsrf() },
                }).done(function(res) {
                    resolve({
                        ok: !!(res && res.success),
                        message: (res && res.message) || '',
                        coupon_code: (res && res.coupon_code) || null,
                        promotion_id: (res && res.promotion_id) || null,
                    });
                }).fail(function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'eBay1 coupon API error';
                    resolve({ ok: false, message: msg, coupon_code: null, promotion_id: null });
                });
            });
        }

        async function chPromoMapLimit(items, limit, fn) {
            const n = Math.max(1, Number(limit) || 8);
            let i = 0;
            async function worker() {
                while (i < items.length) {
                    const idx = i++;
                    await fn(items[idx], idx);
                }
            }
            const workers = [];
            for (let w = 0; w < Math.min(n, items.length); w++) workers.push(worker());
            await Promise.all(workers);
        }

        const CH_PEF_DIL_PRMT_DEFAULTS = [
            { key: '0-10', label: '0–10%', prmt: 10 },
            { key: '10-20', label: '10–20%', prmt: 9 },
            { key: '20-30', label: '20–30%', prmt: 8 },
            { key: '30-40', label: '30–40%', prmt: 7 },
            { key: '40-50', label: '40–50%', prmt: 6 },
            { key: '50-60', label: '50–60%', prmt: 5 },
            { key: '60-70', label: '60–70%', prmt: 4 },
            { key: '70-80', label: '70–80%', prmt: 3 },
            { key: '80-90', label: '80–90%', prmt: 2 },
            { key: '90-100', label: '90–100%', prmt: 1 },
            { key: 'gt-100', label: '> 100%', prmt: 0 },
        ];
        const CH_PEF_CVR_CPN_DEFAULTS = [
            { key: 'eq-0', label: '0%', cpn: 10 },
            { key: '0.01-1', label: '0.01–1%', cpn: 9 },
            { key: '1-1.5', label: '1–1.5%', cpn: 8 },
            { key: '1.5-2', label: '1.5–2%', cpn: 7 },
            { key: '2-3', label: '2–3%', cpn: 6 },
            { key: '3-4', label: '3–4%', cpn: 5 },
            { key: '4-5', label: '4–5%', cpn: 4 },
            { key: '5-6', label: '5–6%', cpn: 3 },
            { key: '6-6.5', label: '6–6.5%', cpn: 2 },
            { key: '6.5-7', label: '6.5–7%', cpn: 1 },
            { key: 'gt-7', label: '> 7%', cpn: 0 },
        ];

        let chPromoDilPrmtRules = CH_PEF_DIL_PRMT_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chPromoCvrCpnRules = CH_PEF_CVR_CPN_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chPromoPushPrcCancel = false;
        let chPromoPushPrcPollTimer = null;
        let chPromoPushPrcLastToastKey = '';
        const CH_PROMO_PUSH_QUEUE_CHANNELS = ['ebay1', 'ebay2', 'ebay2op', 'ebay3'];
        const chPromoPushQueueEnabled = CH_PROMO_PUSH_QUEUE_CHANNELS.indexOf(CHANNEL_PROMO_CHANNEL) !== -1;
        const CH_PROMO_PUSH_QUEUE_URL = '/channel-push-prc/' + encodeURIComponent(CHANNEL_PROMO_CHANNEL);

        function chPromoCsrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
        function chPromoRound2(n) {
            return Math.round((Number(n) || 0) * 100) / 100;
        }
        function chPromoToast(type, msg) {
            if (typeof showToast === 'function') {
                try { showToast(type, msg); } catch (e) { showToast(msg, type); }
            } else if (typeof toast === 'function') toast(msg, type);
            else console.log(type, msg);
        }
        function chPromoSku(d) {
            const f = chPromoCfg.skuField || '(Child) sku';
            return String((d && (d[f] || d['(Child) sku'] || d.sku)) || '').trim();
        }
        function chPromoSkuKey(sku) {
            return String(sku == null ? '' : sku).trim().toUpperCase();
        }
        function chPromoIsChildRow(d) {
            return !!(d && !d.is_parent_summary && chPromoSku(d) && String(chPromoSku(d)).indexOf('PARENT') === -1);
        }
        function chPromoInv(d) {
            return Number(d[chPromoCfg.invField || 'INV']) || Number(d.INV) || 0;
        }
        function chPromoPrice(d) {
            const f = chPromoCfg.priceField;
            let p = f ? Number(d[f]) : NaN;
            if (isFinite(p) && p > 0) return p;
            // Macys: sheet field (MC Price) only — never fall back to a generic/product price.
            if (CHANNEL_PROMO_CHANNEL === 'macys' || CHANNEL_PROMO_CHANNEL === 'macy') {
                return 0;
            }
            p = Number(d.price);
            if (isFinite(p) && p > 0) return p;
            return 0;
        }
        function chPromoGetSprice(d) {
            let s = Number(d.SPRICE);
            if (isFinite(s) && s > 0) return s;
            s = Number(d.sprice);
            if (isFinite(s) && s > 0) return s;
            return 0;
        }
        function chPromoSpricePatch(val) {
            const n = Number(val);
            const patch = { SPRICE: val, sprice: val };
            // Keep S PRC visible even when it equals listing price (ebay formatter hides matches)
            if (isFinite(n) && n > 0) patch.has_custom_sprice = true;
            return patch;
        }
        function chPromoStdBase(d) {
            const std = Number(d.STANDARD_PRICE) || Number(d.standard_price) || 0;
            if (std > 0) return chPromoRound2(std);
            const price = chPromoPrice(d);
            return price > 0 ? chPromoRound2(price) : 0;
        }
        function chPromoDil(d) {
            const inv = chPromoInv(d);
            // eBay Dil column = (OV L30 / INV) × 100 — must match for Dil vs PRMT slabs
            // (backend E Dil% is eBay L30/INV ratio, not the % shown in the Dil column)
            if (String(CHANNEL_PROMO_CHANNEL).indexOf('ebay') === 0) {
                if (inv <= 0) return 0;
                const ovl30 = Number(d['L30'] != null ? d['L30'] : d.L30) || 0;
                return (ovl30 / inv) * 100;
            }
            let dil = Number(d[chPromoCfg.dilField]);
            if (!isFinite(dil)) {
                const l30 = Number(d['eBay L30'] || d.L30 || d['MC L30'] || d['W_L30'] || d['B2B L30'] || 0) || 0;
                dil = inv > 0 ? (l30 / inv) : 0;
            }
            if (dil > 0 && dil <= 2) dil = dil * 100;
            return dil;
        }
        function chPromoCvr(d) {
            const f = chPromoCfg.cvrField;
            let cvr = Number(d[f]);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d.CVR_L30);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d['CVR%']);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            cvr = Number(d.SCVR);
            if (isFinite(cvr) && cvr >= 0) return cvr;
            const views = Number(d.Views || d.Sess30 || d.views || 0) || 0;
            const l30 = Number(d['eBay L30'] || d['B2B L30'] || d['MC L30'] || d['W_L30'] || d.L30 || 0) || 0;
            return views > 0 ? chPromoRound2((l30 / views) * 100) : 0;
        }
        function parseChPromoPercentAmount(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (!s) return null;
            const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num === 0) return null;
            return { type: 'percent', value: Math.abs(num) };
        }
        function parseChPromoPercentAllowZero(raw) {
            const s = String(raw == null ? '' : raw).trim();
            if (s === '') return null;
            const num = parseFloat(s.replace(/[%$,\s]/g, '').replace(',', '.'));
            if (!isFinite(num) || num < 0) return null;
            return { type: 'percent', value: Math.abs(num) };
        }
        function applyChPromoToSpriceBase(base, promo) {
            if (!(base > 0) || !promo) return null;
            const next = base * (1 - (promo.value / 100));
            return Math.max(0.01, chPromoRound2(next));
        }
        function getChPromoDiscountBase(d, appliedKey) {
            let base = chPromoGetSprice(d) > 0 ? chPromoGetSprice(d) : chPromoPrice(d);
            const prev = Number(d[appliedKey] || 0) || 0;
            if (prev > 0 && prev < 100) base = base / (1 - (prev / 100));
            return chPromoRound2(base);
        }
        function fmtChPromoCell(value, placeholder) {
            if (value === null || value === undefined || value === '') {
                return '<span class="ch-pef-promo-cell">' + placeholder + '</span>';
            }
            return '<span class="ch-pef-promo-cell has-val">' + String(value) + '</span>';
        }
        function chPromoEscAttr(s) {
            if (typeof escAttr === 'function') return escAttr(s);
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        function chPromoHistoryDotHtml(sku, metric, pct) {
            if (!sku) return '';
            const n = Number(pct);
            const has = isFinite(n) && n > 0;
            let color = '#adb5bd';
            let label = metric;
            if (metric === 'cpn') { color = has ? '#20c997' : '#adb5bd'; label = 'CPN%'; }
            else if (metric === 'prmt') { color = has ? '#0d6efd' : '#adb5bd'; label = 'PRMT%'; }
            else if (metric === 'push_prc') { color = has ? '#FF9900' : '#adb5bd'; label = 'Push Prc'; }
            const tip = label
                + (has ? (metric === 'push_prc' ? (' = $' + Number(n).toFixed(2)) : (' = ' + n + '%')) : '')
                + ' — click for daily history (PDT)';
            return '<button type="button" class="btn btn-sm p-0 view-sku-chart ch-pef-hist-dot align-middle" '
                + 'data-sku="' + chPromoEscAttr(sku) + '" data-metric="' + chPromoEscAttr(metric) + '" '
                + 'title="' + chPromoEscAttr(tip) + '" '
                + 'style="border:none;background:none;cursor:pointer;padding:0;line-height:1;vertical-align:middle;">'
                + '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;'
                + 'background:' + color + ';flex-shrink:0;"></span></button>';
        }

        function saveChannelPromoFields(sku, fields) {
            const payload = Object.assign({
                channel: CHANNEL_PROMO_CHANNEL,
                sku: sku,
                _token: chPromoCsrf(),
            }, fields || {});
            return $.ajax({
                url: CH_PROMO_SAVE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: payload,
            });
        }

        function saveChannelSprice(sku, sprice, silent) {
            const val = chPromoRound2(sprice);
            if (!sku || !chPromoCfg.saveSpriceUrl) {
                return $.Deferred().reject().promise();
            }
            let data;
            if (chPromoCfg.saveSpriceMode === 'updates') {
                data = { updates: [{ sku: sku, sprice: val }], _token: chPromoCsrf() };
            } else {
                data = { sku: sku, sprice: val, _token: chPromoCsrf() };
            }
            return $.ajax({
                url: chPromoCfg.saveSpriceUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: data,
            }).done(function() {
                if (!silent) chPromoToast('success', 'S PRC updated');
            }).fail(function() {
                if (!silent) chPromoToast('error', 'Failed to save S PRC');
            });
        }

        /** Persist promo % first (so refresh keeps PRMT/CPN), then optional S PRC. Returns a Promise. */
        function saveChannelSpriceAndPromo(row, sprice, silent, extra) {
            const d = row.getData();
            const sku = chPromoSku(d);
            const val = chPromoRound2(sprice);
            extra = extra || {};
            if (!sku) return Promise.resolve(null);
            if (val > 0) {
                row.update(chPromoSpricePatch(val));
            }
            const promoFields = {};
            if (extra.prmt_pct !== undefined && extra.prmt_pct !== null) {
                promoFields.prmt_pct = Number(extra.prmt_pct) || 0;
            }
            if (extra.cpn_pct !== undefined && extra.cpn_pct !== null) {
                promoFields.cpn_pct = Number(extra.cpn_pct) || 0;
            }
            if (extra.dsc !== undefined && extra.dsc !== null) {
                promoFields.dsc = Number(extra.dsc) || 0;
            }
            if (extra.appr !== undefined) {
                promoFields.appr = extra.appr ? 1 : 0;
            }

            const applyPromoPres = function(pres, updates) {
                if (pres) {
                    if (pres.prmt_pct !== undefined && pres.prmt_pct !== null) {
                        updates.prmt_pct = String(pres.prmt_pct);
                        updates._prmt_pct_applied = Number(pres.prmt_pct) || 0;
                    }
                    if (pres.cpn_pct !== undefined && pres.cpn_pct !== null) {
                        updates.cpn_pct = String(pres.cpn_pct);
                        updates._cpn_pct_applied = Number(pres.cpn_pct) || 0;
                    }
                    if (pres.dsc !== undefined && pres.dsc !== null) {
                        updates.dsc = String(pres.dsc);
                        updates._dsc_applied = Number(pres.dsc) || 0;
                    }
                    if (pres.appr !== undefined) updates.appr = !!pres.appr;
                } else {
                    if (promoFields.prmt_pct !== undefined) {
                        updates.prmt_pct = String(promoFields.prmt_pct);
                        updates._prmt_pct_applied = Number(promoFields.prmt_pct) || 0;
                    }
                    if (promoFields.cpn_pct !== undefined) {
                        updates.cpn_pct = String(promoFields.cpn_pct);
                        updates._cpn_pct_applied = Number(promoFields.cpn_pct) || 0;
                    }
                    if (promoFields.dsc !== undefined) {
                        updates.dsc = String(promoFields.dsc);
                        updates._dsc_applied = Number(promoFields.dsc) || 0;
                    }
                    if (promoFields.appr !== undefined) updates.appr = !!promoFields.appr;
                }
            };

            return new Promise(function(resolve) {
                const afterPromo = function(pres) {
                    const updates = {};
                    applyPromoPres(pres, updates);
                    const finish = function(response) {
                        if (response) {
                            if (response.data !== undefined) Object.assign(updates, chPromoSpricePatch(response.data));
                            if (response.sgpft_percent !== undefined) updates.SGPFT = response.sgpft_percent;
                            if (response.spft_percent !== undefined) updates['Spft%'] = response.spft_percent;
                            if (response.sroi_percent !== undefined) updates.SROI = response.sroi_percent;
                            if (response.sgroi_percent !== undefined) updates.SGROI = response.sgroi_percent;
                        }
                        if (Object.keys(updates).length) row.update(updates);
                        if (!silent) chPromoToast('success', 'S PRC / promo updated');
                        resolve(pres || response || null);
                    };
                    if (val > 0 && chPromoCfg.saveSpriceUrl) {
                        saveChannelSprice(sku, val, true).done(finish).fail(function() {
                            if (!silent) chPromoToast('error', 'Failed to save S PRC');
                            finish(null);
                        });
                    } else {
                        finish(null);
                    }
                };

                if (Object.keys(promoFields).length) {
                    saveChannelPromoFields(sku, promoFields)
                        .done(function(pres) { afterPromo(pres); })
                        .fail(function() {
                            if (!silent) chPromoToast('error', 'Promo save failed');
                            // Still keep UI values and try S PRC
                            afterPromo(null);
                        });
                } else if (val > 0 && chPromoCfg.saveSpriceUrl) {
                    afterPromo(null);
                } else {
                    resolve(null);
                }
            });
        }

        function collectChPromoSelectedRows() {
            if (typeof table === 'undefined' || !table) return [];
            // Merge selectedSkus + selectedRows + checked DOM boxes (eBay uses selectedSkus;
            // Amazon aliases both). Match case-insensitively like other ebay bulk actions.
            const keys = new Set();
            function addSku(s) {
                const k = chPromoSkuKey(s);
                if (k) keys.add(k);
            }
            if (typeof selectedSkus !== 'undefined' && selectedSkus && selectedSkus.forEach) {
                selectedSkus.forEach(addSku);
            }
            if (typeof selectedRows !== 'undefined' && selectedRows && selectedRows.forEach) {
                selectedRows.forEach(addSku);
            }
            try {
                document.querySelectorAll('.sku-select-checkbox:checked').forEach(function(el) {
                    addSku(el.getAttribute('data-sku') || (el.dataset && el.dataset.sku) || '');
                });
            } catch (e) { /* ignore */ }
            if (!keys.size) return [];
            return table.getRows().filter(function(row) {
                const d = row.getData();
                return chPromoIsChildRow(d) && keys.has(chPromoSkuKey(chPromoSku(d)));
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }
        function collectChPromoVisibleRows() {
            if (typeof table === 'undefined' || !table) return [];
            return table.getRows('active').filter(function(row) {
                return chPromoIsChildRow(row.getData());
            }).map(function(row) {
                return { row: row, d: row.getData() };
            });
        }

        function chPromoDilSlabKey(dil) {
            const n = Number(dil);
            if (!isFinite(n) || n < 0) return '0-10';
            if (n > 100) return 'gt-100';
            if (n >= 90) return '90-100';
            if (n >= 80) return '80-90';
            if (n >= 70) return '70-80';
            if (n >= 60) return '60-70';
            if (n >= 50) return '50-60';
            if (n >= 40) return '40-50';
            if (n >= 30) return '30-40';
            if (n >= 20) return '20-30';
            if (n >= 10) return '10-20';
            return '0-10';
        }
        function chPromoPrmtForDil(dil) {
            const key = chPromoDilSlabKey(dil);
            const rule = chPromoDilPrmtRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.prmt);
            return isFinite(n) && n >= 0 ? n : 0;
        }
        function chPromoCvrSlabKey(cvr) {
            const n = Number(cvr);
            if (!isFinite(n) || n <= 0) return 'eq-0';
            if (n > 7) return 'gt-7';
            if (n >= 6.5) return '6.5-7';
            if (n >= 6) return '6-6.5';
            if (n >= 5) return '5-6';
            if (n >= 4) return '4-5';
            if (n >= 3) return '3-4';
            if (n >= 2) return '2-3';
            if (n >= 1.5) return '1.5-2';
            if (n >= 1) return '1-1.5';
            return '0.01-1';
        }
        function chPromoCpnForCvr(cvr) {
            const key = chPromoCvrSlabKey(cvr);
            const rule = chPromoCvrCpnRules.find(function(r) { return r.key === key; });
            if (!rule) return 0;
            const n = Number(rule.cpn);
            return isFinite(n) && n >= 0 ? n : 0;
        }

        function renderChPromoDilPrmtModalTable() {
            const $tb = $('#ch-promo-dil-prmt-tbody').empty();
            chPromoDilPrmtRules.forEach(function(r, idx) {
                const prmt = isFinite(Number(r.prmt)) ? Number(r.prmt) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-dil-prmt-input" '
                    + 'min="0" step="0.1" value="' + prmt + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readChPromoDilPrmtRulesFromModal() {
            $('#ch-promo-dil-prmt-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.ch-promo-dil-prmt-input').val());
                const rule = chPromoDilPrmtRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.prmt = (isFinite(val) && val >= 0) ? val : 0;
            });
            return chPromoDilPrmtRules.map(function(r) {
                return { key: r.key, label: r.label, prmt: Number(r.prmt) || 0 };
            });
        }
        async function loadChPromoDilPrmtRules() {
            $('#ch-promo-dil-prmt-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: CH_PROMO_RULES_BASE + '/dil-prmt',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    chPromoDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderChPromoDilPrmtModalTable();
                $('#ch-promo-dil-prmt-status').text(res && res.is_default
                    ? 'Using first-time defaults (0–10). Apply to save & apply.'
                    : 'Loaded saved Dil vs PRMT rules for ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '.');
            } catch (e) {
                renderChPromoDilPrmtModalTable();
                $('#ch-promo-dil-prmt-status').text('Could not load saved rules — showing defaults.');
            }
        }
        function saveChPromoDilPrmtRules() {
            const rules = readChPromoDilPrmtRulesFromModal();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/dil-prmt',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: chPromoCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    chPromoDilPrmtRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderChPromoDilPrmtModalTable();
                }
                $('#ch-promo-dil-prmt-status').text('Saved.');
                return res;
            });
        }

        function renderChPromoCvrCpnModalTable() {
            const $tb = $('#ch-promo-cvr-cpn-tbody').empty();
            chPromoCvrCpnRules.forEach(function(r, idx) {
                const cpn = isFinite(Number(r.cpn)) ? Number(r.cpn) : 0;
                $tb.append(
                    '<tr data-key="' + String(r.key).replace(/"/g, '&quot;') + '">'
                    + '<td>' + String(r.label || r.key) + '</td>'
                    + '<td class="text-end">'
                    + '<input type="number" class="form-control form-control-sm ch-promo-cvr-cpn-input" '
                    + 'min="0" step="0.1" value="' + cpn + '" data-idx="' + idx + '">'
                    + '</td></tr>'
                );
            });
        }
        function readChPromoCvrCpnRulesFromModal() {
            $('#ch-promo-cvr-cpn-tbody tr').each(function() {
                const key = String($(this).attr('data-key') || '');
                const val = parseFloat($(this).find('.ch-promo-cvr-cpn-input').val());
                const rule = chPromoCvrCpnRules.find(function(r) { return r.key === key; });
                if (!rule) return;
                rule.cpn = (isFinite(val) && val >= 0) ? val : 0;
            });
            return chPromoCvrCpnRules.map(function(r) {
                return { key: r.key, label: r.label, cpn: Number(r.cpn) || 0 };
            });
        }
        async function loadChPromoCvrCpnRules() {
            $('#ch-promo-cvr-cpn-status').text('Loading…');
            try {
                const res = await $.ajax({
                    url: CH_PROMO_RULES_BASE + '/cvr-cpn',
                    method: 'GET',
                    dataType: 'json',
                });
                if (res && Array.isArray(res.rules) && res.rules.length) {
                    chPromoCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                }
                renderChPromoCvrCpnModalTable();
                $('#ch-promo-cvr-cpn-status').text(res && res.is_default
                    ? 'Using first-time defaults (0–10). Apply to save & apply.'
                    : 'Loaded saved CVR vs CPN rules for ' + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '.');
            } catch (e) {
                renderChPromoCvrCpnModalTable();
                $('#ch-promo-cvr-cpn-status').text('Could not load saved rules — showing defaults.');
            }
        }
        function saveChPromoCvrCpnRules() {
            const rules = readChPromoCvrCpnRulesFromModal();
            return $.ajax({
                url: CH_PROMO_RULES_BASE + '/cvr-cpn',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { rules: rules, _token: chPromoCsrf() },
            }).then(function(res) {
                if (res && Array.isArray(res.rules)) {
                    chPromoCvrCpnRules = res.rules.map(function(r) { return Object.assign({}, r); });
                    renderChPromoCvrCpnModalTable();
                }
                $('#ch-promo-cvr-cpn-status').text('Saved.');
                return res;
            });
        }

        async function saveAndApplyChPromoDilPrmt() {
            const selected = collectChPromoSelectedRows();
            let targets = selected;
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
                if (!targets.length) {
                    chPromoToast('error', 'No rows to apply');
                    return;
                }
                if (!confirm('No rows selected — save rules and apply Dil→PRMT % to all ' + targets.length + ' visible row(s)?')) {
                    return;
                }
            }
            const $btn = $('#ch-promo-dil-prmt-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await saveChPromoDilPrmtRules();
                await applyChPromoDilPrmtToTargets(targets, label);
            } catch (xhr) {
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        async function saveAndApplyChPromoCvrCpn() {
            const selected = collectChPromoSelectedRows();
            let targets = selected;
            let label = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                label = 'all visible';
                if (!targets.length) {
                    chPromoToast('error', 'No rows to apply');
                    return;
                }
                if (!confirm('No rows selected — save rules and apply CVR→CPN % to all ' + targets.length + ' visible row(s)?')) {
                    return;
                }
            }
            const $btn = $('#ch-promo-cvr-cpn-apply-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying…');
            try {
                await saveChPromoCvrCpnRules();
                await applyChPromoCvrCpnToTargets(targets, label);
            } catch (xhr) {
                chPromoToast('error', 'Save failed: ' + ((xhr && xhr.responseJSON && xhr.responseJSON.message) || 'error'));
            } finally {
                $btn.prop('disabled', false).html(html);
            }
        }

        async function applyChPromoDilPrmtToTargets(targets, label) {
            readChPromoDilPrmtRulesFromModal();
            if (!targets.length) {
                chPromoToast('error', 'No rows to apply');
                return;
            }
            const ebay1PrmtOnly = CHANNEL_PROMO_CHANNEL === 'ebay1';
            let ok = 0;
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!chPromoIsChildRow(d)) { skipped++; continue; }
                const dil = chPromoDil(d);
                const prmt = chPromoInv(d) === 0 ? 0 : chPromoPrmtForDil(dil);
                // eBay 1: S PRC = Std × (1 − PRMT%/100) only (no prior-SPRICE undo)
                if (ebay1PrmtOnly) {
                    const std = chPromoStdBase(d);
                    if (!(std > 0)) {
                        // Still persist PRMT% even when Std missing
                        item.row.update({ prmt_pct: String(prmt), _prmt_pct_applied: prmt });
                        await saveChannelSpriceAndPromo(item.row, 0, true, { prmt_pct: prmt });
                        if (prmt > 0) ok++; else skipped++;
                        continue;
                    }
                    let newPrice = std;
                    if (prmt > 0 && prmt < 100) {
                        newPrice = chPromoRound2(std * (1 - (prmt / 100)));
                        if (!(newPrice >= 0.01)) newPrice = std;
                    }
                    item.row.update(Object.assign({
                        prmt_pct: String(prmt),
                        _prmt_pct_applied: prmt,
                    }, chPromoSpricePatch(newPrice)));
                    await saveChannelSpriceAndPromo(item.row, newPrice, true, { prmt_pct: prmt });
                    if (prmt > 0) ok++; else skipped++;
                    continue;
                }
                if (!(prmt > 0)) {
                    item.row.update({ prmt_pct: String(prmt), _prmt_pct_applied: 0 });
                    await saveChannelSpriceAndPromo(item.row, chPromoGetSprice(d), true, { prmt_pct: prmt });
                    skipped++;
                    continue;
                }
                const promo = { type: 'percent', value: prmt };
                const base = getChPromoDiscountBase(d, '_prmt_pct_applied');
                const newPrice = applyChPromoToSpriceBase(base, promo);
                if (!(base > 0) || !(newPrice > 0)) {
                    item.row.update({ prmt_pct: String(prmt), _prmt_pct_applied: prmt });
                    await saveChannelSpriceAndPromo(item.row, 0, true, { prmt_pct: prmt });
                    ok++;
                    continue;
                }
                item.row.update(Object.assign({
                    prmt_pct: String(prmt),
                    _prmt_pct_applied: prmt,
                }, chPromoSpricePatch(newPrice)));
                await saveChannelSpriceAndPromo(item.row, newPrice, true, { prmt_pct: prmt });
                ok++;
            }
            chPromoToast(
                (ok ? 'success' : 'error'),
                'Dil vs PRMT (' + label + '): PRMT % → ' + ok + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '')
                    + (ebay1PrmtOnly ? ' (S PRC = Std − PRMT%)' : '') + '.'
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        async function applyChPromoCvrCpnToTargets(targets, label) {
            readChPromoCvrCpnRulesFromModal();
            if (!targets.length) {
                chPromoToast('error', 'No rows to apply');
                return;
            }
            const ebay1 = CHANNEL_PROMO_CHANNEL === 'ebay1';
            const jobs = [];
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!chPromoIsChildRow(d)) { skipped++; continue; }
                const cvr = chPromoCvr(d);
                const cpn = chPromoInv(d) === 0 ? 0 : chPromoCpnForCvr(cvr);
                const sku = chPromoSku(d);
                if (!(cpn > 0)) {
                    item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: 0 });
                    jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: ebay1 });
                    skipped++;
                    continue;
                }
                if (ebay1) {
                    // Coupon is at checkout — Apply only fills/saves CPN% (no S PRC rewrite, no eBay API)
                    item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: cpn });
                    jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: true });
                } else {
                    const promo = { type: 'percent', value: cpn };
                    const base = getChPromoDiscountBase(d, '_cpn_pct_applied');
                    const newPrice = applyChPromoToSpriceBase(base, promo);
                    if (!(base > 0) || !(newPrice > 0)) {
                        item.row.update({ cpn_pct: String(cpn), _cpn_pct_applied: cpn });
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: 0, skipSprice: false });
                    } else {
                        item.row.update(Object.assign({
                            cpn_pct: String(cpn),
                            _cpn_pct_applied: cpn,
                        }, chPromoSpricePatch(newPrice)));
                        jobs.push({ row: item.row, sku: sku, cpn: cpn, price: newPrice, skipSprice: false });
                    }
                }
            }
            if (typeof table !== 'undefined' && table) table.redraw(true);

            await chPromoMapLimit(jobs, 8, async function(job) {
                if (!job.sku) return;
                try {
                    if (job.skipSprice) {
                        await Promise.resolve(saveChannelPromoFields(job.sku, { cpn_pct: job.cpn }));
                        return;
                    }
                    await saveChannelSpriceAndPromo(job.row, job.price, true, { cpn_pct: job.cpn });
                } catch (e) { /* keep going */ }
            });

            const ok = jobs.filter(function(j) { return Number(j.cpn) > 0; }).length;
            chPromoToast(
                (ok ? 'success' : 'error'),
                'CVR vs CPN (' + label + '): CPN % → ' + ok + ' row(s)'
                    + (skipped ? ('; skipped ' + skipped) : '')
                    + (ebay1 ? ' (saved locally — use Push CPN% to create eBay coupons)' : '')
                    + '.'
            );
        }

        async function pushEbay1CodedCouponsForTargets(targets) {
            const jobs = [];
            targets.forEach(function(t) {
                const d = t.row.getData();
                if (!chPromoIsChildRow(d)) return;
                const sku = chPromoSku(d);
                if (!sku) return;
                const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
                jobs.push({ row: t.row, sku: sku, cpn: cpn });
            });
            let ok = 0;
            let fail = 0;
            await chPromoMapLimit(jobs, 3, async function(job) {
                const api = await syncEbay1CodedCoupon(job.sku, job.cpn);
                if (api.ok) {
                    ok++;
                    if (api.coupon_code) {
                        job.row.update({ PEF_COUPON_CODE: api.coupon_code, coupon_code: api.coupon_code });
                    }
                } else {
                    fail++;
                }
            });
            chPromoToast(
                fail && !ok ? 'error' : 'success',
                'eBay public coupon: ' + ok + ' ok' + (fail ? (' / ' + fail + ' fail') : '')
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        function chPromoLmpDiffPct(d) {
            const price = chPromoPrice(d);
            const lmp = Number(d.lmp_price) || 0;
            if (!(price > 0) || !(lmp > 0) || !(price > lmp)) return null;
            return chPromoRound2(((price - lmp) / price) * 100);
        }
        /** DSC % display = PRMT % + CPN % (same row). */
        function chPromoDscSumPct(d) {
            const prmt = Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            return chPromoRound2(Math.min(99.99, prmt + cpn));
        }
        function clearChPromoApprDiscount(row, opts) {
            opts = opts || {};
            const d = row.getData();
            const prev = Number(d._dsc_applied) || 0;
            const patch = {
                appr: false,
                _appr_lmp: null,
                dsc: '',
                _dsc_applied: 0,
            };
            if (prev > 0 && prev < 100 && chPromoGetSprice(d) > 0) {
                Object.assign(patch, chPromoSpricePatch(chPromoRound2(chPromoGetSprice(d) / (1 - (prev / 100)))));
            }
            row.update(patch);
            if (opts.save) {
                const sprice = patch.SPRICE != null ? Number(patch.SPRICE) : chPromoGetSprice(row.getData());
                saveChannelSpriceAndPromo(row, sprice, true, { dsc: 0, appr: false });
            }
            if (opts.redraw && typeof table !== 'undefined' && table) table.redraw(true);
        }
        function applyChPromoApprDiscount(row) {
            const d = row.getData();
            const pct = chPromoLmpDiffPct(d);
            const lmp = Number(d.lmp_price);
            if (!(pct > 0) || !(lmp > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'Appr needs Price > LMP');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            const base = getChPromoDiscountBase(d, '_dsc_applied');
            if (!(base > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'No S PRC/Price to discount');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            if (pct >= 100) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'Appr DSC % out of range');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            const newPrice = applyChPromoToSpriceBase(base, { type: 'percent', value: pct });
            if (!(newPrice > 0)) {
                row.update({ appr: false, _appr_lmp: null });
                chPromoToast('error', 'No S PRC/Price to discount');
                if (typeof table !== 'undefined' && table) table.redraw(true);
                return false;
            }
            row.update(Object.assign({
                appr: true,
                _appr_lmp: chPromoRound2(lmp),
                dsc: String(pct),
                _dsc_applied: pct,
            }, chPromoSpricePatch(newPrice)));
            saveChannelSpriceAndPromo(row, newPrice, true, { dsc: pct, appr: true });
            if (typeof table !== 'undefined' && table) table.redraw(true);
            return true;
        }

        async function applyChPromoFromCell(cell, kind) {
            const fieldMeta = {
                prmt: { field: 'prmt_pct', appliedKey: '_prmt_pct_applied', label: 'PRMT %', allowZero: true },
                cpn: { field: 'cpn_pct', appliedKey: '_cpn_pct_applied', label: 'CPN %', allowZero: true },
                dsc: { field: 'dsc', appliedKey: '_dsc_applied', label: 'DSC %', allowZero: false },
            }[kind];
            if (!fieldMeta) return;
            const editedRow = cell.getRow();
            const raw = cell.getValue();
            const promo = fieldMeta.allowZero
                ? parseChPromoPercentAllowZero(raw)
                : parseChPromoPercentAmount(raw);
            if (!promo) {
                if (String(raw == null ? '' : raw).trim() !== '') {
                    chPromoToast('error', 'Enter ' + fieldMeta.label + ' (e.g. 10)');
                }
                return;
            }
            let targets = [{ row: editedRow, d: editedRow.getData() }];
            const selected = collectChPromoSelectedRows();
            const editedSku = chPromoSku(editedRow.getData());
            if (selected.length > 1 && selected.some(function(t) { return chPromoSku(t.d) === editedSku; })) {
                targets = selected;
            }
            const displayVal = String(promo.value);
            let ok = 0;
            let skipped = 0;
            for (let i = 0; i < targets.length; i++) {
                const item = targets[i];
                const d = item.row.getData();
                if (!chPromoIsChildRow(d)) { skipped++; continue; }
                if (!(promo.value > 0)) {
                    const patch = {};
                    patch[fieldMeta.field] = '0';
                    patch[fieldMeta.appliedKey] = 0;
                    if (kind === 'dsc') {
                        patch.appr = false;
                        patch._appr_lmp = null;
                    }
                    // eBay 1 + PRMT cleared → restore S PRC to Std
                    if (CHANNEL_PROMO_CHANNEL === 'ebay1' && kind === 'prmt') {
                        const std = chPromoStdBase(d);
                        if (std > 0) Object.assign(patch, chPromoSpricePatch(std));
                    }
                    item.row.update(patch);
                    const extra = {};
                    if (kind === 'prmt') extra.prmt_pct = 0;
                    if (kind === 'cpn') extra.cpn_pct = 0;
                    if (kind === 'dsc') { extra.dsc = 0; extra.appr = false; }
                    const savePrice = (CHANNEL_PROMO_CHANNEL === 'ebay1' && kind === 'prmt' && patch.SPRICE != null)
                        ? Number(patch.SPRICE)
                        : chPromoGetSprice(d);
                    await saveChannelSpriceAndPromo(item.row, savePrice, true, extra);
                    skipped++;
                    continue;
                }
                // eBay 1 PRMT%: S PRC = Std × (1 − PRMT%/100) only
                let base;
                let newPrice;
                if (CHANNEL_PROMO_CHANNEL === 'ebay1' && kind === 'prmt') {
                    base = chPromoStdBase(d);
                    newPrice = (base > 0 && promo.value < 100)
                        ? chPromoRound2(base * (1 - (promo.value / 100)))
                        : null;
                } else {
                    base = getChPromoDiscountBase(d, fieldMeta.appliedKey);
                    newPrice = applyChPromoToSpriceBase(base, promo);
                }
                const extra = {};
                if (kind === 'prmt') extra.prmt_pct = promo.value;
                if (kind === 'cpn') extra.cpn_pct = promo.value;
                if (kind === 'dsc') { extra.dsc = promo.value; extra.appr = false; }
                if (!(base > 0) || !(newPrice > 0)) {
                    // Still persist the % on refresh even if S PRC cannot be discounted
                    const patchOnly = {};
                    patchOnly[fieldMeta.field] = displayVal;
                    patchOnly[fieldMeta.appliedKey] = promo.value;
                    if (kind === 'dsc') {
                        patchOnly.appr = false;
                        patchOnly._appr_lmp = null;
                    }
                    item.row.update(patchOnly);
                    await saveChannelSpriceAndPromo(item.row, 0, true, extra);
                    ok++;
                    continue;
                }
                const patch = Object.assign({}, chPromoSpricePatch(newPrice));
                patch[fieldMeta.field] = displayVal;
                patch[fieldMeta.appliedKey] = promo.value;
                if (kind === 'dsc') {
                    patch.appr = false;
                    patch._appr_lmp = null;
                }
                item.row.update(patch);
                await saveChannelSpriceAndPromo(item.row, newPrice, true, extra);
                ok++;
            }
            chPromoToast(
                ok ? 'success' : 'error',
                fieldMeta.label + ' → ' + ok + ' row(s)' + (skipped ? ('; skipped ' + skipped) : '')
            );
            if (typeof table !== 'undefined' && table) table.redraw(true);
        }

        function computeChannelPushPrcPlan(d) {
            const stdRaw = Number(d.STANDARD_PRICE) || Number(d.standard_price) || 0;
            if (!(stdRaw > 0)) return null;
            const std = chPromoRound2(stdRaw);
            const prmt = Math.max(0, Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0);
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            const totalDisc = Math.min(99.99, prmt);
            let sale = null;
            if (totalDisc > 0 && totalDisc < 100) {
                sale = chPromoRound2(std * (1 - (totalDisc / 100)));
                if (!(sale >= 0.01) || sale >= std) sale = null;
            }
            const saleBase = sale != null ? sale : std;
            const max = chPromoRound2(std * 1.10);
            const min = chPromoRound2(Math.max(0.01, saleBase * 0.95));
            const business = chPromoRound2(Math.max(0.01, saleBase * 0.95));
            // Listing push = Std Prc. Sale = PRMT% event. Coupon = CPN% campaign.
            return {
                std: std,
                sale: sale,
                max: max,
                min: min,
                business: business,
                prmt: prmt,
                totalDisc: totalDisc,
                cpn: cpn,
                effective: std,
            };
        }

        function chPromoPlanSaleSprice(plan) {
            if (!plan) return 0;
            if (plan.sale != null && plan.sale > 0) return plan.sale;
            return plan.std > 0 ? plan.std : 0;
        }

        function chPromoPushPrcStepsText(plan) {
            if (!plan || !(plan.std > 0)) return '';
            let text = '1) Push Std Prc $' + plan.std.toFixed(2) + ' as listing price';
            if (chPromoPushPrcHasSaleCoupon()) {
                text += '\n2) Sale event from PRMT% ' + plan.prmt + '%'
                    + (plan.prmt <= 0 ? ' (pause if any)' : '');
                text += '\n3) Coupon campaign from CPN% ' + plan.cpn + '%'
                    + (plan.cpn <= 0 ? ' (remove if any)' : '');
            }
            return text;
        }

        function applyChannelPushPrcToSpriceRow(row, plan, saveRes) {
            const listing = plan.std;
            const updates = {
                PUSH_PRC_VALUE: listing,
                prmt_pct: String(plan.prmt),
                cpn_pct: String(plan.cpn),
                _prmt_pct_applied: plan.prmt,
                _cpn_pct_applied: plan.cpn,
            };
            if (saveRes && saveRes.sgpft_percent !== undefined) updates.SGPFT = saveRes.sgpft_percent;
            if (saveRes && saveRes.spft_percent !== undefined) updates['Spft%'] = saveRes.spft_percent;
            if (saveRes && saveRes.sroi_percent !== undefined) updates.SROI = saveRes.sroi_percent;
            if (saveRes && saveRes.sgroi_percent !== undefined) updates.SGROI = saveRes.sgroi_percent;
            row.update(updates);
            try { row.reformat(); } catch (e) { /* ignore */ }
        }

        function setChPromoPushPrcProgress(opts) {
            opts = opts || {};
            const $box = $('#ch-promo-push-prc-progress');
            if (!$box.length) return;
            const total = Number(opts.total) || 0;
            const done = Number(opts.done) || 0;
            const ok = Number(opts.ok) || 0;
            const fail = Number(opts.fail) || 0;
            const active = !!opts.active;
            const pct = (opts.pct != null)
                ? Math.min(100, Number(opts.pct) || 0)
                : (total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0);
            const finished = !active && total > 0 && (done >= total || pct >= 100);

            if (active || finished) $box.addClass('active');
            else $box.removeClass('active');

            $box.toggleClass('done', finished || (!active && pct >= 100));
            $box.toggleClass('has-fail', fail > 0);

            $('#ch-promo-push-prc-progress-pct').text(pct + '%');
            $('#ch-promo-push-prc-progress-bar').css('width', pct + '%');
            $('#ch-promo-push-prc-cancel-btn').toggle(!!active);

            let msg = opts.msg || '';
            if (!msg && total) {
                msg = done + '/' + total + ' · ' + ok + ' ok'
                    + (fail ? (' · ' + fail + ' failed') : '');
            }
            $('#ch-promo-push-prc-progress-msg').text(msg || 'Ready');

            if (finished) {
                clearTimeout(setChPromoPushPrcProgress._hideTimer);
                setChPromoPushPrcProgress._hideTimer = setTimeout(function() {
                    if (!$box.hasClass('done')) return;
                    $box.removeClass('active done has-fail');
                    $('#ch-promo-push-prc-progress-bar').css('width', '0%');
                    $('#ch-promo-push-prc-progress-pct').text('0%');
                    $('#ch-promo-push-prc-progress-msg').text('Ready');
                    $('#ch-promo-push-prc-cancel-btn').hide();
                }, 8000);
            }
        }

        function pushChannelPriceAjax(sku, price) {
            if (!chPromoCfg.pushPriceUrl) {
                return $.Deferred().resolve({ skipped: true, message: 'Push not configured' }).promise();
            }
            return $.ajax({
                url: chPromoCfg.pushPriceUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { sku: sku, price: price, _token: chPromoCsrf() },
            });
        }

        function planToChannelPushPrcQueueItem(d, plan) {
            return {
                sku: chPromoSku(d),
                std: plan.std,
                sale: plan.sale,
                max: plan.max,
                min: plan.min,
                business: plan.business,
                effective: plan.effective,
                prmt: plan.prmt,
                cpn: plan.cpn,
                cvr_disc: 0,
            };
        }

        function applyChannelPushPrcTaskStatusesToTable(tasks) {
            if (typeof table === 'undefined' || !table || !Array.isArray(tasks)) return;
            const bySku = {};
            tasks.forEach(function(t) {
                if (t && t.sku) bySku[String(t.sku).toUpperCase()] = t;
            });
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (!chPromoIsChildRow(d)) return;
                const sku = chPromoSku(d).toUpperCase();
                const t = bySku[sku];
                if (!t) return;
                const st = String(t.status || '');
                if (st === 'ok') {
                    row.update({
                        PUSH_PRC_STATUS: 'pushed',
                        PUSH_PRC_VALUE: t.std != null ? t.std : (t.effective != null ? t.effective : d.PUSH_PRC_VALUE),
                    });
                } else if (st === 'failed') {
                    row.update({ PUSH_PRC_STATUS: 'error' });
                } else if (st === 'pushing' || st === 'pending' || st === 'queued') {
                    row.update({ PUSH_PRC_STATUS: 'processing' });
                }
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
        }

        function stopChannelPushPrcPoll() {
            if (chPromoPushPrcPollTimer) {
                clearInterval(chPromoPushPrcPollTimer);
                chPromoPushPrcPollTimer = null;
            }
        }

        function pollChannelPushPrcStatus() {
            if (!chPromoPushQueueEnabled) return;
            $.ajax({
                url: CH_PROMO_PUSH_QUEUE_URL + '/status',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                timeout: 20000,
            }).done(function(resp) {
                if (!resp) return;
                const active = !!resp.active;
                const total = Number(resp.total) || 0;
                const done = Number(resp.done_count) || 0;
                const ok = Number(resp.ok_count) || 0;
                const fail = Number(resp.fail_count) || 0;
                const pct = Number(resp.pct) || 0;
                const jobStatus = resp.job && resp.job.status ? String(resp.job.status) : 'idle';

                if (total > 0 || active) {
                    setChPromoPushPrcProgress({
                        active: active,
                        done: done,
                        total: total,
                        ok: ok,
                        fail: fail,
                        pct: pct,
                        msg: resp.message || (resp.job && resp.job.last_message) || '',
                    });
                }
                applyChannelPushPrcTaskStatusesToTable(resp.tasks || []);

                if (!active) {
                    stopChannelPushPrcPoll();
                    const toastKey = jobStatus + '|' + ok + '|' + fail + '|' + total;
                    if (total > 0 && toastKey !== chPromoPushPrcLastToastKey
                        && (jobStatus === 'completed' || jobStatus === 'failed')) {
                        chPromoPushPrcLastToastKey = toastKey;
                        chPromoToast(
                            fail && !ok ? 'error' : 'success',
                            resp.message || ('Push Prc: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : ''))
                        );
                    }
                }
            }).fail(function() {
                // Keep polling — worker may still be fine
            });
        }

        function startChannelPushPrcPoll() {
            stopChannelPushPrcPoll();
            chPromoPushPrcPollTimer = setInterval(pollChannelPushPrcStatus, 3000);
            pollChannelPushPrcStatus();
        }

        /** Queue SKUs for background Push Prc (append-safe while a job is running). */
        function queueChannelPushPrcItems(items) {
            if (!items || !items.length) {
                chPromoToast('error', 'Nothing to queue');
                return Promise.resolve(null);
            }
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            setChPromoPushPrcProgress({
                active: true,
                done: 0,
                total: items.length,
                ok: 0,
                fail: 0,
                msg: 'Queuing ' + items.length + ' SKU(s)…',
            });

            return $.ajax({
                url: CH_PROMO_PUSH_QUEUE_URL,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: {
                    _token: chPromoCsrf(),
                    items: items,
                },
                timeout: 60000,
            }).done(function(resp) {
                chPromoToast(
                    'success',
                    (resp && resp.message)
                        || ('Queued ' + items.length + ' Push Prc job(s) — safe to refresh')
                );
                if (resp) {
                    setChPromoPushPrcProgress({
                        active: !!resp.active,
                        done: Number(resp.done_count) || 0,
                        total: Number(resp.total) || items.length,
                        ok: Number(resp.ok_count) || 0,
                        fail: Number(resp.fail_count) || 0,
                        pct: Number(resp.pct) || 0,
                        msg: resp.message || '',
                    });
                }
                startChannelPushPrcPoll();
            }).fail(function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    || 'Could not queue Push Prc';
                chPromoToast('error', msg);
                setChPromoPushPrcProgress({ active: false, done: 0, total: 0, msg: msg });
            });
        }

        function cancelChannelPushPrcJob() {
            if (!confirm('Cancel remaining Push Prc jobs?')) return;
            $.ajax({
                url: CH_PROMO_PUSH_QUEUE_URL + '/cancel',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': chPromoCsrf(), 'Accept': 'application/json' },
                data: { _token: chPromoCsrf() },
            }).done(function(resp) {
                chPromoToast('success', (resp && resp.message) || 'Push Prc cancelled');
                pollChannelPushPrcStatus();
            }).fail(function(xhr) {
                chPromoToast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Cancel failed');
            });
        }

        /** Fallback for channels without background queue. */
        function runChannelPushPrcSequential(ready) {
            chPromoPushPrcCancel = false;
            clearTimeout(setChPromoPushPrcProgress._hideTimer);
            let ok = 0;
            let fail = 0;
            let i = 0;
            const total = ready.length;
            let warnedNoPush = false;
            setChPromoPushPrcProgress({
                active: true, done: 0, total: total, ok: 0, fail: 0,
                msg: 'Starting…',
            });

            function finish() {
                setChPromoPushPrcProgress({
                    active: false, done: total, total: total, ok: ok, fail: fail, pct: 100,
                    msg: ok + ' ok' + (fail ? (' · ' + fail + ' failed') : '')
                        + (chPromoPushPrcCancel ? ' (cancelled)' : ''),
                });
                if (typeof table !== 'undefined' && table) {
                    try { table.redraw(true); } catch (e) { /* ignore */ }
                }
                let toastMsg = 'Push Prc: ' + ok + ' ok' + (fail ? (', ' + fail + ' failed') : '');
                if (!chPromoCfg.pushPriceUrl) toastMsg += ' — Push not configured';
                chPromoToast(fail && !ok ? 'error' : 'success', toastMsg);
            }

            async function next() {
                if (chPromoPushPrcCancel || i >= ready.length) {
                    finish();
                    return;
                }
                const item = ready[i++];
                const d = item.row.getData();
                const sku = chPromoSku(d);
                const plan = item.plan || computeChannelPushPrcPlan(d);
                const listing = plan && plan.std > 0 ? plan.std : 0;
                setChPromoPushPrcProgress({
                    active: true, done: i - 1, total: total, ok: ok, fail: fail,
                    msg: sku,
                });
                if (!sku || !plan || !(listing > 0)) {
                    fail++;
                    item.row.update({ PUSH_PRC_STATUS: 'error' });
                    next();
                    return;
                }
                item.row.update({ PUSH_PRC_STATUS: 'processing' });
                applyChannelPushPrcToSpriceRow(item.row, plan, null);

                try {
                    await Promise.resolve(saveChannelPromoFields(sku, {
                        prmt_pct: plan.prmt,
                        cpn_pct: plan.cpn,
                        record_push_prc: 1,
                        push_prc_value: listing,
                    }));
                    if (!chPromoCfg.pushPriceUrl) {
                        if (!warnedNoPush) {
                            warnedNoPush = true;
                            chPromoToast('info', 'Push not configured');
                        }
                    } else {
                        await Promise.resolve(pushChannelPriceAjax(sku, listing));
                    }
                    if (chPromoPushPrcHasSaleCoupon()) {
                        const stepErr = [];
                        const saleRes = await syncEbay1Promotion(sku, plan.prmt);
                        if (!saleRes.ok) stepErr.push('Sale: ' + (saleRes.message || 'failed'));
                        const cpnRes = await syncEbay1CodedCoupon(sku, plan.cpn);
                        if (!cpnRes.ok) stepErr.push('Coupon: ' + (cpnRes.message || 'failed'));
                        if (stepErr.length) throw new Error(stepErr.join(' | '));
                    }
                    item.row.update({ PUSH_PRC_STATUS: 'pushed', PUSH_PRC_VALUE: listing });
                    ok++;
                } catch (e) {
                    item.row.update({ PUSH_PRC_STATUS: 'error' });
                    fail++;
                    const msg = (e && e.message)
                        || (e && e.responseJSON && e.responseJSON.message)
                        || 'Push failed';
                    if (total === 1) chPromoToast('error', sku + ': ' + msg);
                }
                setChPromoPushPrcProgress({
                    active: true, done: i, total: total, ok: ok, fail: fail, msg: sku,
                });
                next();
            }
            next();
        }

        function pushChannelStdPrcWithPromos($btn, row) {
            const selected = collectChPromoSelectedRows();
            const clickedKey = chPromoSkuKey(chPromoSku(row.getData()));
            const clickedSelected = selected.some(function(t) {
                return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
            });
            if (selected.length > 1 && clickedSelected) {
                bulkPushChannelPrcSelected();
                return;
            }

            const d = row.getData();
            const sku = chPromoSku(d);
            const plan = computeChannelPushPrcPlan(d);
            if (!sku || !plan || !(plan.std > 0)) {
                chPromoToast('error', 'Std Prc required — Push Prc sends listing = Std, then sale (PRMT%) and coupon (CPN%)');
                return;
            }
            if (!confirm(
                (chPromoPushQueueEnabled
                    ? ('Queue Push Prc for ' + sku + ' in background?\n\n')
                    : ('Push Prc for ' + sku + '?\n\n'))
                + chPromoPushPrcStepsText(plan)
                + '\nMax $' + plan.max.toFixed(2)
                + ' · Min $' + plan.min.toFixed(2)
                + ' · Biz $' + plan.business.toFixed(2)
                + (chPromoPushQueueEnabled
                    ? '\n\nYou can refresh or queue more SKUs while it runs.'
                    : (!chPromoCfg.pushPriceUrl ? '\n\n(Push URL not configured — will save promo only)' : ''))
            )) return;

            if (chPromoPushQueueEnabled) {
                row.update({ PUSH_PRC_STATUS: 'processing' });
                applyChannelPushPrcToSpriceRow(row, plan, null);
                queueChannelPushPrcItems([planToChannelPushPrcQueueItem(d, plan)]);
                return;
            }
            runChannelPushPrcSequential([{ row: row, d: d, plan: plan }]);
        }

        function bulkPushChannelPrcSelected() {
            if (typeof table === 'undefined' || !table) {
                chPromoToast('error', 'Load data first');
                return;
            }
            const targets = collectChPromoSelectedRows();
            if (!targets.length) {
                chPromoToast('error', 'Select one or more SKUs first');
                return;
            }
            const ready = [];
            targets.forEach(function(t) {
                const d = t.row.getData();
                const plan = computeChannelPushPrcPlan(d);
                if (plan && plan.std > 0) {
                    ready.push({ row: t.row, d: d, plan: plan });
                }
            });
            const skipped = targets.length - ready.length;
            if (!ready.length) {
                chPromoToast('error', 'Selected SKUs need Std Prc (Push Prc: Std → sale PRMT% → coupon CPN%)');
                return;
            }
            if (!confirm(
                (chPromoPushQueueEnabled
                    ? ('Queue Push Prc for ' + ready.length + ' selected SKU(s) in background?')
                    : ('Push Prc for ' + ready.length + ' selected SKU(s)?'))
                + (skipped ? ('\n(' + skipped + ' skipped — no Std Prc)') : '')
                + '\n\n1) Push Std Prc as listing price'
                + (chPromoPushPrcHasSaleCoupon()
                    ? '\n2) Sale event from PRMT%\n3) Coupon campaign from CPN%'
                    : '')
                + (chPromoPushQueueEnabled
                    ? '\n\nSafe to refresh — progress continues. You can select more and queue again.'
                    : (!chPromoCfg.pushPriceUrl ? '\n\n(Push URL not configured — will save promo only)' : ''))
            )) return;

            if (chPromoPushQueueEnabled) {
                const items = ready.map(function(r) {
                    r.row.update({ PUSH_PRC_STATUS: 'processing' });
                    applyChannelPushPrcToSpriceRow(r.row, r.plan, null);
                    return planToChannelPushPrcQueueItem(r.d, r.plan);
                });
                if (table) table.redraw(true);
                queueChannelPushPrcItems(items);
                return;
            }

            runChannelPushPrcSequential(ready);
        }

        function clearAndAutopopulateChannelSprice() {
            if (typeof table === 'undefined' || !table) {
                chPromoToast('error', 'Load data first');
                return;
            }
            let targets = collectChPromoSelectedRows();
            let scopeLabel = 'selected';
            if (!targets.length) {
                targets = collectChPromoVisibleRows();
                scopeLabel = 'all visible';
            }
            let skippedInv = 0;
            const ready = targets.filter(function(t) {
                if (chPromoInv(t.d) === 0) {
                    skippedInv++;
                    return false;
                }
                const plan = computeChannelPushPrcPlan(t.d);
                return plan && plan.std > 0;
            });
            if (!ready.length) {
                chPromoToast(
                    'error',
                    skippedInv
                        ? 'No rows to refill (skipped ' + skippedInv + ' with INV = 0)'
                        : 'No rows with Std Prc to refill'
                );
                return;
            }
            if (!confirm(
                'Clear S PRC and refill for ' + ready.length + ' ' + scopeLabel + ' SKU(s)?'
                + (skippedInv ? ('\n(Skip ' + skippedInv + ' with INV = 0)') : '')
                + '\n\nFormula (same as Push Prc, no marketplace push):\n'
                + 'S PRC = Std × (1 − PRMT%/100)\n'
                + 'If no discount → S PRC = Std'
            )) return;

            const $btn = $('#ch-promo-sprice-recalc-btn');
            const html = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');

            ready.forEach(function(t) {
                t.row.update(Object.assign({
                    SGPFT: 0,
                    'Spft%': 0,
                    SROI: 0,
                    SGROI: 0,
                    has_custom_sprice: false,
                    SPRICE_STATUS: null,
                }, chPromoSpricePatch(0)));
            });
            if (table) table.redraw(true);

            let ok = 0;
            let fail = 0;
            let i = 0;
            function next() {
                if (i >= ready.length) {
                    $btn.prop('disabled', false).html(html);
                    if (table) table.redraw(true);
                    chPromoToast(
                        fail && !ok ? 'error' : 'success',
                        'sprice ?: ' + ok + ' filled'
                            + (fail ? (', ' + fail + ' failed') : '')
                            + (skippedInv ? (', ' + skippedInv + ' skipped INV=0') : '')
                    );
                    return;
                }
                const item = ready[i++];
                const plan = computeChannelPushPrcPlan(item.row.getData());
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + i + '/' + ready.length);
                const fill = chPromoPlanSaleSprice(plan);
                if (!plan || !(fill > 0)) {
                    fail++;
                    next();
                    return;
                }
                const sku = chPromoSku(item.d);
                saveChannelSprice(sku, fill, true)
                    .done(function(saveRes) {
                        item.row.update(Object.assign({
                            prmt_pct: String(plan.prmt),
                            cpn_pct: String(plan.cpn),
                            _prmt_pct_applied: plan.prmt,
                            _cpn_pct_applied: plan.cpn,
                        }, chPromoSpricePatch(fill)));
                        if (saveRes && saveRes.sgpft_percent !== undefined) {
                            item.row.update({
                                SGPFT: saveRes.sgpft_percent,
                                'Spft%': saveRes.spft_percent,
                                SROI: saveRes.sroi_percent,
                                SGROI: saveRes.sgroi_percent,
                            });
                        }
                        saveChannelPromoFields(sku, { prmt_pct: plan.prmt }).always(function() {
                            ok++;
                            next();
                        });
                    })
                    .fail(function() {
                        item.row.update(chPromoSpricePatch(fill));
                        fail++;
                        next();
                    });
            }
            next();
        }

        /** Sprc CPN = S PRC × (1 − CPN%/100). */
        function chPromoSprcCpnValue(d) {
            const sprice = chPromoGetSprice(d);
            if (!(sprice > 0)) return null;
            const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
            if (cpn <= 0) return chPromoRound2(sprice);
            if (cpn >= 100) return null;
            const v = chPromoRound2(sprice * (1 - (cpn / 100)));
            return v >= 0.01 ? v : null;
        }

        function channelPromoSprcCpnColumn() {
            return {
                title: 'Sprc CPN',
                field: 'sprc_cpn',
                width: 78,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: false,
                editable: false,
                headerTooltip: 'Sprc CPN = S PRC × (1 − CPN%/100). Read-only.',
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (d.is_parent_summary || !chPromoIsChildRow(d)) return '';
                    const v = chPromoSprcCpnValue(d);
                    if (!(v > 0)) {
                        return '<span class="ch-pef-promo-cell">—</span>';
                    }
                    const cpn = Math.max(0, Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0);
                    const sprice = chPromoGetSprice(d);
                    const tip = 'S PRC $' + sprice.toFixed(2)
                        + (cpn > 0 ? (' − ' + cpn + '% CPN') : ' (no CPN%)')
                        + ' = $' + v.toFixed(2);
                    return '<span class="ch-pef-promo-cell has-val" title="' + chPromoEscAttr(tip) + '">$'
                        + v.toFixed(2) + '</span>';
                },
            };
        }

        function channelPromoPricingColumns() {
            return [
                {
                    title: 'PRMT %',
                    field: 'prmt_pct',
                    width: 72,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: function(cell) {
                        return chPromoIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: '% less on S PRC. Also filled by Dil vs PRMT. Dot = PDT daily history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = chPromoSku(d);
                        const val = cell.getValue();
                        const dot = chPromoHistoryDotHtml(sku, 'prmt', val);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:3px;">'
                            + dot + fmtChPromoCell(val, '%') + '</span>';
                    },
                    cellClick: function(e) {
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.ch-pef-hist-dot')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                    cellEdited: function(cell) {
                        applyChPromoFromCell(cell, 'prmt');
                    },
                },
                {
                    title: 'CPN %',
                    field: 'cpn_pct',
                    width: 70,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: function(cell) {
                        return chPromoIsChildRow(cell.getRow().getData());
                    },
                    editor: 'input',
                    headerTooltip: '% less on S PRC. Also filled by CVR vs CPN. Dot = PDT daily history.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary) return '';
                        const sku = chPromoSku(d);
                        const val = cell.getValue();
                        const dot = chPromoHistoryDotHtml(sku, 'cpn', val);
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:3px;">'
                            + dot + fmtChPromoCell(val, '%') + '</span>';
                    },
                    cellClick: function(e) {
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.ch-pef-hist-dot')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                    cellEdited: function(cell) {
                        applyChPromoFromCell(cell, 'cpn');
                    },
                },
                {
                    title: 'Appr',
                    field: 'appr',
                    width: 48,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    headerTooltip: 'Approve — DSC% = (Price − LMP) / Price × 100, applied off S PRC.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (!chPromoIsChildRow(d)) return '';
                        const checked = d.appr ? 'checked' : '';
                        const sku = chPromoSku(d).replace(/"/g, '&quot;');
                        return '<input type="checkbox" class="ch-pef-appr-cb" data-sku="' + sku + '" ' + checked
                            + ' title="Approve LMP gap → DSC %">';
                    },
                },
                {
                    title: 'DSC %',
                    field: 'dsc',
                    width: 56,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    editable: false,
                    headerTooltip: 'DSC % = PRMT % + CPN % (same row). Read-only.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || !chPromoIsChildRow(d)) return '';
                        const sum = chPromoDscSumPct(d);
                        const tip = 'PRMT ' + (Number(d.prmt_pct != null ? d.prmt_pct : d._prmt_pct_applied) || 0)
                            + '% + CPN ' + (Number(d.cpn_pct != null ? d.cpn_pct : d._cpn_pct_applied) || 0)
                            + '% = ' + sum + '%';
                        if (!(sum > 0)) {
                            return '<span class="ch-pef-promo-cell" title="' + chPromoEscAttr(tip) + '">0</span>';
                        }
                        return '<span class="ch-pef-promo-cell has-val" title="' + chPromoEscAttr(tip) + '">'
                            + sum + '</span>';
                    },
                },
                {
                    title: 'Push Prc',
                    field: 'push_prc',
                    width: 78,
                    hozAlign: 'center',
                    vertAlign: 'middle',
                    headerSort: false,
                    headerTooltip: 'Push Prc — 1) Std listing price  2) Sale event from PRMT%  3) Coupon from CPN%. Select SKUs, then click this header to bulk queue.',
                    titleFormatter: function() {
                        return '<button type="button" class="btn btn-sm p-0 ch-promo-push-prc-header-btn" '
                            + 'title="Bulk Push Prc for all selected SKUs" '
                            + 'style="border:none;background:none;cursor:pointer;color:#000;'
                            + 'font-weight:700;font-size:11px;line-height:1.1;padding:0;">'
                            + 'Push Prc</button>';
                    },
                    headerClick: function(e) {
                        if (e.target.closest('.ch-promo-push-prc-header-btn')) {
                            e.stopPropagation();
                            e.preventDefault();
                            bulkPushChannelPrcSelected();
                            return false;
                        }
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (!chPromoIsChildRow(d)) return '';
                        const sku = chPromoSku(d);
                        const plan = computeChannelPushPrcPlan(d);
                        const status = String(d.PUSH_PRC_STATUS || '');
                        const histVal = d.PUSH_PRC_VALUE != null ? d.PUSH_PRC_VALUE : (plan ? plan.std : null);
                        const dot = chPromoHistoryDotHtml(sku, 'push_prc', histVal);
                        if (!plan || !(plan.std > 0)) {
                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">'
                                + dot + '<span style="color:#adb5bd;" title="Std Prc required">—</span></span>';
                        }
                        let icon = '<i class="fas fa-upload"></i>';
                        let color = '#FF9900';
                        let tip = chPromoPushPrcStepsText(plan)
                            + '\nMax $' + plan.max.toFixed(2)
                            + ' · Min $' + plan.min.toFixed(2)
                            + ' · Biz $' + plan.business.toFixed(2);
                        if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            color = '#28a745';
                            tip = 'Pushed — click to push again. Last listing $'
                                + (Number(d.PUSH_PRC_VALUE) || plan.std).toFixed(2);
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-xmark"></i>';
                            color = '#dc3545';
                            tip = 'Last push failed — click to retry';
                        } else if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            color = '#ffc107';
                            tip = 'Pushing Std → sale → coupon…';
                        }
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">'
                            + dot
                            + '<button type="button" class="btn btn-sm p-0 ch-promo-push-prc-btn" '
                            + 'data-sku="' + chPromoEscAttr(sku) + '" '
                            + 'data-price="' + plan.std.toFixed(2) + '" '
                            + 'title="' + chPromoEscAttr(tip) + '" '
                            + 'style="border:none;background:none;cursor:pointer;color:' + color
                            + ';padding:0;line-height:1;vertical-align:middle;">'
                            + icon + '</button></span>';
                    },
                    cellClick: function(e, cell) {
                        // Same as Amazon: multi-select → bulk; otherwise single SKU
                        const btn = e.target.closest('.ch-promo-push-prc-btn');
                        if (btn) {
                            e.stopPropagation();
                            e.preventDefault();
                            if (btn.disabled) return false;
                            const selected = collectChPromoSelectedRows();
                            const clickedKey = chPromoSkuKey(chPromoSku(cell.getRow().getData()));
                            if (selected.length > 1 && selected.some(function(t) {
                                return chPromoSkuKey(chPromoSku(t.d)) === clickedKey;
                            })) {
                                bulkPushChannelPrcSelected();
                                return false;
                            }
                            pushChannelStdPrcWithPromos($(btn), cell.getRow());
                            return false;
                        }
                        if (e.target.closest('.view-sku-chart') || e.target.closest('.ch-pef-hist-dot')) {
                            e.stopPropagation();
                            return false;
                        }
                    },
                },
            ];
        }

        function initChannelPromoPricingUi() {
            if (typeof loadChPromoDilPrmtRules === 'function') loadChPromoDilPrmtRules();
            if (typeof loadChPromoCvrCpnRules === 'function') {
                Promise.resolve(loadChPromoCvrCpnRules()).catch(function() { /* defaults */ });
            }

            $('#ch-promo-push-prc-cancel-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                if (chPromoPushQueueEnabled) {
                    cancelChannelPushPrcJob();
                    return;
                }
                if (!confirm('Cancel remaining Push Prc?')) return;
                chPromoPushPrcCancel = true;
                chPromoToast('success', 'Push Prc cancel requested');
            });

            // Resume background Push Prc progress after refresh (eBay queue)
            if (chPromoPushQueueEnabled) {
                pollChannelPushPrcStatus();
                $.ajax({
                    url: CH_PROMO_PUSH_QUEUE_URL + '/status',
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    timeout: 15000,
                }).done(function(resp) {
                    if (resp && resp.active) startChannelPushPrcPoll();
                });
            }

            $('#ch-promo-dil-vs-prmt-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('chPromoDilVsPrmtModal');
                if (!modalEl) return;
                renderChPromoDilPrmtModalTable();
                loadChPromoDilPrmtRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            $('#ch-promo-push-prmt-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                if (typeof table === 'undefined' || !table) {
                    chPromoToast('error', 'Load data first');
                    return;
                }
                const selected = collectChPromoSelectedRows();
                let targets = selected;
                let scopeLabel = 'selected';
                if (!targets.length) {
                    targets = collectChPromoVisibleRows();
                    scopeLabel = 'all visible';
                }
                if (!targets.length) {
                    chPromoToast('error', 'No rows to push');
                    return;
                }
                if (!confirm(
                    'Apply Dil→PRMT and save for ' + targets.length + ' ' + scopeLabel + ' SKU(s) on '
                    + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '?'
                )) return;

                const $btn = $('#ch-promo-prmt-menu-btn');
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');
                Promise.resolve(applyChPromoDilPrmtToTargets(targets, scopeLabel)).finally(function() {
                    $btn.prop('disabled', false).html(html);
                });
            });

            $('#ch-promo-dil-prmt-apply-btn').off('click.chpromo').on('click.chpromo', saveAndApplyChPromoDilPrmt);

            $('#ch-promo-cvr-vs-cpn-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('chPromoCvrVsCpnModal');
                if (!modalEl) return;
                renderChPromoCvrCpnModalTable();
                loadChPromoCvrCpnRules();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            $('#ch-promo-push-cpn-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                if (typeof table === 'undefined' || !table) {
                    chPromoToast('error', 'Load data first');
                    return;
                }
                const selected = collectChPromoSelectedRows();
                let targets = selected;
                let scopeLabel = 'selected';
                if (!targets.length) {
                    targets = collectChPromoVisibleRows();
                    scopeLabel = 'all visible';
                }
                if (!targets.length) {
                    chPromoToast('error', 'No rows to push');
                    return;
                }
                if (!confirm(
                    'Apply CVR→CPN and save for ' + targets.length + ' ' + scopeLabel + ' SKU(s) on '
                    + (chPromoCfg.label || CHANNEL_PROMO_CHANNEL) + '?'
                    + (CHANNEL_PROMO_CHANNEL === 'ebay1'
                        ? '\n\neBay1: pushes a PUBLIC coded coupon (code SAVE{nn}PCT), starts now, 30 days.\n'
                            + 'Same CPN% reuses the existing campaign and adds the SKU.'
                        : '')
                )) return;

                const $btn = $('#ch-promo-cpn-menu-btn');
                const html = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>…');
                Promise.resolve(applyChPromoCvrCpnToTargets(targets, scopeLabel))
                    .then(function() {
                        if (CHANNEL_PROMO_CHANNEL === 'ebay1') {
                            return pushEbay1CodedCouponsForTargets(targets);
                        }
                    })
                    .finally(function() {
                        $btn.prop('disabled', false).html(html);
                    });
            });

            $('#ch-promo-cvr-cpn-apply-btn').off('click.chpromo').on('click.chpromo', saveAndApplyChPromoCvrCpn);

            $(document).off('change.chpromo', '.ch-pef-appr-cb').on('change.chpromo', '.ch-pef-appr-cb', function() {
                if (typeof table === 'undefined' || !table) return;
                const sku = String($(this).attr('data-sku') || '');
                if (!sku) return;
                const row = table.getRows().find(function(r) {
                    return chPromoSku(r.getData()) === sku;
                });
                if (!row) return;
                if ($(this).is(':checked')) {
                    applyChPromoApprDiscount(row);
                } else {
                    clearChPromoApprDiscount(row, { save: true, redraw: true });
                }
            });

            $(document).off('click.chpromo', '.ch-promo-push-prc-btn').on('click.chpromo', '.ch-promo-push-prc-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof table === 'undefined' || !table) return;
                const $btn = $(this);
                if ($btn.prop('disabled')) return;
                const sku = String($btn.attr('data-sku') || '');
                const row = table.getRows().find(function(r) {
                    return chPromoSkuKey(chPromoSku(r.getData())) === chPromoSkuKey(sku);
                });
                if (!row) {
                    chPromoToast('error', 'Row not found');
                    return;
                }
                // Multi-select → bulk (same as Amazon Push Prc column)
                pushChannelStdPrcWithPromos($btn, row);
            });

            $(document).off('click.chpromo', '.ch-promo-push-prc-header-btn').on('click.chpromo', '.ch-promo-push-prc-header-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                bulkPushChannelPrcSelected();
            });

            $('#ch-promo-sprice-recalc-btn').off('click.chpromo').on('click.chpromo', function(e) {
                e.preventDefault();
                clearAndAutopopulateChannelSprice();
            });
        }

        // Export + auto-init
        window.channelPromoPricingColumns = channelPromoPricingColumns;
        window.computeChannelPushPrcPlan = computeChannelPushPrcPlan;
        window.initChannelPromoPricingUi = initChannelPromoPricingUi;
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() { initChannelPromoPricingUi(); });
        } else {
            initChannelPromoPricingUi();
        }
@endif
