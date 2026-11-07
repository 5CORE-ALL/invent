{{-- CVR trend rule: extra S PRC discount when CVR is Down / Up / Equal. Parts: buttons, modals, script. --}}
@php $amzLmpCvrPart = $amzLmpCvrPart ?? 'all'; @endphp

@if($amzLmpCvrPart === 'buttons' || $amzLmpCvrPart === 'all')
                    <button type="button" class="btn btn-sm btn-warning" id="amz-lmp-cvr-btn" style="font-weight:600;color:#000;"
                        title="Extra S PRC discount on the CVR arrow (Down / Up) bands.">
                        <i class="fas fa-chart-line"></i> CVR Trend Disc
                        <span class="badge bg-dark ms-1" id="amz-lmp-cvr-state" style="display:none;">ON</span>
                        <span class="badge bg-secondary ms-1" id="amz-lmp-cvr-count" title="SKUs getting this discount">0</span>
                    </button>
@endif

@if($amzLmpCvrPart === 'modals' || $amzLmpCvrPart === 'all')
    <div class="modal fade" id="amzLmpCvrModal" tabindex="-1" aria-labelledby="amzLmpCvrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-1" id="amzLmpCvrModalLabel"><i class="fas fa-chart-line me-1"></i> CVR up / down — extra discount on S PRC</h5>
                        <div class="text-muted" style="font-size:13px;">The trend is the arrow next to CVR L30 in the table (Down also when CVR L30 is 0). The discount for the SKU's trend is added to the Sum Disc. and moves on its own when CVR moves.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="amz-lmp-cvr-enabled">
                        <label class="form-check-label fw-semibold" for="amz-lmp-cvr-enabled">Rule ON</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input amz-lmp-cvr-in" type="checkbox" id="amz-lmp-cvr-needlmp">
                        <label class="form-check-label" for="amz-lmp-cvr-needlmp">Only when LMP is available</label>
                    </div>
                    <table class="table table-bordered align-middle mb-2" style="font-size:14px;">
                        <thead style="background:#dbe8fb;">
                            <tr>
                                <th>When (CVR arrow)</th>
                                <th>CVR L30</th>
                                <th class="text-end">Disc %</th>
                                <th class="text-center">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span style="color:#a00211;">&#9679;</span> <i class="fas fa-arrow-down" style="color:#a00211;"></i> Down</td>
                                <td><span class="text-muted me-1">&lt;</span><input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:70px;" id="amz-lmp-cvr-down2_lt"><span class="text-muted ms-1">%</span></td>
                                <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-down2_disc"></td>
                                <td class="text-center"><b id="amz-lmp-cvr-c-down2">0</b></td>
                            </tr>
                            <tr>
                                <td><span style="color:#e04b5a;">&#9679;</span> <i class="fas fa-arrow-down" style="color:#a00211;"></i> Down</td>
                                <td><span class="text-muted me-1">&lt;</span><input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:70px;" id="amz-lmp-cvr-down_lt"><span class="text-muted ms-1">%</span></td>
                                <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-down_disc"></td>
                                <td class="text-center"><b id="amz-lmp-cvr-c-down">0</b></td>
                            </tr>
                            <tr>
                                <td><span style="color:#28a745;">&#9679;</span> <i class="fas fa-arrow-up" style="color:#28a745;"></i> Up</td>
                                <td><span class="text-muted me-1">&gt;</span><input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:70px;" id="amz-lmp-cvr-up_gt"><span class="text-muted ms-1">%</span></td>
                                <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-up_disc"></td>
                                <td class="text-center"><b id="amz-lmp-cvr-c-up">0</b></td>
                            </tr>
                            <tr>
                                <td><span style="color:#146c2e;">&#9679;</span> <i class="fas fa-arrow-up" style="color:#28a745;"></i> Up</td>
                                <td><span class="text-muted me-1">&gt;</span><input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:70px;" id="amz-lmp-cvr-up2_gt"><span class="text-muted ms-1">%</span></td>
                                <td class="text-end"><input type="number" min="-50" max="80" step="0.1" class="form-control form-control-sm d-inline-block text-end amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-up2_disc"></td>
                                <td class="text-center"><b id="amz-lmp-cvr-c-up2">0</b></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="text-muted small">
                        <b id="amz-lmp-cvr-s-hit">0</b> SKU(s) get a discount now · <b id="amz-lmp-cvr-s-total">0</b> in stock.
                        Only SKUs with a Down or Up arrow are touched (a flat arrow gets nothing). Leave a row at 0 for no change. The <b>CVR Trend</b> column in the table shows which SKU is discounted and by how much.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amz-lmp-cvr-apply" title="Save the rule. The S PRC column follows it.">Save</button>
                    <button type="button" class="btn btn-sm btn-success" id="amz-lmp-cvr-apply-push" title="Save and also write S PRC for the SKUs this rule changes (selected rows, or all visible).">Save &amp; push S PRC</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amzLmpCvrPart === 'script' || $amzLmpCvrPart === 'all')
        // ---- Dynamic Rule: extra discount by CVR trend (Down / Up / Equal) ----
        let amzLmpCvr = @json(\App\Http\Controllers\MarketPlace\OverallAmazonController::amazonLmpCvrDiscRuleConfig());

        /** The saved rule, or null while the page script is still starting up. */
        function amzLmpCvrRuleSafe() {
            try { return amzLmpCvr || null; } catch (e) { return null; }
        }
        /**
         * CVR trend and the rule discount for one row. cfgOv previews a draft rule (pass enabled:true).
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
        function amzLmpCvrPaintModalStats() {
            const s = amzLmpCvrStats(amzLmpCvrReadDraft());
            $('#amz-lmp-cvr-s-total').text(s.total.toLocaleString());
            $('#amz-lmp-cvr-s-hit').text(s.hit.toLocaleString());
            ['down2', 'down', 'up', 'up2'].forEach(function(k) { $('#amz-lmp-cvr-c-' + k).text(s[k].toLocaleString()); });
        }
        /** Button badges: ON flag + live count of SKUs that get this discount. */
        function amzLmpCvrPaintState() {
            const cfg = amzLmpCvrRuleSafe();
            $('#amz-lmp-cvr-state').toggle(!!(cfg && cfg.enabled));
            if (!cfg) return;
            const s = amzLmpCvrStats(cfg);
            $('#amz-lmp-cvr-count')
                .text(s.hit.toLocaleString())
                .toggleClass('bg-success', !!cfg.enabled)
                .toggleClass('bg-secondary', !cfg.enabled)
                .attr('title', s.hit + ' SKU(s) ' + (cfg.enabled ? 'get this discount' : 'would get this discount (rule is off)'));
        }
        let amzLmpCvrTimer = null;
        function amzLmpCvrPaintStateSoon() {
            clearTimeout(amzLmpCvrTimer);
            amzLmpCvrTimer = setTimeout(amzLmpCvrPaintState, 400);
        }
        function amzLmpCvrBindTable(tries) {
            tries = tries || 0;
            if (typeof table !== 'undefined' && table && typeof table.on === 'function') {
                try {
                    table.on('dataLoaded', amzLmpCvrPaintStateSoon);
                    table.on('dataProcessed', amzLmpCvrPaintStateSoon);
                } catch (e) { /* ignore */ }
                amzLmpCvrPaintStateSoon();
                return;
            }
            if (tries < 120) setTimeout(function() { amzLmpCvrBindTable(tries + 1); }, 500);
        }
        function amzLmpCvrSave(done) {
            const payload = amzLmpCvrReadDraft();
            payload.enabled = payload.enabled ? 1 : 0;
            payload.need_lmp = payload.need_lmp ? 1 : 0;
            return $.ajax({
                url: @json(route('amazon.lmp-cvr-disc-rule.save')),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' }
            }).done(function(res) {
                if (!res || !res.success) { amzPefToast('error', 'Could not save CVR Trend Disc'); return; }
                amzLmpCvr = Object.assign({ enabled: !!res.enabled, need_lmp: !!res.need_lmp }, AMZ_LMP_CVR_KEYS.reduce(function(o, k) { o[k] = res[k]; return o; }, {}));
                amzLmpCvrPaintState();
                if (typeof amzTableRedrawPreserveScroll === 'function') amzTableRedrawPreserveScroll(true);
                else if (typeof table !== 'undefined' && table) table.redraw(true);
                amzPefToast('success', amzLmpCvr.enabled
                    ? 'CVR Trend Disc saved. Sum Disc. now adds the discount by CVR trend.'
                    : 'CVR Trend Disc saved (off).');
                const el = document.getElementById('amzLmpCvrModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                if (typeof done === 'function') done(res);
            }).fail(function() {
                amzPefToast('error', 'Could not save CVR Trend Disc');
            });
        }

        $(document).ready(function() {
            amzLmpCvrPaintState();
            amzLmpCvrBindTable(0);
            $('#amz-lmp-cvr-btn').off('click.amzlc').on('click.amzlc', function(e) {
                e.preventDefault();
                const cfg = amzLmpCvrRuleSafe() || {};
                $('#amz-lmp-cvr-enabled').prop('checked', !!cfg.enabled);
                $('#amz-lmp-cvr-needlmp').prop('checked', cfg.need_lmp !== false);
                AMZ_LMP_CVR_KEYS.forEach(function(k) { $('#amz-lmp-cvr-' + k).val(cfg[k] != null ? cfg[k] : AMZ_LMP_CVR_DEF[k]); });
                amzLmpCvrPaintModalStats();
                const el = document.getElementById('amzLmpCvrModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $(document).off('input.amzlcstats change.amzlcstats').on('input.amzlcstats change.amzlcstats', '.amz-lmp-cvr-in', amzLmpCvrPaintModalStats);
            $('#amz-lmp-cvr-apply').off('click.amzlc').on('click.amzlc', function() {
                const $btn = $(this).prop('disabled', true);
                amzLmpCvrSave().always(function() { $btn.prop('disabled', false); });
            });
            $('#amz-lmp-cvr-apply-push').off('click.amzlc').on('click.amzlc', function() {
                const $btn = $(this).prop('disabled', true);
                if (!$('#amz-lmp-cvr-enabled').is(':checked')) {
                    amzPefToast('error', 'Turn the rule on first to push S PRC');
                    $btn.prop('disabled', false);
                    return;
                }
                amzLmpCvrSave(function() {
                    if (typeof collectAmzPromoApplyTargets !== 'function' || typeof applyAmzCombinedPlanToTargets !== 'function') return;
                    const pick = collectAmzPromoApplyTargets('No rows to apply CVR Trend Disc');
                    if (pick.cancelled || !pick.targets.length) return;
                    applyAmzCombinedPlanToTargets(pick.targets, pick.label, {
                        toastLabel: 'CVR Trend Disc',
                        match: function(d) { return computeAmzLmpCvrDiscountPct(d) !== 0; }
                    });
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
