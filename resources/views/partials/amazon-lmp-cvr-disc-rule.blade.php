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
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amzLmpCvrModalLabel"><i class="fas fa-chart-line me-1"></i> LMP + CVR Rule — extra discount</h5>
                        <div class="text-muted" style="font-size:12px;">When a SKU has an LMP and its CVR L30 is below CVR L45, it gets <b>Base % + Step % × (extra points of drop)</b>, capped at Max %. The discount is added to the Sum Disc. and moves as CVR moves.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="amz-lmp-cvr-enabled">
                        <label class="form-check-label fw-semibold" for="amz-lmp-cvr-enabled">Add this discount to the Sum Disc. (S PRC)</label>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label mb-0 small">Base discount %</label>
                            <input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm amz-lmp-cvr-in" id="amz-lmp-cvr-base">
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-0 small">Step % per CVR point dropped</label>
                            <input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm amz-lmp-cvr-in" id="amz-lmp-cvr-step">
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-0 small">Max discount %</label>
                            <input type="number" min="0" max="100" step="0.1" class="form-control form-control-sm amz-lmp-cvr-in" id="amz-lmp-cvr-max">
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-0 small">Min CVR drop (points)</label>
                            <input type="number" min="0" max="100" step="0.05" class="form-control form-control-sm amz-lmp-cvr-in" id="amz-lmp-cvr-mindrop">
                        </div>
                    </div>
                    <table class="table table-sm mb-2">
                        <tbody>
                            <tr><td>SKUs in stock</td><td class="text-end fw-semibold" id="amz-lmp-cvr-s-total">0</td></tr>
                            <tr><td>LMP available</td><td class="text-end fw-semibold" id="amz-lmp-cvr-s-lmp">0</td></tr>
                            <tr><td>CVR decreasing</td><td class="text-end fw-semibold" id="amz-lmp-cvr-s-down">0</td></tr>
                            <tr class="table-warning"><td><b>LMP available + CVR decreasing (rule hits)</b></td><td class="text-end fw-bold" id="amz-lmp-cvr-s-hit">0</td></tr>
                        </tbody>
                    </table>
                    <div class="small fw-semibold mb-1">Discount tiers (SKU count)</div>
                    <div id="amz-lmp-cvr-tiers" class="small text-muted">—</div>
                    <div class="text-muted mt-3" style="font-size:12px;">Counts update live as you change the numbers. "Apply" saves the rule; the S PRC column follows it. "Apply &amp; push S PRC" also writes S PRC for the rows it hits (selected rows, or all visible).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amz-lmp-cvr-apply">Apply</button>
                    <button type="button" class="btn btn-sm btn-success" id="amz-lmp-cvr-apply-push">Apply &amp; push S PRC</button>
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
            const minDrop = Math.max(0, Number(cfg.min_drop) || 0);
            const drop = cvr45 > 0 ? cvr45 - Math.max(0, cvr) : 0;
            const decreasing = cvr45 > 0 && drop > minDrop;
            let disc = 0;
            if (cfg.enabled && lmp > 0 && decreasing) {
                const extra = (Number(cfg.base) || 0) + (Number(cfg.step) || 0) * (drop - minDrop);
                disc = Math.round(Math.min(Number(cfg.max) || 0, Math.max(0, extra)) * 100) / 100;
                if (typeof amzStdScaleDisc === 'function') disc = Number(amzStdScaleDisc(d && d.STANDARD_PRICE, disc)) || 0;
            }
            return { lmp: lmp, cvr: cvr, cvr45: cvr45, drop: drop, decreasing: decreasing, hasLmp: lmp > 0, disc: disc };
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
        /** Count SKUs by LMP / CVR state (and discount tiers) for a rule (draft or saved; always evaluated as enabled). */
        function amzLmpCvrStats(cfg) {
            const preview = Object.assign({}, cfg || amzLmpCvrRuleSafe() || {}, { enabled: true });
            const out = { total: 0, lmp: 0, down: 0, hit: 0, tiers: {} };
            amzLmpCvrAllRows().forEach(function(d) {
                const info = amzLmpCvrInfo(d, preview);
                if (!info) return;
                out.total++;
                if (info.hasLmp) out.lmp++;
                if (info.decreasing) out.down++;
                if (info.hasLmp && info.decreasing) {
                    out.hit++;
                    const k = (Math.round(info.disc * 10) / 10).toFixed(1);
                    out.tiers[k] = (out.tiers[k] || 0) + 1;
                }
            });
            return out;
        }
        function amzLmpCvrReadDraft() {
            const num = function(id, def) {
                const v = parseFloat($(id).val());
                return isFinite(v) ? Math.min(100, Math.max(0, v)) : def;
            };
            return {
                enabled: $('#amz-lmp-cvr-enabled').is(':checked'),
                base: num('#amz-lmp-cvr-base', 0),
                step: num('#amz-lmp-cvr-step', 0),
                max: num('#amz-lmp-cvr-max', 0),
                min_drop: num('#amz-lmp-cvr-mindrop', 0)
            };
        }
        function amzLmpCvrPaintModalStats() {
            const s = amzLmpCvrStats(amzLmpCvrReadDraft());
            $('#amz-lmp-cvr-s-total').text(s.total.toLocaleString());
            $('#amz-lmp-cvr-s-lmp').text(s.lmp.toLocaleString());
            $('#amz-lmp-cvr-s-down').text(s.down.toLocaleString());
            $('#amz-lmp-cvr-s-hit').text(s.hit.toLocaleString());
            const keys = Object.keys(s.tiers).sort(function(a, b) { return parseFloat(a) - parseFloat(b); });
            $('#amz-lmp-cvr-tiers').html(keys.length
                ? keys.map(function(k) { return '<span class="badge bg-light text-dark border me-1 mb-1">' + k + '% · ' + s.tiers[k].toLocaleString() + '</span>'; }).join('')
                : '—');
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
                amzLmpCvr = { enabled: !!res.enabled, base: res.base, step: res.step, max: res.max, min_drop: res.min_drop };
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
                $('#amz-lmp-cvr-base').val(cfg.base != null ? cfg.base : 2);
                $('#amz-lmp-cvr-step').val(cfg.step != null ? cfg.step : 1);
                $('#amz-lmp-cvr-max').val(cfg.max != null ? cfg.max : 10);
                $('#amz-lmp-cvr-mindrop').val(cfg.min_drop != null ? cfg.min_drop : 0.1);
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
