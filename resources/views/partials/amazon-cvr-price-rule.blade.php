{{-- CVR Price Rule: a saved price change % for SKUs whose CVR is down, within ±20% of, or up vs LT O CVR. Parts: buttons, modals, script. --}}
@php $amazonCvrRulePart = $amazonCvrRulePart ?? 'all'; @endphp

@if($amazonCvrRulePart === 'buttons' || $amazonCvrRulePart === 'all')
                    <button type="button" class="btn btn-sm btn-info" id="amz-cvr-rule-btn" style="font-weight:600;color:#000;"
                        title="Price change % for SKUs whose CVR L30 is down, within target of, or up vs LT O CVR (targets are set in the modal).">
                        <i class="fas fa-chart-line"></i> CVR Price Rule
                        <span class="badge bg-dark ms-1" id="amz-cvr-rule-state" style="display:none;">ON</span>
                    </button>
@endif

@if($amazonCvrRulePart === 'modals' || $amazonCvrRulePart === 'all')
    <style>
        /* Dashboard-standard history dot (same as the summary badges): green = up, red = down, gray = same / no prior day. */
        #amzCvrRuleModal .summary-trend-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 0.3rem;
            flex-shrink: 0;
            cursor: pointer;
            vertical-align: 0.08em;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
            position: relative;
            z-index: 2;
        }
        #amzCvrRuleModal .summary-trend-dot:hover { transform: scale(1.25); box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.25); }
        #amzCvrRuleModal .summary-trend-dot.up { background: #22c55e; }
        #amzCvrRuleModal .summary-trend-dot.down { background: #ef4444; }
        #amzCvrRuleModal .summary-trend-dot.flat,
        #amzCvrRuleModal .summary-trend-dot.none { background: #9ca3af; }
    </style>
    <div class="modal fade" id="amzCvrRuleModal" tabindex="-1" aria-labelledby="amzCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amzCvrRuleModalLabel"><i class="fas fa-chart-line me-1"></i> CVR Price Rule</h5>
                        <div class="text-muted" style="font-size:12px;">CVR L30 is compared with LT O CVR (the CVR Δ column). Set the targets, then the price change % for each case: CVR Down always lowers the price and CVR Up always raises it, whatever sign you type (type 5 or -5, the price still goes down for CVR Down). For CVR within target a positive % raises the price and a negative % lowers it. A CVR L30 between 7% and 13% is always within target (no up / down arrow).</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-3" id="amz-cvr-onoff-box">
                        <div>
                            <div class="fw-semibold">Rule <span id="amz-cvr-onoff-txt" class="badge bg-secondary">OFF</span></div>
                            <div class="text-muted" style="font-size:12px;">ON: the SKU's price change % (by its CVR case) is taken off its S PRC, so S PRC, the profit columns, the blue triangle and Push Prc all use the new price. OFF: S PRC is left as the other rules make it. Click Save to apply.</div>
                        </div>
                        <div class="form-check form-switch mb-0 ms-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="amz-cvr-rule-enabled" style="width:2.6em;height:1.3em;cursor:pointer;" aria-label="CVR Price Rule on or off">
                        </div>
                    </div>
                    <div class="row g-2 mb-3" id="amz-cvr-band-box">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1" for="amz-cvr-band-down"><i class="fas fa-arrow-down" style="color:#a00211;font-size:12px;"></i> CVR Down when below LT O CVR by</label>
                            <div class="input-group input-group-sm"><input type="number" min="0" max="99" step="0.1" class="form-control" id="amz-cvr-band-down" value="20"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1" for="amz-cvr-band-up"><i class="fas fa-arrow-up" style="color:#28a745;font-size:12px;"></i> CVR Up when above LT O CVR by</label>
                            <div class="input-group input-group-sm"><input type="number" min="0" max="1000" step="0.1" class="form-control" id="amz-cvr-band-up" value="20"><span class="input-group-text">%</span></div>
                        </div>
                    </div>
                    <table class="table table-sm align-middle mb-2" id="amz-cvr-rule-table">
                        <thead>
                            <tr>
                                <th style="width:32%;">CVR vs LT O CVR</th>
                                <th style="width:12%;" class="text-end">SKUs</th>
                                <th style="width:26%;">Price change %</th>
                                <th style="width:15%;" class="text-end" title="SKUs whose new price is set to the Lower cap price">Low cap</th>
                                <th style="width:15%;" class="text-end" title="SKUs whose new price is set to the Upper cap price">High cap</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-key="down">
                                <td><i class="fas fa-arrow-down" style="color:#a00211;font-size:12px;"></i> <strong>CVR Down</strong><div class="text-muted" style="font-size:11px;"><span class="amz-cvr-down-more">more than <span class="amz-cvr-down-lbl">20</span>% </span>below LT O CVR, or CVR L30 = 0% (sold 0, with or without views). Not when CVR L30 is 7% to 13%.</div></td>
                                <td class="text-end text-muted"><span class="summary-trend-dot none" data-metric="cvr_down_count" title="Rolling history"></span><span class="amz-cvr-rule-count">—</span></td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped-low">—</td>
                                <td class="text-end text-muted amz-cvr-rule-capped-high">—</td>
                            </tr>
                            <tr data-key="same">
                                <td><strong>--</strong> <strong>CVR within target</strong><div class="text-muted" style="font-size:11px;">between <span class="amz-cvr-down-lbl">20</span>% below and <span class="amz-cvr-up-lbl">20</span>% above LT O CVR</div></td>
                                <td class="text-end text-muted"><span class="summary-trend-dot none" data-metric="cvr_same_count" title="Rolling history"></span><span class="amz-cvr-rule-count">—</span></td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped-low">—</td>
                                <td class="text-end text-muted amz-cvr-rule-capped-high">—</td>
                            </tr>
                            <tr data-key="up">
                                <td><i class="fas fa-arrow-up" style="color:#28a745;font-size:12px;"></i> <strong>CVR Up</strong><div class="text-muted" style="font-size:11px;"><span class="amz-cvr-up-more">more than <span class="amz-cvr-up-lbl">20</span>% </span>above LT O CVR. Not when CVR L30 is 7% to 13%.</div></td>
                                <td class="text-end text-muted"><span class="summary-trend-dot none" data-metric="cvr_up_count" title="Rolling history"></span><span class="amz-cvr-rule-count">—</span></td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped-low">—</td>
                                <td class="text-end text-muted amz-cvr-rule-capped-high">—</td>
                            </tr>
                            <tr data-key="pink">
                                <td><i class="fas fa-arrow-up" style="color:#e83e8c;font-size:12px;"></i> <strong style="color:#e83e8c;">CVR pink arrow</strong>
                                    <div class="text-muted" style="font-size:11px;">CVR L30 above
                                        <span class="d-inline-flex align-items-center"><input type="number" min="0" max="1000" step="0.01" class="form-control form-control-sm d-inline-block px-1 py-0 amz-cvr-pink-above" style="width:64px;height:20px;font-size:11px;" value="12.99"></span>%
                                        (the pink ↑ in CVR Δ). Takes the place of the three cases above and always raises the price. Leave the % blank to turn it off.</div></td>
                                <td class="text-end text-muted"><span class="amz-cvr-rule-count">—</span></td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="off"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped-low">—</td>
                                <td class="text-end text-muted amz-cvr-rule-capped-high">—</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="border rounded p-2 mb-2" id="amz-cvr-cap-box">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="amz-cvr-cap-enabled">
                            <label class="form-check-label fw-semibold" for="amz-cvr-cap-enabled">Lower cap</label>
                        </div>
                        <div class="text-muted mb-2" style="font-size:12px;">If the new price would make either NPFT% or NROI% fall below its minimum, the price is set to the Lower cap price instead. Whichever limit is hit first sets the lowest allowed price.</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="amz-cvr-cap-npft">Min NPFT%</label>
                                <div class="input-group input-group-sm"><input type="number" max="79" step="0.1" class="form-control" id="amz-cvr-cap-npft" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="amz-cvr-cap-nroi">Min NROI%</label>
                                <div class="input-group input-group-sm"><input type="number" step="0.1" class="form-control" id="amz-cvr-cap-nroi" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded p-2 mb-2" id="amz-cvr-ucap-box">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="amz-cvr-ucap-enabled">
                            <label class="form-check-label fw-semibold" for="amz-cvr-ucap-enabled">Upper cap</label>
                        </div>
                        <div class="text-muted mb-2" style="font-size:12px;">If the new price would make either NPFT% or NROI% rise above its maximum, the price is set to the Upper cap price instead. Whichever limit is hit first sets the highest allowed price.</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="amz-cvr-ucap-npft">Max NPFT%</label>
                                <div class="input-group input-group-sm"><input type="number" max="79" step="0.1" class="form-control" id="amz-cvr-ucap-npft" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="amz-cvr-ucap-nroi">Max NROI%</label>
                                <div class="input-group input-group-sm"><input type="number" step="0.1" class="form-control" id="amz-cvr-ucap-nroi" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted" style="font-size:12px;" id="amz-cvr-rule-note"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amz-cvr-rule-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amazonCvrRulePart === 'script' || $amazonCvrRulePart === 'all')
        // ---- CVR Price Rule: price change % by CVR vs LT O CVR ----
        let amzCvrRule = @json(\App\Http\Controllers\MarketPlace\OverallAmazonController::amazonCvrPriceRuleConfig());

        /** CVR targets: how far below / above LT O CVR counts as down / up, as fractions (default 20% each). */
        function amzCvrRuleBands(cfg) {
            cfg = cfg || amzCvrRule || {};
            const d = parseFloat(cfg.down_band);
            const u = parseFloat(cfg.up_band);
            return {
                down: (isFinite(d) && d >= 0 && d < 100 ? d : 20) / 100,
                up: (isFinite(u) && u >= 0 ? u : 20) / 100
            };
        }
        /** Plain % numbers for labels, e.g. 20 or 12.5. */
        function amzCvrRuleBandPcts(cfg) {
            const b = amzCvrRuleBands(cfg);
            return { down: Math.round(b.down * 10000) / 100, up: Math.round(b.up * 10000) / 100 };
        }
        /** CVR (L30) vs LT O CVR for a row: 1 up, -1 down, 0 within the targets. A SKU with no lifetime CVR (no sessions) starts from LT O CVR = 0%. */
        function amzCvrRuleTrendValue(row, cfg) {
            if (!row) return null;
            const sess30 = parseFloat(row['Sess30']) || 0;
            const sess = sess30 + (parseFloat(row['sessions_l60']) || 0);
            const lto = sess <= 0 ? 0 : (((parseFloat(row['A_L30']) || 0) + (parseFloat(row['units_ordered_l60']) || 0)) / sess) * 100;
            const cvr = sess30 === 0 ? 0 : ((parseFloat(row['A_L30']) || 0) / sess30) * 100;
            const b = amzCvrRuleBands(cfg);
            // Sold 0 (CVR L30 = 0%): always CVR Down, whatever LT O CVR is.
            if ((parseFloat(row['A_L30']) || 0) <= 0) return -1;
            // CVR L30 between 7% and 13%: no up / down arrow, always within target.
            if (cvr >= 7 && cvr <= 13) return 0;
            if (cvr > lto * (1 + b.up)) return 1;
            if (cvr < lto * (1 - b.down)) return -1;
            return 0;
        }
        /** CVR L30 % above which the pink up arrow shows (CVR Δ column) and the pink slab applies. Default 12.99. */
        function amzCvrRulePinkAbove(cfg) {
            cfg = cfg || amzCvrRule || {};
            const v = parseFloat(cfg.pink_above);
            return (isFinite(v) && v >= 0) ? v : 12.99;
        }
        /** The pink slab is on once it has a price change % (blank = off). */
        function amzCvrRulePinkOn(cfg) {
            cfg = cfg || amzCvrRule || {};
            return cfg.pink !== null && cfg.pink !== undefined && cfg.pink !== '' && isFinite(parseFloat(cfg.pink));
        }
        window.amzCvrRulePinkAbove = amzCvrRulePinkAbove;
        /** Same as amzCvrRuleTrendValue but as 'pink' / 'up' / 'down' / 'same'. Pink (CVR L30 above the pink target) comes first. */
        function amzCvrRuleCaseFor(row, cfg) {
            const t = amzCvrRuleTrendValue(row, cfg);
            if (t === null) return null;
            if (amzCvrRulePinkOn(cfg)) {
                const sess30 = parseFloat(row['Sess30']) || 0;
                const cvr = sess30 === 0 ? 0 : ((parseFloat(row['A_L30']) || 0) / sess30) * 100;
                if (cvr > amzCvrRulePinkAbove(cfg)) return 'pink';
            }
            return t > 0 ? 'up' : (t < 0 ? 'down' : 'same');
        }
        /** Keep the band wording in the modal and the CVR Δ filter in step with the targets. */
        function amzCvrRuleRefreshLabels(cfg) {
            const p = amzCvrRuleBandPcts(cfg);
            $('.amz-cvr-down-lbl').text(p.down);
            $('.amz-cvr-up-lbl').text(p.up);
            $('.amz-cvr-down-more').toggle(p.down > 0);
            $('.amz-cvr-up-more').toggle(p.up > 0);
            const $f = $('#cvr-delta-filter');
            $f.find('option[value="up"]').text('\u2191 Up (> +' + p.up + '%)');
            $f.find('option[value="same"]').text('-- Within target');
            $f.find('option[value="down"]').text('\u2193 Down (< -' + p.down + '%)');
            $f.attr('title', 'CVR L30 vs LT O CVR (down below -' + p.down + '%, up above +' + p.up + '%)');
        }
        /** Ads % used for the cap maths: the real channel Ads%. */
        function amzCvrRuleChannelAds() {
            if (typeof amzAmazonAdsPct === 'function') return Number(amzAmazonAdsPct()) || 0;
            try { return parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0; } catch (e) { return 0; }
        }
        /**
         * Lowest price the Lower cap allows: the higher of the price where NPFT% = Min NPFT% and the price where NROI% = Min NROI%.
         * Same formulas as the NPFT% / NROI% columns, with the channel Ads %:
         *   NPFT% = (p×0.80 − ship − LP) / p × 100 − Ads%        ->  p = (ship + LP) / (0.80 − (Min NPFT + Ads%) / 100)
         *   NROI% = (p×margin − ship − LP − p×Ads%) / LP × 100   ->  p = (LP × (1 + Min NROI / 100) + ship) / (margin − Ads% / 100)
         * Infinity when a minimum cannot be reached. null when no limit applies to this row.
         */
        function amzCvrRuleFloorPrice(row, cfg) {
            if (!cfg || !cfg.lower_cap) return null;
            const lp = parseFloat(row && row.LP_productmaster);
            const ship = parseFloat(row && row.Ship_productmaster) || 0;
            const cost = (isFinite(lp) && lp > 0) ? lp : 0;
            const marginRaw = parseFloat(row && row.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.80;
            const chAds = amzCvrRuleChannelAds();
            const solve = function(solveAt) {
                return solveAt(chAds);
            };
            let floor = null;
            const n = parseFloat(cfg.min_npft);
            if (isFinite(n) && cfg.min_npft !== null && cfg.min_npft !== '') {
                const f = solve(function(ads) {
                    const k = 0.80 - (n + ads) / 100;
                    return k > 0 ? (ship + cost) / k : Infinity;
                });
                floor = floor === null ? f : Math.max(floor, f);
            }
            const r = parseFloat(cfg.min_nroi);
            if (isFinite(r) && cfg.min_nroi !== null && cfg.min_nroi !== '' && cost > 0) {
                const f = solve(function(ads) {
                    const k = margin - ads / 100;
                    return k > 0 ? (cost * (1 + r / 100) + ship) / k : Infinity;
                });
                floor = floor === null ? f : Math.max(floor, f);
            }
            return floor;
        }
        /**
         * Highest price the Upper cap allows: the lower of the price where NPFT% = Max NPFT% and the price where NROI% = Max NROI%
         * (same formulas as the Lower cap). null when no limit applies to this row (or a maximum can never be reached).
         */
        function amzCvrRuleCeilingPrice(row, cfg) {
            if (!cfg || !cfg.upper_cap) return null;
            const lp = parseFloat(row && row.LP_productmaster);
            const ship = parseFloat(row && row.Ship_productmaster) || 0;
            const cost = (isFinite(lp) && lp > 0) ? lp : 0;
            const marginRaw = parseFloat(row && row.percentage);
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.80;
            const ads = amzCvrRuleChannelAds();
            let ceil = null;
            const n = parseFloat(cfg.max_npft);
            if (isFinite(n) && cfg.max_npft !== null && cfg.max_npft !== '') {
                const k = 0.80 - (n + ads) / 100;
                if (k > 0) { const f = (ship + cost) / k; ceil = ceil === null ? f : Math.min(ceil, f); }
            }
            const r = parseFloat(cfg.max_nroi);
            if (isFinite(r) && cfg.max_nroi !== null && cfg.max_nroi !== '' && cost > 0) {
                const k = margin - ads / 100;
                if (k > 0) { const f = (cost * (1 + r / 100) + ship) / k; ceil = ceil === null ? f : Math.min(ceil, f); }
            }
            return ceil;
        }
        /**
         * Price after the CVR Price Rule % for a row, with the Lower cap and the Upper cap applied. The new price never goes below the cap price:
         * if it would, it is set to the cap price (this can raise a SKU whose current price is already under the cap).
         * Returns { price, capped } or null when the row has no price or no LT O CVR.
         */
        /** The % with its direction fixed by the case: Down always cuts, Up always raises, within target keeps the typed sign. */
        function amzCvrRuleSignedPct(c, raw) {
            const p = parseFloat(raw);
            if (!isFinite(p)) return NaN;
            if (c === 'down') return -Math.abs(p);
            if (c === 'up' || c === 'pink') return Math.abs(p);
            return p;
        }
        function amzCvrRulePriceFor(row, cfg, basePrice) {
            cfg = cfg || amzCvrRule || {};
            const c = amzCvrRuleCaseFor(row, cfg);
            // The % is taken off the S PRC the other rules produced (stable across pushes), not off the live Price.
            const price = (basePrice > 0) ? Number(basePrice) : parseFloat(row && row.price);
            if (!c || !(price > 0)) return null;
            // "--" (within target) with no % still gets the Lower / Upper cap: it is a 0% change that the caps can move.
            const blankPct = (cfg[c] === null || cfg[c] === undefined || cfg[c] === '');
            const pct = (c === 'same' && blankPct) ? 0 : amzCvrRuleSignedPct(c, cfg[c]);
            if (!isFinite(pct) || (blankPct && c !== 'same')) return { price: price, capped: false };
            let next = price * (1 + pct / 100);
            let capped = false;
            let capKind = '';
            // Upper cap: a new price above the cap price is brought down to it (CVR Up / pink never lower the price, see below).
            const ceil = amzCvrRuleCeilingPrice(row, cfg);
            if (ceil !== null && next > ceil) {
                next = ceil;
                capped = true;
                capKind = 'high';
            }
            // Lower cap: a new price below the cap price is brought up to the cap price (even when the current price is already
            // below it). When a minimum can never be reached (no finite cap price) the price is left where it is.
            const floor = amzCvrRuleFloorPrice(row, cfg);
            if (floor !== null && next < floor) {
                next = isFinite(floor) ? floor : price;
                capped = true;
                capKind = 'low';
            }
            // CVR Up / pink must never LOWER the price: not below where it started (the Upper cap can pull a high price down).
            if (pct > 0) next = Math.max(next, price);
            // CVR Down must never RAISE the price: not above where it started (the Lower cap lifted a low price up to its floor),
            // and not above the live price. Server twin does the same.
            if (pct < 0) {
                next = Math.min(next, price);
                const live = (parseFloat(row && row.price) || 0);
                if (live > 0) next = Math.min(next, live);
            }
            return { price: Math.round(next * 100) / 100, capped: capped, capKind: capKind };
        }
        /** Rule ON/OFF paint: the switch label in the modal and the badge on the toolbar button. */
        function amzCvrRulePaintState() {
            const on = !!(amzCvrRule && amzCvrRule.enabled);
            $('#amz-cvr-rule-state').toggle(on);
        }
        function amzCvrRulePaintOnOffText() {
            const on = $('#amz-cvr-rule-enabled').is(':checked');
            $('#amz-cvr-onoff-txt').text(on ? 'ON' : 'OFF').toggleClass('bg-success', on).toggleClass('bg-secondary', !on);
        }
        /**
         * The S PRC the other rules produced for a row (Sprc Dil / Std prc vs dil, Std Prc maximum done; the CVR rule adds no LMP cap of its own): the CVR
         * Price Rule's base. 0 when the row has no rule price (rules OFF, cleared, clearance, parent, no INV).
         */
        function amzCvrRuleBaseFor(d) {
            if (!d || d.is_parent_summary) return 0;
            if (typeof amzPefIsChildRow === 'function' && !amzPefIsChildRow(d)) return 0;
            if (typeof amzIsClearanceSku === 'function' && amzIsClearanceSku(d)) return 0;
            // Sprc Dil and Std prc vs dil both OFF: the CVR Price Rule prices on its own and compares with the live Price: the % comes
            // off it. It acts once per price: when the saved S PRC already equals the live Price the rule holds (no compounding).
            // Server twin: AmazonSprcDilAutoPushService::computeCvrOnlyTarget().
            if (typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() === 'none') {
                if (!amzCvrRule || !amzCvrRule.enabled) return 0;
                const live = Math.round((parseFloat(d.price) || 0) * 100) / 100;
                if (!(live > 0)) return 0;
                const saved = parseFloat(d.SPRICE) || 0;
                if (saved > 0 && Math.abs(saved - live) < 0.005) return 0;
                return live;
            }
            if (typeof amzLiveRuleSprice !== 'function') return 0;
            const live = Number(amzLiveRuleSprice(d)) || 0;
            if (!(live > 0)) return 0;
            // No LMP cap here: the CVR Price Rule takes the S PRC the other rules produced as it is.
            return Math.round(live * 100) / 100;
        }
        window.amzCvrRuleBaseFor = amzCvrRuleBaseFor;
        /**
         * CVR Price Rule on one row's S PRC: { price, capped, base, c, pct } or null when the rule is OFF or does not change the SKU.
         * Server twin: AmazonCvrPriceRule::apply(). Both take the % off the same base, so a push never compounds it.
         */
        function amzCvrRuleInfo(d) {
            if (!amzCvrRule || !amzCvrRule.enabled || !d || d.is_parent_summary) return null;
            const c = amzCvrRuleCaseFor(d, amzCvrRule);
            if (!c) return null;
            const blankPct = (amzCvrRule[c] === null || amzCvrRule[c] === undefined || amzCvrRule[c] === '');
            const pct = (c === 'same' && blankPct) ? 0 : amzCvrRuleSignedPct(c, amzCvrRule[c]);
            if (!isFinite(pct) || (blankPct && c !== 'same')) return null;
            // A 0% change only matters for "--" (within target) with a cap on: the caps can still move the price.
            if (pct === 0 && !(c === 'same' && (amzCvrRule.lower_cap || amzCvrRule.upper_cap))) return null;
            const base = amzCvrRuleBaseFor(d);
            if (!(base > 0)) return null;
            const res = amzCvrRulePriceFor(d, amzCvrRule, base);
            if (!res || !(res.price >= 0.01)) return null;
            let outPrice = res.price;
            // No Std Prc maximum: Up is not held at Std Prc. Down never goes above live (amzCvrRulePriceFor).
            outPrice = Math.round(outPrice * 100) / 100;
            if (Math.abs(outPrice - base) < 0.005) return null;
            return { price: outPrice, capped: res.capped, base: base, c: c, pct: pct };
        }
        window.amzCvrRuleInfo = amzCvrRuleInfo;
        window.amzCvrRuleIsOn = function() { return !!(amzCvrRule && amzCvrRule.enabled); };
        /** Small arrow shown next to the S PRC number when the CVR Price Rule moved it (hover = the working). One price only. */
        window.amzCvrRuleMarkHtml = function(info) {
            if (!info) return '';
            const word = info.c === 'down' ? 'CVR Down' : (info.c === 'up' ? 'CVR Up' : (info.c === 'pink' ? 'CVR pink arrow' : 'CVR within target'));
            const tip = 'CVR Price Rule (' + word + '): $' + info.base.toFixed(2) + ' ' + (info.pct > 0 ? '+' : '') + info.pct + '% = $' + info.price.toFixed(2)
                + (info.capped ? ' (set to the Lower / Upper cap price)' : '');
            const up = info.price > info.base;
            return '<i class="fas fa-' + (up ? 'arrow-up' : 'arrow-down') + '" title="' + String(tip).replace(/"/g, '&quot;')
                + '" style="font-size:10px;margin-left:2px;color:' + (up ? '#198754' : '#a00211') + ';"></i>'
                + (info.capped ? '<i class="fas fa-lock" title="Lower / Upper cap price" style="font-size:9px;margin-left:1px;color:#6c757d;"></i>' : '');
        };
        /** The price after the CVR rule when it applies, else the given price. */
        window.amzCvrRuleApplyTo = function(d, price) {
            let info = null;
            try { info = amzCvrRuleInfo(d); } catch (e) { info = null; }
            return info ? info.price : price;
        };
        /** Kept so the column definition does not change. */
        function amzCvrRuleSpriceFormatter(inner) {
            return inner;
        }
        function amzCvrRuleReadDraft() {
            const d = { pink_above: $('#amz-cvr-rule-table .amz-cvr-pink-above').val(), enabled: $('#amz-cvr-rule-enabled').is(':checked') ? 1 : 0, lower_cap: $('#amz-cvr-cap-enabled').is(':checked') ? 1 : 0, min_npft: $('#amz-cvr-cap-npft').val(), min_nroi: $('#amz-cvr-cap-nroi').val(), upper_cap: $('#amz-cvr-ucap-enabled').is(':checked') ? 1 : 0, max_npft: $('#amz-cvr-ucap-npft').val(), max_nroi: $('#amz-cvr-ucap-nroi').val(), down_band: $('#amz-cvr-band-down').val(), up_band: $('#amz-cvr-band-up').val() };
            $('#amz-cvr-rule-table tbody tr').each(function() {
                d[$(this).data('key')] = $(this).find('.amz-cvr-rule-pct').val();
            });
            return d;
        }
        /** Modal wording only (the page filter keeps the saved targets until Save). */
        function amzCvrRuleRefreshLabelsModalOnly(cfg) {
            const p = amzCvrRuleBandPcts(cfg);
            $('#amzCvrRuleModal .amz-cvr-down-lbl').text(p.down);
            $('#amzCvrRuleModal .amz-cvr-up-lbl').text(p.up);
            $('#amzCvrRuleModal .amz-cvr-down-more').toggle(p.down > 0);
            $('#amzCvrRuleModal .amz-cvr-up-more').toggle(p.up > 0);
        }
        /** SKU count (and Lower-capped count) per CVR case for a rule config, over the child SKUs loaded in the table. */
        function amzCvrRuleCounts(cfg) {
            cfg = cfg || amzCvrRule || {};
            const rows = (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
            const counts = { down: 0, same: 0, up: 0, pink: 0 };
            const capped = { down: 0, same: 0, up: 0, pink: 0 };
            const cappedLow = { down: 0, same: 0, up: 0, pink: 0 };
            const cappedHigh = { down: 0, same: 0, up: 0, pink: 0 };
            rows.forEach(function(r) {
                if (!r || r.is_parent_summary) return;
                const sku = String(r['(Child) sku'] || '').trim().toUpperCase();
                if (sku.indexOf('PARENT ') === 0 || sku === 'PARENT') return;
                // SKUs with 0 INV are not counted (they get no rule price).
                if (!(parseFloat(r['INV']) > 0)) return;
                const c = amzCvrRuleCaseFor(r, cfg);
                if (!c) return;
                counts[c]++;
                const res = amzCvrRulePriceFor(r, cfg, (typeof amzCvrRuleBaseFor === 'function') ? amzCvrRuleBaseFor(r) : 0);
                if (res && res.capped) { capped[c]++; if (res.capKind === 'high') cappedHigh[c]++; else cappedLow[c]++; }
            });
            return { counts: counts, capped: capped, cappedLow: cappedLow, cappedHigh: cappedHigh };
        }
        const AMZ_CVR_RULE_COUNT_METRICS = { down: 'cvr_down_count', same: 'cvr_same_count', up: 'cvr_up_count' };
        /** History dot per SKUs count: green up / red down / gray same vs the prior day's snapshot (CVR Down is lower-is-better). */
        function amzCvrRulePaintDots(counts) {
            $('#amz-cvr-rule-table tbody tr').each(function() {
                const k = $(this).data('key');
                const metric = AMZ_CVR_RULE_COUNT_METRICS[k];
                const $dot = $(this).find('.summary-trend-dot');
                if (!metric || !$dot.length) return;
                const live = counts[k];
                const prev = (typeof amzBadgePrevDay !== 'undefined' && amzBadgePrevDay) ? amzBadgePrevDay[metric] : null;
                if (!isFinite(live) || prev == null || !isFinite(prev)) {
                    $dot.attr('class', 'summary-trend-dot none').attr('title', 'Click for rolling history (no prior day yet)');
                    return;
                }
                const cls = amzTrendClass(live, prev, !!amzBadgeInvertMetrics[metric]);
                const word = cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : 'Same');
                $dot.attr('class', 'summary-trend-dot ' + cls)
                    .attr('title', word + ' vs prior day (' + Math.round(prev).toLocaleString('en-US') + ' → ' + Math.round(live).toLocaleString('en-US') + '). Click for rolling history.');
            });
        }
        function amzCvrRulePaintCounts() {
            const cfg = amzCvrRuleReadDraft();
            amzCvrRuleRefreshLabelsModalOnly(cfg);
            const res = amzCvrRuleCounts(cfg);
            const counts = res.counts, cappedLow = res.cappedLow, cappedHigh = res.cappedHigh;
            $('#amz-cvr-rule-table tbody tr').each(function() {
                const k = $(this).data('key');
                const live = counts[k] || 0;
                $(this).find('.amz-cvr-rule-count').text(live.toLocaleString());
                $(this).find('.amz-cvr-rule-capped-low').text(cfg.lower_cap ? (cappedLow[k] || 0).toLocaleString() : '—');
                $(this).find('.amz-cvr-rule-capped-high').text(cfg.upper_cap ? (cappedHigh[k] || 0).toLocaleString() : '—');
            });
            amzCvrRulePaintDots(counts);
            $('#amz-cvr-cap-npft, #amz-cvr-cap-nroi').prop('disabled', !cfg.lower_cap);
            $('#amz-cvr-ucap-npft, #amz-cvr-ucap-nroi').prop('disabled', !cfg.upper_cap);
        }
        /** Re-save today's counts after the targets change (the daily page save only runs once per load). */
        function amzCvrRulePostCounts() {
            const counts = amzCvrRuleCounts(amzCvrRule).counts;
            $.ajax({
                url: '/amazon-cvr-rule-counts-save',
                method: 'POST',
                data: { cvr_down_count: counts.down, cvr_same_count: counts.same, cvr_up_count: counts.up },
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' }
            });
        }
        function amzCvrRulePaintModal() {
            const cfg = amzCvrRule || {};
            $('#amz-cvr-rule-table tbody tr').each(function() {
                const key = $(this).data('key');
                const v = cfg[key];
                $(this).find('.amz-cvr-rule-pct').val(v === null || v === undefined || v === '' ? '' : v);
            });
            $('#amz-cvr-rule-table .amz-cvr-pink-above').val(amzCvrRulePinkAbove(cfg));
            $('#amz-cvr-rule-enabled').prop('checked', !!cfg.enabled);
            amzCvrRulePaintOnOffText();
            $('#amz-cvr-band-down').val(amzCvrRuleBandPcts(cfg).down);
            $('#amz-cvr-band-up').val(amzCvrRuleBandPcts(cfg).up);
            $('#amz-cvr-cap-enabled').prop('checked', !!cfg.lower_cap);
            $('#amz-cvr-cap-npft').val(cfg.min_npft === null || cfg.min_npft === undefined ? '' : cfg.min_npft);
            $('#amz-cvr-cap-nroi').val(cfg.min_nroi === null || cfg.min_nroi === undefined ? '' : cfg.min_nroi);
            $('#amz-cvr-ucap-enabled').prop('checked', !!cfg.upper_cap);
            $('#amz-cvr-ucap-npft').val(cfg.max_npft === null || cfg.max_npft === undefined ? '' : cfg.max_npft);
            $('#amz-cvr-ucap-nroi').val(cfg.max_nroi === null || cfg.max_nroi === undefined ? '' : cfg.max_nroi);
            amzCvrRulePaintCounts();
            $('#amz-cvr-rule-note').text('SKU counts are the child SKUs with INV > 0 loaded in the table. A SKU with 0 INV is not counted. A SKU with no lifetime CVR (no sessions) starts from LT O CVR = 0%. Low cap / High cap = SKUs whose new price is set to the Lower / Upper cap price (based on each SKU\'s S PRC before this rule).');
        }

        $(document).ready(function() {
            amzCvrRuleRefreshLabels(amzCvrRule);
            amzCvrRulePaintState();
            $('#amz-cvr-rule-btn').off('click.amzcvr').on('click.amzcvr', function(e) {
                e.preventDefault();
                amzCvrRulePaintModal();
                // Prior-day snapshot for the history dots (cached after the first load); repaint once it arrives.
                if (typeof loadAmzBadgePrevDay === 'function') loadAmzBadgePrevDay(amzCvrRulePaintCounts);
                const el = document.getElementById('amzCvrRuleModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $(document).off('input.amzcvrcount change.amzcvrcount').on('input.amzcvrcount change.amzcvrcount', '#amz-cvr-rule-table input, #amz-cvr-cap-box input, #amz-cvr-ucap-box input, #amz-cvr-band-box input', amzCvrRulePaintCounts);
            $(document).off('change.amzcvronoff').on('change.amzcvronoff', '#amz-cvr-rule-enabled', amzCvrRulePaintOnOffText);
            $('#amz-cvr-rule-save').off('click.amzcvr').on('click.amzcvr', function() {
                const $btn = $(this).prop('disabled', true);
                const payload = amzCvrRuleReadDraft();
                $.ajax({
                    url: @json(route('amazon.cvr-price-rule.save')),
                    method: 'POST',
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' }
                }).done(function(res) {
                    if (!res || !res.success) { amzPefToast('error', 'Could not save CVR Price Rule'); return; }
                    amzCvrRule = { enabled: !!res.enabled, down: res.down, same: res.same, up: res.up, pink: res.pink, pink_above: res.pink_above, lower_cap: !!res.lower_cap, min_npft: res.min_npft, min_nroi: res.min_nroi, upper_cap: !!res.upper_cap, max_npft: res.max_npft, max_nroi: res.max_nroi, down_band: res.down_band, up_band: res.up_band };
                    amzCvrRuleRefreshLabels(amzCvrRule);
                    amzCvrRulePaintState();
                    amzCvrRulePostCounts();
                    if (typeof amzTableRedrawPreserveScroll === 'function') amzTableRedrawPreserveScroll(true);
                    else if (typeof table !== 'undefined' && table) table.redraw(true);
                    if ($('#cvr-delta-filter').val() !== 'all') $('#cvr-delta-filter').trigger('change');
                    // The CVR price is the S PRC now: re-save every SKU's S PRC (page twin of AmazonSprcDilAutoPushService).
                    if (typeof amzScheduleRuleSpriceSync === 'function') amzScheduleRuleSpriceSync({ force: true, delay: 600 });
                    // Same as switching Sprc Dil / Std prc vs dil: the blue SKUs (S PRC != Price) are queued for push right away,
                    // not only on the next page load. Needs "Push on reload" ON.
                    setTimeout(function() {
                        window._amzReloadPushQueued = false;
                        window._amzLeftoverPasses = 0;
                        if (typeof window.amzTryQueuePushOnReload === 'function') window.amzTryQueuePushOnReload();
                    }, 1500);
                    amzPefToast('success', amzCvrRule.enabled ? 'CVR Price Rule saved and ON. S PRC is being re-saved with the new prices.' : 'CVR Price Rule saved (OFF). S PRC is being re-saved without it.');
                    const el = document.getElementById('amzCvrRuleModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                }).fail(function() {
                    amzPefToast('error', 'Could not save CVR Price Rule');
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
