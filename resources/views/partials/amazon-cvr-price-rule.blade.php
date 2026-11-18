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
    <div class="modal fade" id="amzCvrRuleModal" tabindex="-1" aria-labelledby="amzCvrRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amzCvrRuleModalLabel"><i class="fas fa-chart-line me-1"></i> CVR Price Rule</h5>
                        <div class="text-muted" style="font-size:12px;">CVR L30 is compared with LT O CVR (the CVR Δ column). Set the targets, then the price change % for each case: a positive % raises the price, a negative % lowers it.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-3" id="amz-cvr-onoff-box">
                        <div>
                            <div class="fw-semibold">Rule <span id="amz-cvr-onoff-txt" class="badge bg-secondary">OFF</span></div>
                            <div class="text-muted" style="font-size:12px;">ON: the S PRC column suggests a new price next to its own value, from the price change % of the SKU's CVR case. OFF: no suggestion. Click Save to apply.</div>
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
                                <th style="width:40%;">CVR vs LT O CVR</th>
                                <th style="width:14%;" class="text-end">SKUs</th>
                                <th style="width:28%;">Price change %</th>
                                <th style="width:18%;" class="text-end" title="SKUs whose price cut is held by the Lower cap">Capped</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr data-key="down">
                                <td><i class="fas fa-arrow-down" style="color:#a00211;font-size:12px;"></i> <strong>CVR Down</strong><div class="text-muted" style="font-size:11px;">more than <span class="amz-cvr-down-lbl">20</span>% below LT O CVR</div></td>
                                <td class="text-end text-muted amz-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped">—</td>
                            </tr>
                            <tr data-key="same">
                                <td><strong>--</strong> <strong>CVR within target</strong><div class="text-muted" style="font-size:11px;">between <span class="amz-cvr-down-lbl">20</span>% below and <span class="amz-cvr-up-lbl">20</span>% above LT O CVR</div></td>
                                <td class="text-end text-muted amz-cvr-rule-count">—</td>
                                <td><div class="input-group input-group-sm"><input type="number" min="-100" max="100" step="0.1" class="form-control amz-cvr-rule-pct" placeholder="0"><span class="input-group-text">%</span></div></td>
                                <td class="text-end text-muted amz-cvr-rule-capped">—</td>
                            </tr>
                            <tr data-key="up">
                                <td><i class="fas fa-arrow-up" style="color:#28a745;font-size:12px;"></i> <strong>CVR Up</strong><div class="text-muted" style="font-size:11px;">more than <span class="amz-cvr-up-lbl">20</span>% above LT O CVR</div></td>
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
                        <div class="text-muted mb-2" style="font-size:12px;">A price cut is held as soon as either NPFT% or NROI% would fall below its minimum. Whichever limit is hit first sets the lowest allowed price.</div>
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
                    <div class="text-muted" style="font-size:12px;" id="amz-cvr-rule-note"></div>
                    <div class="border-top pt-2 mt-2" id="amz-cvr-rule-switches">
                        <div class="fw-semibold mb-1" style="font-size:13px;">S PRC rule ON / OFF</div>
                        <div class="text-muted mb-1" style="font-size:12px;">Only the rule that is ON fills S PRC. Turning one ON turns the other OFF. The Sprc Dil and Std prc vs dil buttons stay in the toolbar.</div>
                        @include('partials.sprice-rule-switch', ['spriceRuleSwitchPart' => 'buttons', 'spriceRuleChannel' => 'amazon'])
                    </div>
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
        /** CVR (L30) vs LT O CVR for a row: 1 up, -1 down, 0 within the targets, null when LT O CVR is unknown. */
        function amzCvrRuleTrendValue(row, cfg) {
            if (!row) return null;
            const sess30 = parseFloat(row['Sess30']) || 0;
            const sess = sess30 + (parseFloat(row['sessions_l60']) || 0);
            if (sess <= 0) return null;
            const lto = (((parseFloat(row['A_L30']) || 0) + (parseFloat(row['units_ordered_l60']) || 0)) / sess) * 100;
            const cvr = sess30 === 0 ? 0 : ((parseFloat(row['A_L30']) || 0) / sess30) * 100;
            const b = amzCvrRuleBands(cfg);
            if (cvr > lto * (1 + b.up)) return 1;
            if (cvr < lto * (1 - b.down)) return -1;
            return 0;
        }
        /** Same as amzCvrRuleTrendValue but as 'up' / 'down' / 'same'. */
        function amzCvrRuleCaseFor(row, cfg) {
            const t = amzCvrRuleTrendValue(row, cfg);
            return t === null ? null : (t > 0 ? 'up' : (t < 0 ? 'down' : 'same'));
        }
        /** Keep the band wording in the modal and the CVR Δ filter in step with the targets. */
        function amzCvrRuleRefreshLabels(cfg) {
            const p = amzCvrRuleBandPcts(cfg);
            $('.amz-cvr-down-lbl').text(p.down);
            $('.amz-cvr-up-lbl').text(p.up);
            const $f = $('#cvr-delta-filter');
            $f.find('option[value="up"]').text('\u2191 Up (> +' + p.up + '%)');
            $f.find('option[value="same"]').text('-- Within target');
            $f.find('option[value="down"]').text('\u2193 Down (< -' + p.down + '%)');
            $f.attr('title', 'CVR L30 vs LT O CVR (down below -' + p.down + '%, up above +' + p.up + '%)');
        }
        /** Ads % used for the cap maths: the channel Ads% (the slab price-rule, when ON, is handled in amzDynAdsSolve). */
        function amzCvrRuleChannelAds() {
            if (typeof amzDynAdsChannelPct === 'function') return Number(amzDynAdsChannelPct()) || 0;
            try { return parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0; } catch (e) { return 0; }
        }
        /**
         * Lowest price the Lower cap allows: the higher of the price where NPFT% = Min NPFT% and the price where NROI% = Min NROI%.
         * Same formulas as the NPFT% / NROI% columns, with the Ads % that applies at that price:
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
                return (typeof amzDynAdsSolve === 'function') ? amzDynAdsSolve(solveAt, chAds) : solveAt(chAds);
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
         * Price after the CVR Price Rule % for a row, with the Lower cap applied to cuts. A cut never goes below the cap price;
         * a SKU already under the cap price is left where it is (the cap never raises a price).
         * Returns { price, capped } or null when the row has no price or no LT O CVR.
         */
        function amzCvrRulePriceFor(row, cfg) {
            cfg = cfg || amzCvrRule || {};
            const c = amzCvrRuleCaseFor(row, cfg);
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
         * The suggested new price for the S PRC cell, or '' when the rule is OFF, the SKU has no case / % / price, or the
         * suggestion equals the current price. Display only: nothing is saved or pushed from it.
         */
        function amzCvrRuleSuggestHtml(row) {
            if (!amzCvrRule || !amzCvrRule.enabled || !row || row.is_parent_summary) return '';
            const c = amzCvrRuleCaseFor(row, amzCvrRule);
            if (!c) return '';
            const pct = parseFloat(amzCvrRule[c]);
            if (!isFinite(pct) || pct === 0 || amzCvrRule[c] === null || amzCvrRule[c] === '') return '';
            const res = amzCvrRulePriceFor(row, amzCvrRule);
            const price = parseFloat(row.price);
            if (!res || !(res.price > 0) || Math.abs(res.price - price) < 0.005) return '';
            const word = c === 'down' ? 'CVR Down' : (c === 'up' ? 'CVR Up' : 'CVR within target');
            const tip = 'CVR Price Rule (' + word + '): Price $' + price.toFixed(2) + ' ' + (pct > 0 ? '+' : '') + pct + '% = $' + res.price.toFixed(2)
                + (res.capped ? ' — the cut is held by the Lower cap' : '') + '. Suggestion only, not saved or pushed.';
            const color = res.price < price ? '#a00211' : '#198754';
            return ' <span class="amz-cvr-suggest" title="' + String(tip).replace(/"/g, '&quot;') + '" style="white-space:nowrap;font-weight:600;color:' + color + ';">'
                + '<i class="fas fa-' + (res.price < price ? 'arrow-down' : 'arrow-up') + '" style="font-size:10px;"></i> $' + res.price.toFixed(2)
                + (res.capped ? ' <i class="fas fa-lock" style="font-size:9px;"></i>' : '') + '</span>';
        }
        /** Wraps the S PRC cell formatter: its own output stays, the rule's suggestion is added after it. */
        function amzCvrRuleSpriceFormatter(inner) {
            return function(cell) {
                const out = inner.apply(this, arguments);
                let sug = '';
                try { sug = amzCvrRuleSuggestHtml(cell.getRow().getData()); } catch (e) { sug = ''; }
                if (!sug) return out;
                return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:2px;flex-wrap:wrap;">' + (out || '') + sug + '</span>';
            };
        }
        function amzCvrRuleReadDraft() {
            const d = { enabled: $('#amz-cvr-rule-enabled').is(':checked') ? 1 : 0, lower_cap: $('#amz-cvr-cap-enabled').is(':checked') ? 1 : 0, min_npft: $('#amz-cvr-cap-npft').val(), min_nroi: $('#amz-cvr-cap-nroi').val(), down_band: $('#amz-cvr-band-down').val(), up_band: $('#amz-cvr-band-up').val() };
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
        }
        function amzCvrRulePaintCounts() {
            const cfg = amzCvrRuleReadDraft();
            amzCvrRuleRefreshLabelsModalOnly(cfg);
            const rows = (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
            const counts = { down: 0, same: 0, up: 0 };
            const capped = { down: 0, same: 0, up: 0 };
            rows.forEach(function(r) {
                if (!r || r.is_parent_summary) return;
                const sku = String(r['(Child) sku'] || '').trim().toUpperCase();
                if (sku.indexOf('PARENT ') === 0 || sku === 'PARENT') return;
                const c = amzCvrRuleCaseFor(r, cfg);
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
            $('#amz-cvr-cap-npft, #amz-cvr-cap-nroi').prop('disabled', !cfg.lower_cap);
        }
        function amzCvrRulePaintModal() {
            const cfg = amzCvrRule || {};
            $('#amz-cvr-rule-table tbody tr').each(function() {
                const key = $(this).data('key');
                const v = cfg[key];
                $(this).find('.amz-cvr-rule-pct').val(v === null || v === undefined || v === '' ? '' : v);
            });
            $('#amz-cvr-rule-enabled').prop('checked', !!cfg.enabled);
            amzCvrRulePaintOnOffText();
            $('#amz-cvr-band-down').val(amzCvrRuleBandPcts(cfg).down);
            $('#amz-cvr-band-up').val(amzCvrRuleBandPcts(cfg).up);
            $('#amz-cvr-cap-enabled').prop('checked', !!cfg.lower_cap);
            $('#amz-cvr-cap-npft').val(cfg.min_npft === null || cfg.min_npft === undefined ? '' : cfg.min_npft);
            $('#amz-cvr-cap-nroi').val(cfg.min_nroi === null || cfg.min_nroi === undefined ? '' : cfg.min_nroi);
            amzCvrRulePaintCounts();
            $('#amz-cvr-rule-note').text('SKU counts are the child SKUs loaded in the table. A SKU with no LT O CVR (no sessions) is not counted. Capped = SKUs whose price cut would be held by the Lower cap (based on each SKU\'s current price).');
        }

        $(document).ready(function() {
            amzCvrRuleRefreshLabels(amzCvrRule);
            amzCvrRulePaintState();
            $('#amz-cvr-rule-btn').off('click.amzcvr').on('click.amzcvr', function(e) {
                e.preventDefault();
                amzCvrRulePaintModal();
                const el = document.getElementById('amzCvrRuleModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $(document).off('input.amzcvrcount change.amzcvrcount').on('input.amzcvrcount change.amzcvrcount', '#amz-cvr-rule-table input, #amz-cvr-cap-box input, #amz-cvr-band-box input', amzCvrRulePaintCounts);
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
                    amzCvrRule = { enabled: !!res.enabled, down: res.down, same: res.same, up: res.up, lower_cap: !!res.lower_cap, min_npft: res.min_npft, min_nroi: res.min_nroi, down_band: res.down_band, up_band: res.up_band };
                    amzCvrRuleRefreshLabels(amzCvrRule);
                    amzCvrRulePaintState();
                    if (typeof amzTableRedrawPreserveScroll === 'function') amzTableRedrawPreserveScroll(true);
                    else if (typeof table !== 'undefined' && table) table.redraw(true);
                    if ($('#cvr-delta-filter').val() !== 'all') $('#cvr-delta-filter').trigger('change');
                    amzPefToast('success', amzCvrRule.enabled ? 'CVR Price Rule saved and ON. S PRC now shows the suggested prices.' : 'CVR Price Rule saved (OFF). No price suggestions.');
                    const el = document.getElementById('amzCvrRuleModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                }).fail(function() {
                    amzPefToast('error', 'Could not save CVR Price Rule');
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
