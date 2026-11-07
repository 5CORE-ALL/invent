{{--
  eBay 1 / 2 / 3: "CVR up / down — extra discount on S PRC" (the same rule Amazon has in its Sprc Dil modal).
  Parts: section (card block inside #ebayDilGroiModal), script (page JS, after the Sprc Dil script).
  Arrow = the arrow of the CVR 30 column (CVR 30 vs CVR 60, +/- 0.1). Only arrow Down / Up bands apply, a flat arrow gets nothing.
  The discount (Disc % of Std Prc, Std under $15 halved) comes off S PRC AFTER the LMP cap and the Std Prc maximum, in both
  Std prc vs dil and Sprc Dil. Server twin: App\Support\EbayRuleSprice::price().
--}}
@php
    $ebayLmpCvrPart = $ebayLmpCvrPart ?? 'all';
    $ebayLmpCvrChannel = $ebayLmpCvrChannel ?? 'ebay1';
@endphp

@if($ebayLmpCvrPart === 'section' || $ebayLmpCvrPart === 'all')
                    <div class="ebay-dg-rules-title mt-3"
                        title="Extra discount on S PRC for SKUs whose CVR 30 arrow is Down or Up. Autosaved. Count = SKUs (INV &gt; 0) in that row.">
                        CVR up / down — extra discount on S PRC
                    </div>
                    <div class="small text-muted mb-1">
                        <strong>If</strong> the CVR 30 arrow is Down or Up and CVR is inside a row,
                        <strong>take that row's Disc % of Std Prc off S PRC</strong>, after the LMP and Std Prc steps (Std prc vs dil and Sprc Dil). A flat arrow is not changed.
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-1">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="ebay-lmp-cvr-enabled">
                            <label class="form-check-label fw-semibold small" for="ebay-lmp-cvr-enabled">Rule ON</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input ebay-lmp-cvr-in" type="checkbox" id="ebay-lmp-cvr-needlmp">
                            <label class="form-check-label small" for="ebay-lmp-cvr-needlmp">Only when LMP is available</label>
                        </div>
                        <span class="small text-muted ms-auto"><b id="ebay-lmp-cvr-s-hit">0</b> SKU(s) get a discount · <b id="ebay-lmp-cvr-s-total">0</b> in stock</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="ebay-lmp-cvr-table">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th class="text-center">CVR%</th>
                                    <th class="text-end">Disc %</th>
                                    <th class="text-center" style="width:80px;" title="SKUs (INV &gt; 0) in this row that get the discount (LMP condition applied)">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Down</td>
                                    <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-down2_lt" value="4"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-down2_disc" value="6"></td>
                                    <td class="ebay-dg-count"><span id="ebay-lmp-cvr-c-down2">0</span> <span style="color:#a00211;">&#9679;</span></td>
                                </tr>
                                <tr>
                                    <td>Down</td>
                                    <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-down_lt" value="7"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-down_disc" value="4"></td>
                                    <td class="ebay-dg-count"><span id="ebay-lmp-cvr-c-down">0</span> <span style="color:#e04b5a;">&#9679;</span></td>
                                </tr>
                                <tr>
                                    <td>Up</td>
                                    <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-up_gt" value="13"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-up_disc" value="0"></td>
                                    <td class="ebay-dg-count"><span id="ebay-lmp-cvr-c-up">0</span> <span style="color:#28a745;">&#9679;</span></td>
                                </tr>
                                <tr>
                                    <td>Up</td>
                                    <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-up2_gt" value="15"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm ebay-lmp-cvr-in" id="ebay-lmp-cvr-up2_disc" value="0"></td>
                                    <td class="ebay-dg-count"><span id="ebay-lmp-cvr-c-up2">0</span> <span style="color:#146c2e;">&#9679;</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
@endif

@if($ebayLmpCvrPart === 'script' || $ebayLmpCvrPart === 'all')
        // ---- eBay 1 / 2 / 3 rule: extra discount on the CVR 30 arrow (Down / Up) bands. Edited inside the Sprc Dil modal. ----
        const EBAY_LMP_CVR_CHANNEL = @json($ebayLmpCvrChannel);
        let ebayLmpCvr = @json(\App\Support\AmazonLmpCvrDiscRule::config($ebayLmpCvrChannel));

        function ebayLmpCvrRuleSafe() {
            try { return ebayLmpCvr || null; } catch (e) { return null; }
        }
        function ebayLmpCvrRound2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
        /**
         * Band and discount of one row. cfgOv previews a draft rule (pass enabled:true). null for parent rows,
         * rows with no stock and clearance SKUs (they are priced from the Clearance Target NROI% only).
         */
        function ebayLmpCvrInfo(d, cfgOv) {
            if (typeof ebayDgIsChild !== 'function' || !ebayDgIsChild(d)) return null;
            if (typeof ebayDgInv === 'function' && !(ebayDgInv(d) > 0)) return null;
            if (typeof window.chStdIsClearance === 'function' && window.chStdIsClearance(d)) return null;
            const cfg = cfgOv || ebayLmpCvrRuleSafe();
            if (!cfg) return null;
            const lmp = typeof ebayEffectiveLmp === 'function' ? (Number(ebayEffectiveLmp(d)) || 0)
                : (typeof ebayDgRowLmp === 'function' ? (Number(ebayDgRowLmp(d)) || 0) : 0);
            const cvr = parseFloat(d && d.SCVR) || 0;
            const hasC60 = !!(d && d.CVR_60 != null && d.CVR_60 !== '');
            const cvr60 = hasC60 ? (parseFloat(d.CVR_60) || 0) : (parseFloat(d && d.CVR_45) || 0);
            // Same arrow as the CVR 30 column: CVR 30 vs CVR 60, +/- 0.1 (CVR 0 against 0 is flat).
            const tol = 0.1;
            const trend = cvr < cvr60 - tol ? 'down' : (cvr > cvr60 + tol ? 'up' : 'flat');
            let band = null;
            if (trend === 'down') {
                const slabs = [{ k: 'down2', v: Number(cfg.down2_lt) || 0 }, { k: 'down', v: Number(cfg.down_lt) || 0 }]
                    .filter(function(x) { return x.v > 0; }).sort(function(x, y) { return x.v - y.v; });
                for (let i = 0; i < slabs.length; i++) if (cvr < slabs[i].v) { band = slabs[i].k; break; }
            } else if (trend === 'up') {
                const slabs = [{ k: 'up', v: Number(cfg.up_gt) || 0 }, { k: 'up2', v: Number(cfg.up2_gt) || 0 }]
                    .sort(function(x, y) { return y.v - x.v; });
                for (let i = 0; i < slabs.length; i++) if (cvr > slabs[i].v) { band = slabs[i].k; break; }
            }
            const eligible = !cfg.need_lmp || lmp > 0;
            const std = parseFloat(d && d.STANDARD_PRICE) || 0;
            let disc = 0;
            if (cfg.enabled && eligible && band) {
                disc = Number(cfg[band + '_disc']) || 0;
                // Std Prc under $15 halves every rule discount (same as the server).
                if (disc && typeof chStdScaleDisc === 'function') disc = Number(chStdScaleDisc(std, disc)) || 0;
            }
            return { lmp: lmp, cvr: cvr, cvr60: cvr60, trend: trend, band: band, eligible: eligible, disc: disc, std: std };
        }
        /** Discount % this rule takes off for one row (0 when off / not matching). */
        function ebayLmpCvrDiscPct(d) {
            const info = ebayLmpCvrInfo(d);
            return info ? info.disc : 0;
        }
        /** S PRC after the rule: the price already capped at LMP / Std, minus Disc % of Std Prc. Same maths as EbayRuleSprice::price(). */
        function ebayLmpCvrApplyToPrice(d, price) {
            const p = Number(price) || 0;
            if (!(p > 0)) return p;
            const info = ebayLmpCvrInfo(d);
            if (!info || !(info.std > 0) || Math.abs(info.disc) < 0.01) return p;
            const after = ebayLmpCvrRound2(p - (info.std * info.disc / 100));
            return after >= 0.01 ? after : p;
        }
        window.ebayLmpCvrInfo = ebayLmpCvrInfo;
        window.ebayLmpCvrDiscPct = ebayLmpCvrDiscPct;
        window.ebayLmpCvrApplyToPrice = ebayLmpCvrApplyToPrice;
        /** Hover card step for the S PRC cell. `after` = S PRC shown (rule already taken off). null when the rule is off. */
        window.ebayLmpCvrStep = function(d, after) {
            const cfg = ebayLmpCvrRuleSafe();
            if (!cfg || !cfg.enabled) return null;
            const info = ebayLmpCvrInfo(d);
            if (!info) return null;
            const money = function(n) { n = Number(n); return (isFinite(n) && n > 0) ? '$' + n.toFixed(2) : '—'; };
            const title = 'CVR up / down discount';
            const head = 'CVR ' + info.cvr.toFixed(1) + '% (' + info.trend + ' arrow vs CVR 60 ' + info.cvr60.toFixed(1) + '%)';
            if (cfg.need_lmp && !(info.lmp > 0)) return { state: 'skip', title: title, detail: head + '. This rule needs an LMP and there is none. Not applied.' };
            if (!info.band) return { state: 'skip', title: title, detail: head + ' is in no Down / Up row. Not applied.' };
            if (!info.disc) return { state: 'skip', title: title, detail: head + ' is in the ' + info.band.replace('2', ' (2nd)') + ' row, but its Disc % is 0. Not applied.' };
            const off = ebayLmpCvrRound2(info.std * info.disc / 100);
            const before = ebayLmpCvrRound2((Number(after) || 0) + off);
            return {
                state: 'ok', title: title,
                detail: head + ' → <b>' + info.disc + '%</b> of Std Prc ' + money(info.std) + ' = <b>−' + money(off) + '</b> taken off after the LMP and Std Prc steps: '
                    + money(before) + ' − ' + money(off) + ' = <b>' + money(after) + '</b>'
            };
        };

        function ebayLmpCvrAllRows() {
            return (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
        }
        /** Count in-stock SKUs per band (LMP condition applied) and how many get a non-zero discount. */
        function ebayLmpCvrStats(cfg) {
            const preview = Object.assign({}, cfg || ebayLmpCvrRuleSafe() || {}, { enabled: true });
            const out = { total: 0, hit: 0, down2: 0, down: 0, up: 0, up2: 0 };
            const visit = function(d) {
                if (!d || typeof d !== 'object') return;
                const info = ebayLmpCvrInfo(d, preview);
                if (info) {
                    out.total++;
                    if (info.eligible && info.band) {
                        out[info.band]++;
                        if (info.disc) out.hit++;
                    }
                }
                if (Array.isArray(d._children)) d._children.forEach(visit);
            };
            ebayLmpCvrAllRows().forEach(visit);
            return out;
        }
        const EBAY_LMP_CVR_KEYS = ['down2_lt', 'down2_disc', 'down_lt', 'down_disc', 'up_gt', 'up_disc', 'up2_gt', 'up2_disc'];
        const EBAY_LMP_CVR_DEF = { down2_lt: 4, down2_disc: 6, down_lt: 7, down_disc: 4, up_gt: 13, up_disc: 0, up2_gt: 15, up2_disc: 0 };
        function ebayLmpCvrReadDraft() {
            const out = {
                enabled: $('#ebay-lmp-cvr-enabled').is(':checked'),
                need_lmp: $('#ebay-lmp-cvr-needlmp').is(':checked')
            };
            EBAY_LMP_CVR_KEYS.forEach(function(k) {
                const v = parseFloat($('#ebay-lmp-cvr-' + k).val());
                const isDisc = k.slice(-5) === '_disc';
                out[k] = isFinite(v) ? (isDisc ? Math.min(80, Math.max(-50, v)) : Math.min(100, Math.max(0, v))) : 0;
            });
            return out;
        }
        /** Put the saved rule into the Sprc Dil modal fields. */
        function ebayLmpCvrFillForm() {
            const cfg = ebayLmpCvrRuleSafe() || {};
            $('#ebay-lmp-cvr-enabled').prop('checked', !!cfg.enabled);
            $('#ebay-lmp-cvr-needlmp').prop('checked', cfg.need_lmp !== false);
            EBAY_LMP_CVR_KEYS.forEach(function(k) { $('#ebay-lmp-cvr-' + k).val(cfg[k] != null ? cfg[k] : EBAY_LMP_CVR_DEF[k]); });
            ebayLmpCvrPaintModalStats();
        }
        function ebayLmpCvrPaintModalStats() {
            if (!document.getElementById('ebay-lmp-cvr-table')) return;
            const s = ebayLmpCvrStats(ebayLmpCvrReadDraft());
            $('#ebay-lmp-cvr-s-total').text(s.total.toLocaleString());
            $('#ebay-lmp-cvr-s-hit').text(s.hit.toLocaleString());
            ['down2', 'down', 'up', 'up2'].forEach(function(k) { $('#ebay-lmp-cvr-c-' + k).text(s[k].toLocaleString()); });
        }
        let ebayLmpCvrSaveTimer = null;
        /**
         * Saves the rule (this page, or the three eBay pages with saveAll). The page uses the new numbers at once; the server
         * re-saves S PRC from the rules right after (nothing is pushed here).
         */
        function ebayLmpCvrSave(saveAll) {
            if (!document.getElementById('ebay-lmp-cvr-table')) return null;
            if (ebayLmpCvrSaveTimer) { clearTimeout(ebayLmpCvrSaveTimer); ebayLmpCvrSaveTimer = null; }
            const payload = ebayLmpCvrReadDraft();
            ebayLmpCvr = Object.assign({}, payload);
            if (typeof redrawEbaySprcDilColumn === 'function') redrawEbaySprcDilColumn();
            const body = Object.assign({}, payload, { enabled: payload.enabled ? 1 : 0, need_lmp: payload.need_lmp ? 1 : 0, all: saveAll === true ? 1 : 0 });
            return $.ajax({
                url: @json(route('channel.lmp-cvr-disc-rule.save', ['channel' => $ebayLmpCvrChannel])),
                method: 'POST',
                data: JSON.stringify(body),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': (typeof ebayDgCsrf === 'function' ? ebayDgCsrf() : ''), 'Accept': 'application/json' }
            }).fail(function() {
                if (typeof ebayDgToast === 'function') ebayDgToast('error', 'Could not save the CVR up / down discount');
            });
        }
        function ebayLmpCvrScheduleSave() {
            if (ebayLmpCvrSaveTimer) clearTimeout(ebayLmpCvrSaveTimer);
            ebayLmpCvrSaveTimer = setTimeout(function() { ebayLmpCvrSaveTimer = null; ebayLmpCvrSave(false); }, 700);
        }
        window.ebayLmpCvrFillForm = ebayLmpCvrFillForm;
        window.ebayLmpCvrSave = ebayLmpCvrSave;

        $(document).ready(function() {
            $(document).off('input.ebaylcstats change.ebaylcstats').on('input.ebaylcstats change.ebaylcstats', '.ebay-lmp-cvr-in, #ebay-lmp-cvr-enabled', function() {
                ebayLmpCvrPaintModalStats();
                ebayLmpCvrScheduleSave();
            });
        });
@endif
