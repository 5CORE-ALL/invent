{{-- CVR arrow rule (extra S PRC discount on CVR Down / Up bands). Lives inside the Sprc Dil modal. Parts: section, script. --}}
@php $amzLmpCvrPart = $amzLmpCvrPart ?? 'all'; @endphp

@if($amzLmpCvrPart === 'section' || $amzLmpCvrPart === 'all')
                    <div class="amz-dg-side">
                    <div class="amz-dg-rules-title mt-3"
                        title="Extra discount on S PRC for SKUs whose CVR L30 arrow is Down or Up. Saved with Save Only / Save All. Count = SKUs (INV &gt; 0) in that row.">
                        <span class="amz-dg-seq" title="Sequence number: the order of the price rules (the LMP cap with the Std Prc maximum, and this CVR up / down discount). The price rules always run after the Target NROI rules. Saved with Save Only / Save All and used by the server job too."><span class="amz-dg-seq-hash">Seq</span><input type="number" min="1" max="8" step="1" class="amz-dg-seq-in" data-amz-seq="cvrud" value="7"></span>CVR up / down — extra discount on S PRC
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 mb-1">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="amz-lmp-cvr-enabled">
                            <label class="form-check-label fw-semibold small" for="amz-lmp-cvr-enabled">Rule ON</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input amz-lmp-cvr-in" type="checkbox" id="amz-lmp-cvr-needlmp">
                            <label class="form-check-label small" for="amz-lmp-cvr-needlmp">Only when LMP is available</label>
                        </div>
                        <span class="small text-muted ms-auto"><b id="amz-lmp-cvr-s-hit">0</b> SKU(s) get a discount · <b id="amz-lmp-cvr-s-total">0</b> in stock</span>
                    </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="amz-lmp-cvr-table">
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
                                    <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-down2_lt" value="4"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-down2_disc" value="6"></td>
                                    <td class="amz-dg-count"><span id="amz-lmp-cvr-c-down2">0</span> <span style="color:#a00211;">&#9679;</span></td>
                                </tr>
                                <tr>
                                    <td>Down</td>
                                    <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-down_lt" value="7"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-down_disc" value="4"></td>
                                    <td class="amz-dg-count"><span id="amz-lmp-cvr-c-down">0</span> <span style="color:#e04b5a;">&#9679;</span></td>
                                </tr>
                                <tr>
                                    <td>Up</td>
                                    <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-up_gt" value="13"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-up_disc" value="0"></td>
                                    <td class="amz-dg-count"><span id="amz-lmp-cvr-c-up">0</span> <span style="color:#28a745;">&#9679;</span></td>
                                </tr>
                                <tr>
                                    <td>Up</td>
                                    <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-up2_gt" value="15"></td>
                                    <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm amz-cvr-groi-input amz-lmp-cvr-in" id="amz-lmp-cvr-up2_disc" value="0"></td>
                                    <td class="amz-dg-count"><span id="amz-lmp-cvr-c-up2">0</span> <span style="color:#146c2e;">&#9679;</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="amz-dg-card-rules" data-card="cvrud">
                        <div class="amz-dg-card-rules-head"><span><i class="fas fa-list-ul me-1"></i> Rules</span><button type="button" class="amz-dg-rule-add" title="Add a rule" aria-label="Add a rule">+</button></div>
                        <div class="amz-dg-rules-list"></div>
                    </div>
@endif

