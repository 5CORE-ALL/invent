{{-- LMP + CVR rule: when LMP is available and CVR is decreasing, add an extra discount. Parts: buttons, modals, script. --}}
@php $amzLmpCvrPart = $amzLmpCvrPart ?? 'all'; @endphp

@if($amzLmpCvrPart === 'buttons' || $amzLmpCvrPart === 'all')
                    <button type="button" class="btn btn-sm btn-warning" id="amz-lmp-cvr-btn" style="font-weight:600;color:#000;"
                        title="If LMP is available and CVR is decreasing, automatically add an extra discount.">
                        <i class="fas fa-chart-line"></i> LMP+CVR Rule
                        <span class="badge bg-dark ms-1" id="amz-lmp-cvr-state" style="display:none;">ON</span>
                        <span class="badge bg-secondary ms-1" id="amz-lmp-cvr-count" title="SKUs with LMP available and CVR decreasing">0</span>
                    </button>
@endif

@if($amzLmpCvrPart === 'modals' || $amzLmpCvrPart === 'all')
    <div class="modal fade" id="amzLmpCvrModal" tabindex="-1" aria-labelledby="amzLmpCvrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-1" id="amzLmpCvrModalLabel"><i class="fas fa-chart-line me-1"></i> LMP + CVR Rule</h5>
                        <div class="text-muted" style="font-size:13px;"><b>If</b> LMP is available <b>and CVR is going down</b> (CVR L30 below CVR L45), the S PRC discount goes up automatically. The bigger the CVR fall, the bigger the extra discount. SKUs whose CVR is not down are not touched.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="amz-lmp-cvr-enabled">
                        <label class="form-check-label fw-semibold" for="amz-lmp-cvr-enabled">Rule ON</label>
                    </div>
                    <table class="table table-bordered align-middle mb-2 text-center" style="font-size:14px;">
                        <thead style="background:#dbe8fb;">
                            <tr>
                                <th class="text-start">CVR fall</th>
                                <th>CVR fall (points)</th>
                                <th>Extra discount %</th>
                                <th>S PRC</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-start">Small</td>
                                <td><span class="text-muted me-1">&lt;</span><input type="number" min="0" step="0.1" class="form-control form-control-sm d-inline-block amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-t1"></td>
                                <td><input type="number" min="0" max="80" step="0.1" class="form-control form-control-sm d-inline-block amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-d1"></td>
                                <td class="text-muted">Std × <span id="amz-lmp-cvr-f1"></span></td>
                                <td><b id="amz-lmp-cvr-c1">0</b> <span style="color:#2563eb;">&#9679;</span></td>
                            </tr>
                            <tr>
                                <td class="text-start">Medium</td>
                                <td class="text-muted"><span id="amz-lmp-cvr-mid"></span></td>
                                <td><input type="number" min="0" max="80" step="0.1" class="form-control form-control-sm d-inline-block amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-d2"></td>
                                <td class="text-muted">Std × <span id="amz-lmp-cvr-f2"></span></td>
                                <td><b id="amz-lmp-cvr-c2">0</b> <span style="color:#7c3aed;">&#9679;</span></td>
                            </tr>
                            <tr>
                                <td class="text-start">Large</td>
                                <td><span class="text-muted me-1">&ge;</span><input type="number" min="0" step="0.1" class="form-control form-control-sm d-inline-block amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-t2"></td>
                                <td><input type="number" min="0" max="80" step="0.1" class="form-control form-control-sm d-inline-block amz-lmp-cvr-in" style="width:80px;" id="amz-lmp-cvr-d3"></td>
                                <td class="text-muted">Std × <span id="amz-lmp-cvr-f3"></span></td>
                                <td><b id="amz-lmp-cvr-c3">0</b> <span style="color:#f97316;">&#9679;</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="text-muted small">
                        <b id="amz-lmp-cvr-s-hit">0</b> SKU(s) get extra discount now · LMP available: <b id="amz-lmp-cvr-s-lmp">0</b> · CVR down: <b id="amz-lmp-cvr-s-down">0</b> · In stock: <b id="amz-lmp-cvr-s-total">0</b>.
                        Extra discount is added on top of the other discounts in Sum Disc. "CVR fall" = CVR L45 minus CVR L30.
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
        // ---- Dynamic Rule: LMP available + CVR decreasing → extra discount ----
        let amzLmpCvr = @json(\App\Http\Controllers\MarketPlace\OverallAmazonController::amazonLmpCvrDiscRuleConfig());

        /** The saved rule, or null while the page script is still starting up. */
        function amzLmpCvrRuleSafe() {
            try { return amzLmpCvr || null; } catch (e) { return null; }
        }
        /**
         * LMP / CVR facts and the rule discount for one row. cfgOv previews a draft rule (pass enabled:true).
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
            const drop = cvr45 > 0 ? cvr45 - Math.max(0, cvr) : 0;
            const decreasing = cvr45 > 0 && drop > 0.1;
            let band = 0;
            let disc = 0;
            if (lmp > 0 && decreasing) {
                band = drop < (Number(cfg.t1) || 0) ? 1 : (drop < (Number(cfg.t2) || 0) ? 2 : 3);
                if (cfg.enabled) {
                    disc = Math.max(0, Number(cfg['d' + band]) || 0);
                    if (typeof amzStdScaleDisc === 'function') disc = Number(amzStdScaleDisc(d && d.STANDARD_PRICE, disc)) || 0;
                }
            }
            return { lmp: lmp, cvr: cvr, cvr45: cvr45, drop: drop, decreasing: decreasing, hasLmp: lmp > 0, band: band, disc: disc };
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
        /** Count SKUs by LMP / CVR state and by band for a rule (draft or saved; always evaluated as enabled). */
        function amzLmpCvrStats(cfg) {
            const preview = Object.assign({}, cfg || amzLmpCvrRuleSafe() || {}, { enabled: true });
            const out = { total: 0, lmp: 0, down: 0, hit: 0, bands: [0, 0, 0, 0] };
            amzLmpCvrAllRows().forEach(function(d) {
                const info = amzLmpCvrInfo(d, preview);
                if (!info) return;
                out.total++;
                if (info.hasLmp) out.lmp++;
                if (info.decreasing) out.down++;
                if (info.band) { out.hit++; out.bands[info.band]++; }
            });
            return out;
        }
        function amzLmpCvrReadDraft() {
            const num = function(id, def) {
                const v = parseFloat($(id).val());
                return isFinite(v) ? Math.max(0, v) : def;
            };
            let t1 = num('#amz-lmp-cvr-t1', 0.5);
            let t2 = num('#amz-lmp-cvr-t2', 1.5);
            if (t2 < t1) { const x = t1; t1 = t2; t2 = x; }
            return {
                enabled: $('#amz-lmp-cvr-enabled').is(':checked'),
                t1: t1, t2: t2,
                d1: Math.min(80, num('#amz-lmp-cvr-d1', 0)),
                d2: Math.min(80, num('#amz-lmp-cvr-d2', 0)),
                d3: Math.min(80, num('#amz-lmp-cvr-d3', 0))
            };
        }
        function amzLmpCvrPaintModalStats() {
            const d = amzLmpCvrReadDraft();
            const s = amzLmpCvrStats(d);
            $('#amz-lmp-cvr-s-total').text(s.total.toLocaleString());
            $('#amz-lmp-cvr-s-lmp').text(s.lmp.toLocaleString());
            $('#amz-lmp-cvr-s-down').text(s.down.toLocaleString());
            $('#amz-lmp-cvr-s-hit').text(s.hit.toLocaleString());
            for (let i = 1; i <= 3; i++) {
                $('#amz-lmp-cvr-c' + i).text(s.bands[i].toLocaleString());
                $('#amz-lmp-cvr-f' + i).text((Math.round((1 - d['d' + i] / 100) * 1000) / 1000));
            }
            $('#amz-lmp-cvr-mid').text(d.t1 + ' – ' + d.t2);
        }
        /** Button badges: ON flag + live count of SKUs with LMP available and CVR decreasing. */
        function amzLmpCvrPaintState() {
            const cfg = amzLmpCvrRuleSafe();
            $('#amz-lmp-cvr-state').toggle(!!(cfg && cfg.enabled));
            if (!cfg) return;
            const s = amzLmpCvrStats(cfg);
            $('#amz-lmp-cvr-count')
                .text(s.hit.toLocaleString())
                .toggleClass('bg-success', !!cfg.enabled)
                .toggleClass('bg-secondary', !cfg.enabled)
                .attr('title', s.hit + ' SKU(s) have LMP available and CVR decreasing' + (cfg.enabled ? ' — discount applied' : ' — rule is off'));
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
            return $.ajax({
                url: @json(route('amazon.lmp-cvr-disc-rule.save')),
                method: 'POST',
                data: JSON.stringify(payload),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' }
            }).done(function(res) {
                if (!res || !res.success) { amzPefToast('error', 'Could not save LMP+CVR Rule'); return; }
                amzLmpCvr = { enabled: !!res.enabled, t1: res.t1, t2: res.t2, d1: res.d1, d2: res.d2, d3: res.d3 };
                amzLmpCvrPaintState();
                if (typeof amzTableRedrawPreserveScroll === 'function') amzTableRedrawPreserveScroll(true);
                else if (typeof table !== 'undefined' && table) table.redraw(true);
                amzPefToast('success', amzLmpCvr.enabled
                    ? 'LMP+CVR Rule saved. Sum Disc. now adds the extra discount where LMP is available and CVR is decreasing.'
                    : 'LMP+CVR Rule saved (off).');
                const el = document.getElementById('amzLmpCvrModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                if (typeof done === 'function') done(res);
            }).fail(function() {
                amzPefToast('error', 'Could not save LMP+CVR Rule');
            });
        }

        $(document).ready(function() {
            amzLmpCvrPaintState();
            amzLmpCvrBindTable(0);
            $('#amz-lmp-cvr-btn').off('click.amzlc').on('click.amzlc', function(e) {
                e.preventDefault();
                const cfg = amzLmpCvrRuleSafe() || {};
                $('#amz-lmp-cvr-enabled').prop('checked', !!cfg.enabled);
                $('#amz-lmp-cvr-t1').val(cfg.t1 != null ? cfg.t1 : 0.5);
                $('#amz-lmp-cvr-t2').val(cfg.t2 != null ? cfg.t2 : 1.5);
                $('#amz-lmp-cvr-d1').val(cfg.d1 != null ? cfg.d1 : 2);
                $('#amz-lmp-cvr-d2').val(cfg.d2 != null ? cfg.d2 : 4);
                $('#amz-lmp-cvr-d3').val(cfg.d3 != null ? cfg.d3 : 6);
                amzLmpCvrPaintModalStats();
                const el = document.getElementById('amzLmpCvrModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $(document).off('input.amzlcstats').on('input.amzlcstats', '.amz-lmp-cvr-in', amzLmpCvrPaintModalStats);
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
                    const pick = collectAmzPromoApplyTargets('No rows to apply LMP+CVR Rule');
                    if (pick.cancelled || !pick.targets.length) return;
                    applyAmzCombinedPlanToTargets(pick.targets, pick.label, {
                        toastLabel: 'LMP+CVR Rule',
                        match: function(d) { return computeAmzLmpCvrDiscountPct(d) > 0; }
                    });
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
