{{-- eBay 1 / 2 / 3 + Shopify B2C CVR Price Rule (pass ebayCvrRuleChannel): a saved price change % for SKUs whose CVR 30 is down, within target of, or up vs LT O CVR. Same rule as Amazon. Parts: buttons, modals, script. --}}
@php $ebayCvrRulePart = $ebayCvrRulePart ?? 'all'; $ebayCvrRuleChannel = $ebayCvrRuleChannel ?? 'ebay1'; @endphp

@if($ebayCvrRulePart === 'buttons' || $ebayCvrRulePart === 'all')
                    <button type="button" class="btn btn-sm btn-info" id="ebay-cvr-rule-btn" style="font-weight:600;color:#000;"
                        title="Price change % for SKUs whose CVR L30 is down, within target of, or up vs LT O CVR (targets are set in the modal).">
                        <i class="fas fa-chart-line"></i> CVR Price Rule
                        <span class="badge bg-dark ms-1" id="ebay-cvr-rule-state" style="display:none;">ON</span>
                    </button>
@endif

@if($ebayCvrRulePart === 'modals' || $ebayCvrRulePart === 'all')
    <div class="modal fade" id="ebayCvrRuleModal" tabindex="-1" aria-labelledby="ebayCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="ebayCvrRuleModalLabel"><i class="fas fa-chart-line me-1"></i> CVR Price Rule</h5>
                        <div class="text-muted" style="font-size:12px;">CVR L30 is compared with LT O CVR (the CVR Δ column). Set the targets, then the price change % for each case: CVR Down always lowers the price and CVR Up always raises it, whatever sign you type (type 5 or -5, the price still goes down for CVR Down). For CVR within target a positive % raises the price and a negative % lowers it.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-3" id="ebay-cvr-onoff-box">
                        <div>
                            <div class="fw-semibold">Rule <span id="ebay-cvr-onoff-txt" class="badge bg-secondary">OFF</span></div>
                            <div class="text-muted" style="font-size:12px;">ON: the SKU's price change % (by its CVR case) is taken off its S PRC, so S PRC, S GROI / SNROI and Push Prc all use the new price. OFF: S PRC is left as the other rules make it. Click Save to apply.</div>
                        </div>
                        <div class="form-check form-switch mb-0 ms-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="ebay-cvr-rule-enabled" style="width:2.6em;height:1.3em;cursor:pointer;" aria-label="CVR Price Rule on or off">
                        </div>
                    </div>
                    <div class="row g-2 mb-3" id="ebay-cvr-band-box">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1" for="ebay-cvr-band-down"><i class="fas fa-arrow-down" style="color:#a00211;font-size:12px;"></i> CVR Down when below LT O CVR by</label>
                            <div class="input-group input-group-sm"><input type="number" min="0" max="99" step="0.1" class="form-control" id="ebay-cvr-band-down" value="20"><span class="input-group-text">%</span></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1" for="ebay-cvr-band-up"><i class="fas fa-arrow-up" style="color:#28a745;font-size:12px;"></i> CVR Up when above LT O CVR by</label>
                            <div class="input-group input-group-sm"><input type="number" min="0" max="1000" step="0.1" class="form-control" id="ebay-cvr-band-up" value="20"><span class="input-group-text">%</span></div>
                        </div>
                    </div>
                    <table class="table table-sm align-middle mb-2" id="ebay-cvr-rule-table">
                        <thead>
                            <tr>
                                <th style="width:40%;">CVR vs LT O CVR</th>
                                <th style="width:14%;" class="text-end">SKUs</th>
                                <th style="width:28%;">Price change %</th>
                                <th style="width:18%;" class="text-end" title="SKUs whose new price is set to the Lower cap price">Capped</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-key="down">
                                <td><i class="fas fa-arrow-down" style="color:#a00211;font-size:12px;"></i> <strong>CVR Down</strong><div class="text-muted" style="font-size:11px;">more than <span class="ebay-cvr-down-lbl">20</span>% below LT O CVR</div></td>
                                <td class="text-end text-muted ebay-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control ebay-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted ebay-cvr-rule-capped">—</td>
                            </tr>
                            <tr data-key="same">
                                <td><strong>--</strong> <strong>CVR within target</strong><div class="text-muted" style="font-size:11px;">between <span class="ebay-cvr-down-lbl">20</span>% below and <span class="ebay-cvr-up-lbl">20</span>% above LT O CVR</div></td>
                                <td class="text-end text-muted ebay-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control ebay-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted ebay-cvr-rule-capped">—</td>
                            </tr>
                            <tr data-key="up">
                                <td><i class="fas fa-arrow-up" style="color:#28a745;font-size:12px;"></i> <strong>CVR Up</strong><div class="text-muted" style="font-size:11px;">more than <span class="ebay-cvr-up-lbl">20</span>% above LT O CVR</div></td>
                                <td class="text-end text-muted ebay-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control ebay-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted ebay-cvr-rule-capped">—</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="border rounded p-2 mb-2" id="ebay-cvr-cap-box">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="ebay-cvr-cap-enabled">
                            <label class="form-check-label fw-semibold" for="ebay-cvr-cap-enabled">Lower cap</label>
                        </div>
                        <div class="text-muted mb-2" style="font-size:12px;">If the new price would make either NPFT% or NROI% fall below its minimum, the price is set to the Lower cap price instead. Whichever limit is hit first sets the lowest allowed price.</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="ebay-cvr-cap-npft">Min NPFT%</label>
                                <div class="input-group input-group-sm"><input type="number" max="79" step="0.1" class="form-control" id="ebay-cvr-cap-npft" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="ebay-cvr-cap-nroi">Min NROI%</label>
                                <div class="input-group input-group-sm"><input type="number" step="0.1" class="form-control" id="ebay-cvr-cap-nroi" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted" style="font-size:12px;" id="ebay-cvr-rule-note"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="ebay-cvr-rule-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($ebayCvrRulePart === 'script' || $ebayCvrRulePart === 'all')
        // ---- CVR Price Rule: price change % by CVR vs LT O CVR ----
        let ebayCvrRule = @json(\App\Support\EbayCvrPriceRule::config($ebayCvrRuleChannel));

        /** CVR targets: how far below / above LT O CVR counts as down / up, as fractions (default 20% each). */
        function ebayCvrRuleBands(cfg) {
            cfg = cfg || ebayCvrRule || {};
            const d = parseFloat(cfg.down_band);
            const u = parseFloat(cfg.up_band);
            return {
                down: (isFinite(d) && d >= 0 && d < 100 ? d : 20) / 100,
                up: (isFinite(u) && u >= 0 ? u : 20) / 100
            };
        }
        /** Plain % numbers for labels, e.g. 20 or 12.5. */
        function ebayCvrRuleBandPcts(cfg) {
            const b = ebayCvrRuleBands(cfg);
            return { down: Math.round(b.down * 10000) / 100, up: Math.round(b.up * 10000) / 100 };
        }
        /** CVR (L30) vs LT O CVR for a row: 1 up, -1 down, 0 within the targets, null when LT O CVR is unknown. */
        function ebayCvrRuleTrendValue(row, cfg) {
            if (!row || row.is_parent_summary) return null;
            if (row.LT_O_CVR === null || row.LT_O_CVR === undefined || row.LT_O_CVR === '') return null;
            const lto = parseFloat(row.LT_O_CVR);
            if (!isFinite(lto)) return null;
            const cvr = parseFloat(row.SCVR) || 0;
            const b = ebayCvrRuleBands(cfg);
            if (cvr > lto * (1 + b.up)) return 1;
            if (cvr < lto * (1 - b.down)) return -1;
            return 0;
        }
        /** Same as ebayCvrRuleTrendValue but as 'up' / 'down' / 'same'. */
        function ebayCvrRuleCaseFor(row, cfg) {
            const t = ebayCvrRuleTrendValue(row, cfg);
            return t === null ? null : (t > 0 ? 'up' : (t < 0 ? 'down' : 'same'));
        }
        /** Keep the band wording in the modal and the modal wording in step with the targets. */
        function ebayCvrRuleRefreshLabels(cfg) {
            const p = ebayCvrRuleBandPcts(cfg);
            $('.ebay-cvr-down-lbl').text(p.down);
            $('.ebay-cvr-up-lbl').text(p.up);
        }
        /** Ads % used for the cap maths: the eBay channel Ads%. */
        function ebayCvrRuleChannelAds() {
            try { if (typeof shopifyChannelAdsPct === 'function') return parseFloat(shopifyChannelAdsPct()) || 0; return parseFloat(typeof EBAY3_CHANNEL_ADS_PCT !== 'undefined' ? EBAY3_CHANNEL_ADS_PCT : (typeof EBAY2_CHANNEL_ADS_PCT !== 'undefined' ? EBAY2_CHANNEL_ADS_PCT : EBAY_CHANNEL_ADS_PCT)) || 0; } catch (e) { return 0; }
        }
        /**
         * Lowest price the Lower cap allows: the higher of the price where NPFT% = Min NPFT% and the price where NROI% = Min NROI%.
         * Same formulas as the NPFT% / NROI% columns, with the Ads % that applies at that price:
         *   NPFT% = (p×0.80 − ship − LP) / p × 100 − Ads%        ->  p = (ship + LP) / (0.80 − (Min NPFT + Ads%) / 100)
         *   NROI% = (p×margin − ship − LP − p×Ads%) / LP × 100   ->  p = (LP × (1 + Min NROI / 100) + ship) / (margin − Ads% / 100)
         * Infinity when a minimum cannot be reached. null when no limit applies to this row.
         */
        function ebayCvrRuleFloorPrice(row, cfg) {
            if (!cfg || !cfg.lower_cap) return null;
            const lp = parseFloat(row && row.LP_productmaster);
            const ship = parseFloat(row && row.Ship_productmaster) || 0;
            const cost = (isFinite(lp) && lp > 0) ? lp : 0;
            const marginRaw = parseFloat(row && row.percentage);
            let takeHome = 0.8;
            try { takeHome = parseFloat(typeof EBAY3_TAKEHOME !== 'undefined' ? EBAY3_TAKEHOME : (typeof EBAY2_TAKEHOME !== 'undefined' ? EBAY2_TAKEHOME : EBAY_TAKEHOME)) || 0.8; } catch (e) { takeHome = 0.8; }
            const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : takeHome;
            const ads = ebayCvrRuleChannelAds();
            let floor = null;
            const n = parseFloat(cfg.min_npft);
            if (isFinite(n) && cfg.min_npft !== null && cfg.min_npft !== '') {
                // NPFT% = GPFT% - Ads%, GPFT% = (p x margin - ship - LP) / p x 100  ->  p = (ship + LP) / (margin - (Min NPFT + Ads%) / 100)
                const k = margin - (n + ads) / 100;
                const f = k > 0 ? (ship + cost) / k : Infinity;
                floor = floor === null ? f : Math.max(floor, f);
            }
            const r = parseFloat(cfg.min_nroi);
            if (isFinite(r) && cfg.min_nroi !== null && cfg.min_nroi !== '' && cost > 0) {
                // NROI% = (p x margin - ship - LP - p x Ads%) / LP x 100  ->  p = (LP x (1 + Min NROI / 100) + ship) / (margin - Ads% / 100)
                const k = margin - ads / 100;
                const f = k > 0 ? (cost * (1 + r / 100) + ship) / k : Infinity;
                floor = floor === null ? f : Math.max(floor, f);
            }
            return floor;
        }
        /**
         * Price after the CVR Price Rule % for a row, with the Lower cap applied. The new price never goes below the cap price:
         * if it would, it is set to the cap price (this can raise a SKU whose current price is already under the cap).
         * Returns { price, capped } or null when the row has no price or no LT O CVR.
         */
        /** The % with its direction fixed by the case: Down always cuts, Up always raises, within target keeps the typed sign. */
        function ebayCvrRuleSignedPct(c, raw) {
            const p = parseFloat(raw);
            if (!isFinite(p)) return NaN;
            if (c === 'down') return -Math.abs(p);
            if (c === 'up') return Math.abs(p);
            return p;
        }
        function ebayCvrRulePriceFor(row, cfg, basePrice) {
            cfg = cfg || ebayCvrRule || {};
            const c = ebayCvrRuleCaseFor(row, cfg);
            // The % is taken off the S PRC the other rules produced (stable across pushes), not off the live Price.
            const price = (basePrice > 0) ? Number(basePrice) : parseFloat(row && row['eBay Price']);
            if (!c || !(price > 0)) return null;
            const pct = ebayCvrRuleSignedPct(c, cfg[c]);
            if (!isFinite(pct) || cfg[c] === null || cfg[c] === '') return { price: price, capped: false };
            let next = price * (1 + pct / 100);
            let capped = false;
            // Lower cap: a new price below the cap price is brought up to the cap price (even when the current price is already
            // below it). When a minimum can never be reached (no finite cap price) the price is left where it is.
            const floor = ebayCvrRuleFloorPrice(row, cfg);
            if (floor !== null && next < floor) {
                next = isFinite(floor) ? floor : price;
                capped = true;
            }
            // CVR Down must never RAISE the price: not above where it started (the Lower cap lifted a low price up to its floor),
            // and not above the live price. Server twin does the same.
            if (pct < 0) {
                next = Math.min(next, price);
                const live = (parseFloat(row && row['eBay Price']) || parseFloat(row && row.Price) || 0);
                if (live > 0) next = Math.min(next, live);
            }
            return { price: Math.round(next * 100) / 100, capped: capped };
        }
        /** Rule ON/OFF paint: the switch label in the modal and the badge on the toolbar button. */
        function ebayCvrRulePaintState() {
            const on = !!(ebayCvrRule && ebayCvrRule.enabled);
            $('#ebay-cvr-rule-state').toggle(on);
        }
        function ebayCvrRulePaintOnOffText() {
            const on = $('#ebay-cvr-rule-enabled').is(':checked');
            $('#ebay-cvr-onoff-txt').text(on ? 'ON' : 'OFF').toggleClass('bg-success', on).toggleClass('bg-secondary', !on);
        }
        /**
         * CVR Price Rule on one row's S PRC. `base` = the S PRC the other rules produced (LMP cap + Std Prc maximum done).
         * Returns { price, capped, base, c, pct } or null when the rule is OFF or does not change this SKU.
         * Server twin: EbayCvrPriceRule::apply(). Both read the same base, so a push never compounds the %.
         */
        function ebayCvrRuleApply(row, base) {
            // A PARENT row never gets the % itself: its S PRC is the average of its SKUs, which already carry it.
            if (!ebayCvrRule || !ebayCvrRule.enabled || !row || row.is_parent_summary || row.is_parent_row || String(row['(Child) sku'] || '').toUpperCase().indexOf('PARENT') !== -1) return null;
            base = Math.round((Number(base) || 0) * 100) / 100;
            if (!(base > 0)) return null;
            const c = ebayCvrRuleCaseFor(row, ebayCvrRule);
            if (!c) return null;
            const pct = ebayCvrRuleSignedPct(c, ebayCvrRule[c]);
            if (!isFinite(pct) || pct === 0 || ebayCvrRule[c] === null || ebayCvrRule[c] === '') return null;
            const res = ebayCvrRulePriceFor(row, ebayCvrRule, base);
            if (!res || !(res.price >= 0.01) || Math.abs(res.price - base) < 0.005) return null;
            return { price: res.price, capped: res.capped, base: base, c: c, pct: pct };
        }
        window.ebayCvrRuleIsOn = function() { return !!(ebayCvrRule && ebayCvrRule.enabled); };
        /** The price after the rule (the base when the rule does not apply). */
        window.ebayCvrRuleApplyToPrice = function(row, base) {
            let info = null;
            try { info = ebayCvrRuleApply(row, base); } catch (e) { info = null; }
            return info ? info.price : base;
        };
        /**
         * CVR Price Rule on its own (Sprc Dil and Std prc vs dil both OFF): price = live Price + the rule's % (Up stops at the Std Prc).
         * Returns the new price, or 0 when the mode is not on or the rule does not change this SKU (then nothing is applied or pushed).
         * Server twin: EbayRuleSprice::price() with rule 'none'. capFn = the page's LMP cap (ebayCapSpriceToLmp / ebay2... / ebay3...).
         */
        window.ebayCvrOnlyMode = function() {
            return typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() === 'none' && window.ebayCvrRuleIsOn();
        };
        window.ebayCvrOnlyPrice = function(row, capFn) {
            if (!window.ebayCvrOnlyMode()) return 0;
            if (!row || row.is_parent_summary || row.is_parent_row || row.is_parent) return 0;
            const sku = String(row['(Child) sku'] || row.sku || '').toUpperCase();
            if (sku.indexOf('PARENT') !== -1) return 0;
            if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(row)) return 0;
            // Compares with the live Price: the % comes off it. The rule acts once per price: when the saved S PRC already equals the
            // live Price it holds (no compounding). Up stops at Std Prc (page's LMP cap), Down never goes above live.
            const live = Math.round((parseFloat(row['eBay Price']) || parseFloat(row.Price) || 0) * 100) / 100;
            if (!(live > 0)) return 0;
            const savedRaw = row.SPRICE_SERVER !== undefined ? row.SPRICE_SERVER : row.SPRICE;
            const saved = Number(savedRaw) || 0;
            if (saved > 0 && Math.abs(saved - live) < 0.005) return 0;
            let std = 0;
            try { std = (window.SpriceLmpCap && typeof SpriceLmpCap.stdOf === 'function') ? Number(SpriceLmpCap.stdOf(row)) : (Number(row.STANDARD_PRICE) || 0); } catch (e) { std = 0; }
            let stdCapped = 0;
            if (std > 0) {
                stdCapped = std;
                try { if (typeof capFn === 'function') { const c = Number(capFn(row, std)); if (isFinite(c) && c > 0) stdCapped = c; } } catch (e) { stdCapped = std; }
            }
            let out = Number(window.ebayCvrRuleApplyToPrice(row, live));
            if (!isFinite(out) || !(out > 0)) return 0;
            if (out > live) {
                if (!(stdCapped > 0)) return 0;
                out = Math.max(live, Math.min(out, stdCapped));
            }
            out = Math.round(out * 100) / 100;
            return Math.abs(out - live) >= 0.005 ? out : 0;
        };
        /** Small arrow shown next to the S PRC number when the CVR Price Rule moved it (hover = the working). One price only. */
        window.ebayCvrRuleMarkHtml = function(row, base) {
            let info = null;
            try { info = ebayCvrRuleApply(row, base); } catch (e) { info = null; }
            if (!info) return '';
            const word = info.c === 'down' ? 'CVR Down' : (info.c === 'up' ? 'CVR Up' : 'CVR within target');
            const tip = 'CVR Price Rule (' + word + '): $' + info.base.toFixed(2) + ' ' + (info.pct > 0 ? '+' : '') + info.pct + '% = $' + info.price.toFixed(2)
                + (info.capped ? ' (set to the Lower cap price)' : '');
            const up = info.price > info.base;
            return '<i class="fas fa-' + (up ? 'arrow-up' : 'arrow-down') + '" title="' + String(tip).replace(/"/g, '&quot;')
                + '" style="font-size:10px;margin-left:2px;color:' + (up ? '#198754' : '#a00211') + ';"></i>'
                + (info.capped ? '<i class="fas fa-lock" title="Lower cap price" style="font-size:9px;margin-left:1px;color:#6c757d;"></i>' : '');
        };
        function ebayCvrRuleReadDraft() {
            const d = { enabled: $('#ebay-cvr-rule-enabled').is(':checked') ? 1 : 0, lower_cap: $('#ebay-cvr-cap-enabled').is(':checked') ? 1 : 0, min_npft: $('#ebay-cvr-cap-npft').val(), min_nroi: $('#ebay-cvr-cap-nroi').val(), down_band: $('#ebay-cvr-band-down').val(), up_band: $('#ebay-cvr-band-up').val() };
            $('#ebay-cvr-rule-table tbody tr').each(function() {
                d[$(this).data('key')] = $(this).find('.ebay-cvr-rule-pct').val();
            });
            return d;
        }
        /** Modal wording only (the page filter keeps the saved targets until Save). */
        function ebayCvrRuleRefreshLabelsModalOnly(cfg) {
            const p = ebayCvrRuleBandPcts(cfg);
            $('#ebayCvrRuleModal .ebay-cvr-down-lbl').text(p.down);
            $('#ebayCvrRuleModal .ebay-cvr-up-lbl').text(p.up);
        }
        function ebayCvrRulePaintCounts() {
            const cfg = ebayCvrRuleReadDraft();
            ebayCvrRuleRefreshLabelsModalOnly(cfg);
            const top = (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
            // eBay 3 is a tree (PARENT rows hold their SKUs in _children): walk it so the child SKUs are counted.
            const rows = [];
            (function walk(list) {
                (list || []).forEach(function(r) {
                    if (!r) return;
                    rows.push(r);
                    if (Array.isArray(r._children)) walk(r._children);
                });
            })(top);
            const counts = { down: 0, same: 0, up: 0 };
            const capped = { down: 0, same: 0, up: 0 };
            rows.forEach(function(r) {
                if (!r || r.is_parent_summary || r.is_parent_row) return;
                const sku = String(r['(Child) sku'] || '').trim().toUpperCase();
                if (sku.indexOf('PARENT ') === 0 || sku === 'PARENT') return;
                const c = ebayCvrRuleCaseFor(r, cfg);
                if (!c) return;
                counts[c]++;
                const baseNow = (typeof window.ebayDisplayedSpriceBase === 'function') ? (Number(window.ebayDisplayedSpriceBase(r)) || 0) : 0;
                const res = ebayCvrRulePriceFor(r, cfg, baseNow);
                if (res && res.capped) capped[c]++;
            });
            $('#ebay-cvr-rule-table tbody tr').each(function() {
                const k = $(this).data('key');
                $(this).find('.ebay-cvr-rule-count').text(counts[k].toLocaleString());
                $(this).find('.ebay-cvr-rule-capped').text(cfg.lower_cap ? capped[k].toLocaleString() : '—');
            });
            $('#ebay-cvr-cap-npft, #ebay-cvr-cap-nroi').prop('disabled', !cfg.lower_cap);
        }
        function ebayCvrRulePaintModal() {
            const cfg = ebayCvrRule || {};
            $('#ebay-cvr-rule-table tbody tr').each(function() {
                const key = $(this).data('key');
                const v = cfg[key];
                $(this).find('.ebay-cvr-rule-pct').val(v === null || v === undefined || v === '' ? '' : v);
            });
            $('#ebay-cvr-rule-enabled').prop('checked', !!cfg.enabled);
            ebayCvrRulePaintOnOffText();
            $('#ebay-cvr-band-down').val(ebayCvrRuleBandPcts(cfg).down);
            $('#ebay-cvr-band-up').val(ebayCvrRuleBandPcts(cfg).up);
            $('#ebay-cvr-cap-enabled').prop('checked', !!cfg.lower_cap);
            $('#ebay-cvr-cap-npft').val(cfg.min_npft === null || cfg.min_npft === undefined ? '' : cfg.min_npft);
            $('#ebay-cvr-cap-nroi').val(cfg.min_nroi === null || cfg.min_nroi === undefined ? '' : cfg.min_nroi);
            ebayCvrRulePaintCounts();
            $('#ebay-cvr-rule-note').text('SKU counts are the child SKUs loaded in the table. A SKU with no LT O CVR (no sessions) is not counted. Capped = SKUs whose new price is set to the Lower cap price (based on each SKU\'s S PRC before this rule).');
        }

        $(document).ready(function() {
            ebayCvrRuleRefreshLabels(ebayCvrRule);
            ebayCvrRulePaintState();
            $('#ebay-cvr-rule-btn').off('click.ebaycvr').on('click.ebaycvr', function(e) {
                e.preventDefault();
                ebayCvrRulePaintModal();
                const el = document.getElementById('ebayCvrRuleModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $(document).off('input.ebaycvrcount change.ebaycvrcount').on('input.ebaycvrcount change.ebaycvrcount', '#ebay-cvr-rule-table input, #ebay-cvr-cap-box input, #ebay-cvr-band-box input', ebayCvrRulePaintCounts);
            $(document).off('change.ebaycvronoff').on('change.ebaycvronoff', '#ebay-cvr-rule-enabled', ebayCvrRulePaintOnOffText);
            $('#ebay-cvr-rule-save').off('click.ebaycvr').on('click.ebaycvr', function() {
                const $btn = $(this).prop('disabled', true);
                const payload = ebayCvrRuleReadDraft();
                $.ajax({
                    url: @json($ebayCvrRuleChannel === 'shopify_b2c' ? route('shopify.b2c.cvr-price-rule.save') : ($ebayCvrRuleChannel === 'ebay3' ? route('ebay3.cvr-price-rule.save') : ($ebayCvrRuleChannel === 'ebay2' ? route('ebay2.cvr-price-rule.save') : route('ebay.cvr-price-rule.save')))),
                    method: 'POST',
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': ($('meta[name="csrf-token"]').attr('content') || ''), 'Accept': 'application/json' }
                }).done(function(res) {
                    if (!res || !res.success) { showToast('error', 'Could not save CVR Price Rule'); return; }
                    ebayCvrRule = { enabled: !!res.enabled, down: res.down, same: res.same, up: res.up, lower_cap: !!res.lower_cap, min_npft: res.min_npft, min_nroi: res.min_nroi, down_band: res.down_band, up_band: res.up_band };
                    ebayCvrRuleRefreshLabels(ebayCvrRule);
                    ebayCvrRulePaintState();
                    if (typeof table !== 'undefined' && table) table.redraw(true);
                    // Server-owned S PRC (Shopify B2C): the page waits for the server's re-save, then reloads its rows.
                    if (typeof window.ebayCvrRuleOnSaved === 'function') window.ebayCvrRuleOnSaved();
                    // eBay: the rule's own auto push starts at once (like the Sprc Dil switch): reset the blue-push passes and queue the blue SKUs.
                    @if($ebayCvrRuleChannel !== 'shopify_b2c')
                    try {
                        window._chPushSpriceLeftoverPasses = 0;
                        window._chPushSpriceLeftoverLastN = -1;
                        if (typeof window.chPushSpriceScheduleLeftover === 'function') window.chPushSpriceScheduleLeftover(1500);
                    } catch (e) { /* no push queue on this page */ }
                    @endif
                    showToast('success', ebayCvrRule.enabled ? 'CVR Price Rule saved and ON. S PRC is being re-saved with the new prices.' : 'CVR Price Rule saved (OFF). S PRC is being re-saved without it.');
                    const el = document.getElementById('ebayCvrRuleModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                }).fail(function() {
                    showToast('error', 'Could not save CVR Price Rule');
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