@if($amzLmpCvrPart === 'script' || $amzLmpCvrPart === 'all')
        // ---- Dynamic Rule: extra discount on the CVR arrow (Down / Up) bands. Edited inside the Sprc Dil modal. ----
        let amzLmpCvr = @json(\App\Http\Controllers\MarketPlace\OverallAmazonController::amazonLmpCvrDiscRuleConfig());

        /** The saved rule, or null while the page script is still starting up. */
        function amzLmpCvrRuleSafe() {
            try { return amzLmpCvr || null; } catch (e) { return null; }
        }
        /**
         * CVR arrow band and the rule discount for one row. cfgOv previews a draft rule (pass enabled:true).
         * null for parent rows and rows with no stock.
         */
        function amzLmpCvrInfo(d, cfgOv) {
            if (typeof amzPefIsChildRow !== 'function' || !amzPefIsChildRow(d)) return null;
            if (typeof amzPefInv === 'function' && !(amzPefInv(d) > 0)) return null;
            const cfg = cfgOv || amzLmpCvrRuleSafe();
            if (!cfg) return null;
            const lmp = typeof amzPefLmp === 'function' ? (Number(amzPefLmp(d)) || 0) : 0;
            const cvr = typeof amzPefCvrL30Live === 'function' ? (Number(amzPefCvrL30Live(d)) || 0) : 0;
            const cvr45 = typeof amzPefCvrL45Live === 'function' ? (Number(amzPefCvrL45Live(d)) || 0) : 0;
            // Same arrow as the CVR L30 column: Down (also when CVR is 0), Up, or flat.
            const trend = typeof amzPefCvrTrend === 'function' ? amzPefCvrTrend(d) : 'flat';
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
            let disc = 0;
            if (cfg.enabled && eligible && band) {
                disc = Number(cfg[band + '_disc']) || 0;
                if (disc && typeof amzStdScaleDisc === 'function') disc = Number(amzStdScaleDisc(d && d.STANDARD_PRICE, disc)) || 0;
            }
            return { lmp: lmp, cvr: cvr, cvr45: cvr45, trend: trend, band: band, eligible: eligible, disc: disc };
        }
        /** Discount % this rule adds to the Sum Disc. (0 when off / not matching). */
        function computeAmzLmpCvrDiscountPct(d) {
            const info = amzLmpCvrInfo(d);
            return info ? info.disc : 0;
        }
        window.computeAmzLmpCvrDiscountPct = computeAmzLmpCvrDiscountPct;

        function amzLmpCvrAllRows() {
            return (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
        }
        /** Count in-stock SKUs per band (that pass the LMP condition) and how many get a non-zero discount. */
        function amzLmpCvrStats(cfg) {
            const preview = Object.assign({}, cfg || amzLmpCvrRuleSafe() || {}, { enabled: true });
            const out = { total: 0, hit: 0, down2: 0, down: 0, up: 0, up2: 0 };
            amzLmpCvrAllRows().forEach(function(d) {
                const info = amzLmpCvrInfo(d, preview);
                if (!info) return;
                out.total++;
                if (!info.eligible || !info.band) return;
                out[info.band]++;
                if (info.disc) out.hit++;
            });
            return out;
        }
        const AMZ_LMP_CVR_KEYS = ['down2_lt', 'down2_disc', 'down_lt', 'down_disc', 'up_gt', 'up_disc', 'up2_gt', 'up2_disc'];
        const AMZ_LMP_CVR_DEF = { down2_lt: 4, down2_disc: 6, down_lt: 7, down_disc: 4, up_gt: 13, up_disc: 0, up2_gt: 15, up2_disc: 0 };
        function amzLmpCvrReadDraft() {
            const out = {
                enabled: $('#amz-lmp-cvr-enabled').is(':checked'),
                need_lmp: $('#amz-lmp-cvr-needlmp').is(':checked')
            };
            AMZ_LMP_CVR_KEYS.forEach(function(k) {
                const v = parseFloat($('#amz-lmp-cvr-' + k).val());
                const isDisc = k.slice(-5) === '_disc';
                out[k] = isFinite(v) ? (isDisc ? Math.min(80, Math.max(-50, v)) : Math.min(100, Math.max(0, v))) : 0;
            });
            return out;
        }
        /** Put the saved rule into the Sprc Dil modal fields. */
        function amzLmpCvrFillForm() {
            const cfg = amzLmpCvrRuleSafe() || {};
            $('#amz-lmp-cvr-enabled').prop('checked', !!cfg.enabled);
            $('#amz-lmp-cvr-needlmp').prop('checked', cfg.need_lmp !== false);
            AMZ_LMP_CVR_KEYS.forEach(function(k) { $('#amz-lmp-cvr-' + k).val(cfg[k] != null ? cfg[k] : AMZ_LMP_CVR_DEF[k]); });
            amzLmpCvrPaintModalStats();
        }
        function amzLmpCvrPaintModalStats() {
            if (!document.getElementById('amz-lmp-cvr-table')) return;
            const s = amzLmpCvrStats(amzLmpCvrReadDraft());
            $('#amz-lmp-cvr-s-total').text(s.total.toLocaleString());
            $('#amz-lmp-cvr-s-hit').text(s.hit.toLocaleString());
            ['down2', 'down', 'up', 'up2'].forEach(function(k) { $('#amz-lmp-cvr-c-' + k).text(s[k].toLocaleString()); });
        }
        /**
         * Saved together with the Sprc Dil rules (Save Only / Save All). The page uses the new numbers at once; the
         * server copy is written in the background.
         */
        function amzLmpCvrSaveQuiet() {
            if (!document.getElementById('amz-lmp-cvr-table')) return null;
            const payload = amzLmpCvrReadDraft();
            amzLmpCvr = Object.assign({}, payload);
            const body = Object.assign({}, payload, { enabled: payload.enabled ? 1 : 0, need_lmp: payload.need_lmp ? 1 : 0 });
            if (typeof amzTableRedrawPreserveScroll === 'function') amzTableRedrawPreserveScroll(true);
            return $.ajax({
                url: @json(route('amazon.lmp-cvr-disc-rule.save')),
                method: 'POST',
                data: JSON.stringify(body),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' }
            }).fail(function() {
                amzPefToast('error', 'Could not save the CVR up / down discount');
            });
        }
        window.amzLmpCvrFillForm = amzLmpCvrFillForm;
        window.amzLmpCvrSaveQuiet = amzLmpCvrSaveQuiet;

        $(document).ready(function() {
            $(document).off('input.amzlcstats change.amzlcstats').on('input.amzlcstats change.amzlcstats', '.amz-lmp-cvr-in, #amz-lmp-cvr-enabled', amzLmpCvrPaintModalStats);
        });
@endif
