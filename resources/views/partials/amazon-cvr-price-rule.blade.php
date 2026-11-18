{{-- CVR Price Rule: a saved price change % for SKUs whose CVR is down, within ±20% of, or up vs LT O CVR. Parts: buttons, modals, script. --}}
@php $amazonCvrRulePart = $amazonCvrRulePart ?? 'all'; @endphp

@if($amazonCvrRulePart === 'buttons' || $amazonCvrRulePart === 'all')
                    <button type="button" class="btn btn-sm btn-info" id="amz-cvr-rule-btn" style="font-weight:600;color:#000;"
                        title="Price change % for SKUs whose CVR L30 is down, within ±20% of, or up vs LT O CVR.">
                        <i class="fas fa-chart-line"></i> CVR Price Rule
                    </button>
@endif

@if($amazonCvrRulePart === 'modals' || $amazonCvrRulePart === 'all')
    <div class="modal fade" id="amzCvrRuleModal" tabindex="-1" aria-labelledby="amzCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amzCvrRuleModalLabel"><i class="fas fa-chart-line me-1"></i> CVR Price Rule</h5>
                        <div class="text-muted" style="font-size:12px;">CVR L30 is compared with LT O CVR (the CVR Δ column). Enter the price change % for each case: a positive % raises the price, a negative % lowers it.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm align-middle mb-2" id="amz-cvr-rule-table">
                        <thead>
                            <tr>
                                <th style="width:40%;">CVR vs LT O CVR</th>
                                <th style="width:14%;" class="text-end">SKUs</th>
                                <th style="width:28%;">Price change %</th>
                                <th style="width:18%;" class="text-end" title="SKUs whose price cut is held by the Lower cap">Capped</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-key="down">
                                <td><i class="fas fa-arrow-down" style="color:#a00211;font-size:12px;"></i> <strong>CVR Down</strong><div class="text-muted" style="font-size:11px;">more than 20% below LT O CVR</div></td>
                                <td class="text-end text-muted amz-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped">—</td>
                            </tr>
                            <tr data-key="same">
                                <td><strong>--</strong> <strong>CVR within ±20%</strong><div class="text-muted" style="font-size:11px;">between 20% below and 20% above LT O CVR</div></td>
                                <td class="text-end text-muted amz-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped">—</td>
                            </tr>
                            <tr data-key="up">
                                <td><i class="fas fa-arrow-up" style="color:#28a745;font-size:12px;"></i> <strong>CVR Up</strong><div class="text-muted" style="font-size:11px;">more than 20% above LT O CVR</div></td>
                                <td class="text-end text-muted amz-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped">—</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="border rounded p-2 mb-2" id="amz-cvr-cap-box">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="amz-cvr-cap-enabled">
                            <label class="form-check-label fw-semibold" for="amz-cvr-cap-enabled">Lower cap</label>
                        </div>
                        <div class="text-muted mb-2" style="font-size:12px;">A price cut is held as soon as either GPFT% or GROI% would fall below its minimum. Whichever limit is hit first sets the lowest allowed price.</div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="amz-cvr-cap-gpft">Min GPFT%</label>
                                <div class="input-group input-group-sm"><input type="number" max="79" step="0.1" class="form-control" id="amz-cvr-cap-gpft" placeholder="no limit"><span class="input-group-text">%</span></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-1" for="amz-cvr-cap-groi">Min GROI%</label>
                                <div class="input-group input-group-sm"><input type="number" step="0.1" class="form-control" id="amz-cvr-cap-groi" placeholder="no limit"><span class="input-group-text">%</span></div>
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

        /** CVR L30 vs LT O CVR for a row: 'up' (> +20%), 'down' (< -20%), 'same' (within ±20%), null when LT O CVR is unknown. */
        function amzCvrRuleCaseFor(row) {
            if (!row) return null;
            const sess30 = parseFloat(row['Sess30']) || 0;
            const sess = sess30 + (parseFloat(row['sessions_l60']) || 0);
            if (sess <= 0) return null;
            const lto = (((parseFloat(row['A_L30']) || 0) + (parseFloat(row['units_ordered_l60']) || 0)) / sess) * 100;
            const cvr = sess30 === 0 ? 0 : ((parseFloat(row['A_L30']) || 0) / sess30) * 100;
            if (cvr > lto * 1.2) return 'up';
            if (cvr < lto * 0.8) return 'down';
            return 'same';
        }
        /**
         * Lowest price the Lower cap allows: the higher of the price where GPFT% = Min GPFT% and the price where GROI% = Min GROI%.
         * GPFT% = (p×0.80 − ship − LP) / p, GROI% = (p×0.80 − ship − LP) / LP. null when no limit applies to this row.
         */
        function amzCvrRuleFloorPrice(row, cfg) {
            if (!cfg || !cfg.lower_cap) return null;
            const lp = parseFloat(row && row.LP_productmaster);
            const ship = parseFloat(row && row.Ship_productmaster) || 0;
            const cost = (isFinite(lp) && lp > 0) ? lp : 0;
            let floor = null;
            const g = parseFloat(cfg.min_gpft);
            if (isFinite(g) && cfg.min_gpft !== null && cfg.min_gpft !== '') {
                const k = 0.80 - g / 100;
                const f = k > 0 ? (ship + cost) / k : Infinity;
                floor = floor === null ? f : Math.max(floor, f);
            }
            const r = parseFloat(cfg.min_groi);
            if (isFinite(r) && cfg.min_groi !== null && cfg.min_groi !== '' && cost > 0) {
                const f = (cost * (1 + r / 100) + ship) / 0.80;
                floor = floor === null ? f : Math.max(floor, f);
            }
            return floor;
        }
        /**
         * Price after the CVR Price Rule % for a row, with the Lower cap applied to cuts. A cut never goes below the cap price;
         * a SKU already under the cap price is left where it is (the cap never raises a price).
         * Returns { price, capped } or null when the row has no price or no LT O CVR.
         */
        function amzCvrRulePriceFor(row, cfg) {
            cfg = cfg || amzCvrRule || {};
            const c = amzCvrRuleCaseFor(row);
            const price = parseFloat(row && row.price);
            if (!c || !(price > 0)) return null;
            const pct = parseFloat(cfg[c]);
            if (!isFinite(pct) || cfg[c] === null || cfg[c] === '') return { price: price, capped: false };
            let next = price * (1 + pct / 100);
            let capped = false;
            if (next < price) {
                const floor = amzCvrRuleFloorPrice(row, cfg);
                if (floor !== null && next < floor) {
                    next = Math.min(floor, price);
                    capped = true;
                }
            }
            return { price: Math.round(next * 100) / 100, capped: capped };
        }
        function amzCvrRuleReadDraft() {
            const d = { lower_cap: $('#amz-cvr-cap-enabled').is(':checked') ? 1 : 0, min_gpft: $('#amz-cvr-cap-gpft').val(), min_groi: $('#amz-cvr-cap-groi').val() };
            $('#amz-cvr-rule-table tbody tr').each(function() {
                d[$(this).data('key')] = $(this).find('.amz-cvr-rule-pct').val();
            });
            return d;
        }
        function amzCvrRulePaintCounts() {
            const cfg = amzCvrRuleReadDraft();
            const rows = (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
            const counts = { down: 0, same: 0, up: 0 };
            const capped = { down: 0, same: 0, up: 0 };
            rows.forEach(function(r) {
                if (!r || r.is_parent_summary) return;
                const sku = String(r['(Child) sku'] || '').trim().toUpperCase();
                if (sku.indexOf('PARENT ') === 0 || sku === 'PARENT') return;
                const c = amzCvrRuleCaseFor(r);
                if (!c) return;
                counts[c]++;
                const res = amzCvrRulePriceFor(r, cfg);
                if (res && res.capped) capped[c]++;
            });
            $('#amz-cvr-rule-table tbody tr').each(function() {
                const k = $(this).data('key');
                $(this).find('.amz-cvr-rule-count').text(counts[k].toLocaleString());
                $(this).find('.amz-cvr-rule-capped').text(cfg.lower_cap ? capped[k].toLocaleString() : '—');
            });
            $('#amz-cvr-cap-gpft, #amz-cvr-cap-groi').prop('disabled', !cfg.lower_cap);
        }
        function amzCvrRulePaintModal() {
            const cfg = amzCvrRule || {};
            $('#amz-cvr-rule-table tbody tr').each(function() {
                const key = $(this).data('key');
                const v = cfg[key];
                $(this).find('.amz-cvr-rule-pct').val(v === null || v === undefined || v === '' ? '' : v);
            });
            $('#amz-cvr-cap-enabled').prop('checked', !!cfg.lower_cap);
            $('#amz-cvr-cap-gpft').val(cfg.min_gpft === null || cfg.min_gpft === undefined ? '' : cfg.min_gpft);
            $('#amz-cvr-cap-groi').val(cfg.min_groi === null || cfg.min_groi === undefined ? '' : cfg.min_groi);
            amzCvrRulePaintCounts();
            $('#amz-cvr-rule-note').text('SKU counts are the child SKUs loaded in the table. A SKU with no LT O CVR (no sessions) is not counted. Capped = SKUs whose price cut would be held by the Lower cap (based on each SKU\'s current price).');
        }

        $(document).ready(function() {
            $('#amz-cvr-rule-btn').off('click.amzcvr').on('click.amzcvr', function(e) {
                e.preventDefault();
                amzCvrRulePaintModal();
                const el = document.getElementById('amzCvrRuleModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $(document).off('input.amzcvrcount change.amzcvrcount').on('input.amzcvrcount change.amzcvrcount', '#amz-cvr-rule-table input, #amz-cvr-cap-box input', amzCvrRulePaintCounts);
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
                    amzCvrRule = { down: res.down, same: res.same, up: res.up, lower_cap: !!res.lower_cap, min_gpft: res.min_gpft, min_groi: res.min_groi };
                    amzPefToast('success', 'CVR Price Rule saved.');
                    const el = document.getElementById('amzCvrRuleModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                }).fail(function() {
                    amzPefToast('error', 'Could not save CVR Price Rule');
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
