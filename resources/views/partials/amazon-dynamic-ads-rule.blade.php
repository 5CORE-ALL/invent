{{-- Dynamic Ads % rule: Ads % by price slab. Parts: buttons, modals, script. --}}
@php $amazonDynAdsPart = $amazonDynAdsPart ?? 'all'; @endphp

@if($amazonDynAdsPart === 'buttons' || $amazonDynAdsPart === 'all')
                    <button type="button" class="btn btn-sm btn-info" id="amz-dyn-ads-btn" style="font-weight:600;color:#000;"
                        title="Ads % by price slab. Each SKU's Ads % comes from the slab its price falls in.">
                        <i class="fas fa-sliders-h"></i> Dynamic Rule
                        <span class="badge bg-dark ms-1" id="amz-dyn-ads-state" style="display:none;">ON</span>
                    </button>
@endif

@if($amazonDynAdsPart === 'modals' || $amazonDynAdsPart === 'all')
    <div class="modal fade" id="amzDynAdsModal" tabindex="-1" aria-labelledby="amzDynAdsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0" id="amzDynAdsModalLabel"><i class="fas fa-sliders-h me-1"></i> Dynamic Rule — Ads % by price</h5>
                        <div class="text-muted" style="font-size:12px;">A SKU whose price is From $ or more and below To $ gets that slab's Ads %. Leave To $ blank for no upper limit. Prices outside every slab use the channel Ads %.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="amz-dyn-ads-enabled">
                        <label class="form-check-label fw-semibold" for="amz-dyn-ads-enabled">Use these slabs for the Ads % column (also NPFT%, SNPFT%, NROI% and SNROI%)</label>
                    </div>
                    <table class="table table-sm align-middle mb-2" id="amz-dyn-ads-table">
                        <thead>
                            <tr>
                                <th style="width:28%;">From $</th>
                                <th style="width:28%;">To $</th>
                                <th style="width:22%;">Ads %</th>
                                <th style="width:14%;" class="text-end">SKUs</th>
                                <th style="width:8%;"></th>
                            </tr>
                        </thead>
                        <tbody id="amz-dyn-ads-tbody"></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="amz-dyn-ads-add"><i class="fas fa-plus"></i> Add slab</button>
                    <div class="text-muted mt-3" style="font-size:12px;" id="amz-dyn-ads-note"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amz-dyn-ads-apply">Apply</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amazonDynAdsPart === 'script' || $amazonDynAdsPart === 'all')
        // ---- Dynamic Rule: Ads % by price slab ----
        let amzDynAds = @json(\App\Http\Controllers\MarketPlace\OverallAmazonController::amazonDynamicAdsRuleConfig());

        /** The saved rule, or null while the page script is still starting up. */
        function amzDynAdsRuleSafe() {
            try { return amzDynAds || null; } catch (e) { return null; }
        }
        function amzDynAdsChannelPct() {
            if (typeof amzAmazonAdsPct === 'function') return Number(amzAmazonAdsPct()) || 0;
            try { return parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0; } catch (e) { return 0; }
        }
        /** Slab for a price: From ≤ price < To (blank To = no limit), first match. null when none. */
        function amazonDynAdsSlabFor(price) {
            const rule = amzDynAdsRuleSafe();
            const p = parseFloat(price);
            if (!rule || !rule.enabled || !isFinite(p) || p <= 0) return null;
            const slabs = rule.slabs || [];
            for (let i = 0; i < slabs.length; i++) {
                const s = slabs[i];
                const max = (s.max === null || s.max === undefined || s.max === '') ? Infinity : parseFloat(s.max);
                if (p >= (parseFloat(s.min) || 0) && p < max) return s;
            }
            return null;
        }
        /** Ads % for a row at a price: its slab's % when the rule is on, else the channel Ads%. */
        function amazonAdsPctAtPrice(price) {
            const s = amazonDynAdsSlabFor(price);
            if (s) return parseFloat(s.pct) || 0;
            return amzDynAdsChannelPct();
        }
        function amazonRowAdsPct(rowData) {
            return amazonAdsPctAtPrice(rowData && rowData.price);
        }
        /** Price ranges that each have one Ads % (slabs, with the channel Ads % in the gaps). null when the rule is off. */
        function amzDynAdsSegments(channelPct) {
            const rule = amzDynAdsRuleSafe();
            if (!rule || !rule.enabled || !(rule.slabs || []).length) return null;
            const segs = [];
            let cursor = 0;
            for (let i = 0; i < rule.slabs.length; i++) {
                const s = rule.slabs[i];
                const min = Math.max(parseFloat(s.min) || 0, cursor);
                const max = (s.max === null || s.max === undefined || s.max === '') ? Infinity : parseFloat(s.max);
                if (!(max > min)) continue;
                if (min > cursor) segs.push({ min: cursor, max: min, pct: channelPct });
                segs.push({ min: min, max: max, pct: parseFloat(s.pct) || 0 });
                cursor = max;
                if (!isFinite(max)) break;
            }
            if (isFinite(cursor)) segs.push({ min: cursor, max: Infinity, pct: channelPct });
            return segs;
        }
        /**
         * Price that hits a target when the Ads % depends on the price. solveAt(adsPct) gives the price for one fixed
         * Ads %. Lowest price whose own slab agrees wins (same as AmazonDilGroiRule::priceWithDynamicAds on the server).
         */
        function amzDynAdsSolve(solveAt, channelPct) {
            const segs = amzDynAdsSegments(channelPct);
            if (!segs) return solveAt(channelPct);
            for (let i = 0; i < segs.length; i++) {
                let cand = Number(solveAt(segs[i].pct));
                if (!isFinite(cand) || !(cand > 0)) continue;
                if (cand < segs[i].min) cand = segs[i].min;
                if (cand < segs[i].max) return cand;
            }
            return solveAt(channelPct);
        }

        function amzDynAdsEsc(v) {
            return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }
        function amzDynAdsRowHtml(s) {
            s = s || {};
            return '<tr>'
                + '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm amz-da-min" value="' + amzDynAdsEsc(s.min != null ? s.min : '') + '"></td>'
                + '<td><input type="number" min="0" step="0.01" class="form-control form-control-sm amz-da-max" placeholder="no limit" value="' + amzDynAdsEsc(s.max != null ? s.max : '') + '"></td>'
                + '<td><div class="input-group input-group-sm"><input type="number" min="0" max="100" step="0.1" class="form-control amz-da-pct" value="' + amzDynAdsEsc(s.pct != null ? s.pct : '') + '"><span class="input-group-text">%</span></div></td>'
                + '<td class="text-end amz-da-count text-muted">—</td>'
                + '<td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger p-0 amz-da-del" title="Remove slab"><i class="fas fa-trash"></i></button></td>'
                + '</tr>';
        }
        function amzDynAdsReadDraft() {
            const out = [];
            $('#amz-dyn-ads-tbody tr').each(function() {
                const $r = $(this);
                const pct = $r.find('.amz-da-pct').val();
                if (pct === '' || isNaN(parseFloat(pct))) return;
                const minV = parseFloat($r.find('.amz-da-min').val());
                const maxRaw = $r.find('.amz-da-max').val();
                out.push({
                    min: isFinite(minV) ? Math.max(0, minV) : 0,
                    max: (maxRaw === '' || isNaN(parseFloat(maxRaw))) ? null : parseFloat(maxRaw),
                    pct: Math.min(100, Math.max(0, parseFloat(pct)))
                });
            });
            return out;
        }
        /** Count the SKUs (child rows with a price) that fall in each slab being edited. */
        function amzDynAdsPaintCounts() {
            const rows = (typeof table !== 'undefined' && table && table.getData) ? (table.getData('all') || []) : [];
            const prices = [];
            rows.forEach(function(r) {
                if (!r || r.is_parent_summary) return;
                const sku = String(r['(Child) sku'] || '').trim().toUpperCase();
                if (sku.indexOf('PARENT ') === 0 || sku === 'PARENT') return;
                const p = parseFloat(r.price);
                if (isFinite(p) && p > 0) prices.push(p);
            });
            $('#amz-dyn-ads-tbody tr').each(function() {
                const $r = $(this);
                const minV = parseFloat($r.find('.amz-da-min').val());
                const min = isFinite(minV) ? minV : 0;
                const maxRaw = parseFloat($r.find('.amz-da-max').val());
                const max = isFinite(maxRaw) ? maxRaw : Infinity;
                if (max <= min) { $r.find('.amz-da-count').text('—'); return; }
                let n = 0;
                for (let i = 0; i < prices.length; i++) if (prices[i] >= min && prices[i] < max) n++;
                $r.find('.amz-da-count').text(n.toLocaleString());
            });
            $('#amz-dyn-ads-note').text('Channel Ads% for prices outside every slab: ' + (parseFloat(AMAZON_CHANNEL_ADS_PCT) || 0).toFixed(1) + '%.');
        }
        function amzDynAdsPaintModal() {
            $('#amz-dyn-ads-enabled').prop('checked', !!(amzDynAds && amzDynAds.enabled));
            const slabs = (amzDynAds && amzDynAds.slabs && amzDynAds.slabs.length) ? amzDynAds.slabs : [{ min: 0, max: null, pct: '' }];
            $('#amz-dyn-ads-tbody').html(slabs.map(amzDynAdsRowHtml).join(''));
            amzDynAdsPaintCounts();
        }
        function amzDynAdsPaintState() {
            $('#amz-dyn-ads-state').toggle(!!(amzDynAds && amzDynAds.enabled));
        }

        $(document).ready(function() {
            amzDynAdsPaintState();
            $('#amz-dyn-ads-btn').off('click.amzda').on('click.amzda', function(e) {
                e.preventDefault();
                amzDynAdsPaintModal();
                const el = document.getElementById('amzDynAdsModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $('#amz-dyn-ads-add').off('click.amzda').on('click.amzda', function() {
                const draft = amzDynAdsReadDraft();
                const last = draft.length ? draft[draft.length - 1] : null;
                $('#amz-dyn-ads-tbody').append(amzDynAdsRowHtml({ min: last && last.max != null ? last.max : '', max: null, pct: '' }));
                amzDynAdsPaintCounts();
            });
            $(document).off('click.amzdadel').on('click.amzdadel', '#amz-dyn-ads-tbody .amz-da-del', function() {
                $(this).closest('tr').remove();
                amzDynAdsPaintCounts();
            });
            $(document).off('input.amzdacount').on('input.amzdacount', '#amz-dyn-ads-tbody input', amzDynAdsPaintCounts);
            $('#amz-dyn-ads-apply').off('click.amzda').on('click.amzda', function() {
                const $btn = $(this).prop('disabled', true);
                const payload = { enabled: $('#amz-dyn-ads-enabled').is(':checked') ? 1 : 0, slabs: amzDynAdsReadDraft() };
                $.ajax({
                    url: @json(route('amazon.dynamic-ads-rule.save')),
                    method: 'POST',
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' }
                }).done(function(res) {
                    if (!res || !res.success) { amzPefToast('error', 'Could not save Dynamic Rule'); return; }
                    amzDynAds = { enabled: !!res.enabled, slabs: res.slabs || [] };
                    amzDynAdsPaintState();
                    if (typeof amzTableRedrawPreserveScroll === 'function') amzTableRedrawPreserveScroll(true);
                    else if (typeof table !== 'undefined' && table) table.redraw(true);
                    amzPefToast('success', amzDynAds.enabled ? 'Dynamic Rule saved. Ads % now follows the price slabs.' : 'Dynamic Rule saved (off). Ads % uses the channel value.');
                    const el = document.getElementById('amzDynAdsModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
                }).fail(function() {
                    amzPefToast('error', 'Could not save Dynamic Rule');
                }).always(function() { $btn.prop('disabled', false); });
            });
        });
@endif
