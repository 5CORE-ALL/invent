{{-- Std prc vs dil: promotional % off standard price. Parts: css, buttons, modals, script. --}}
@php $amazonStdPrcPart = $amazonStdPrcPart ?? 'all'; @endphp

@if($amazonStdPrcPart === 'css' || $amazonStdPrcPart === 'all')
        .amz-age-discount-badge,
        .amz-dil-discount-badge,
        .amz-sum-discount-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            line-height: 1.2;
        }
        .amz-age-discount-badge { color: #d97706; }
        .amz-dil-discount-badge { color: #6f42c1; }
        .amz-sum-discount-badge { color: #0f172a; }
        .amz-age-discount-badge.is-zero,
        .amz-dil-discount-badge.is-zero,
        .amz-sum-discount-badge.is-zero { color: #adb5bd; font-weight: 600; }
        #amzStdPrcModal .modal-dialog {
            width: 96vw;
            max-width: 96vw;
            margin: 1vh auto;
        }
        #amzStdPrcModal .modal-body { overflow-x: auto; }
        #amzStdPrcModal .amz-sp-cols {
            display: grid;
            grid-template-columns: repeat(5, minmax(220px, 1fr));
            gap: 10px;
            align-items: start;
            min-width: 1180px;
        }
        #amzStdPrcModal .amz-sp-col {
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            background: #fff;
        }
        #amzStdPrcModal .amz-sp-pie {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 8px;
        }
        #amzStdPrcModal .amz-sp-pie-canvas {
            position: relative;
            width: 128px;
            height: 128px;
            flex: 0 0 128px;
        }
        #amzStdPrcModal .amz-sp-pie-canvas canvas {
            display: block;
            width: 128px !important;
            height: 128px !important;
        }
        #amzStdPrcModal .amz-sp-pie-legend {
            width: 100%;
            font-size: 11px;
            max-height: 92px;
            overflow: auto;
            margin-top: 4px;
        }
        #amzStdPrcModal .amz-sp-pie-title { font-weight: 700; font-size: 12px; margin-bottom: 4px; color: #334155; align-self: flex-start; }
        #amzStdPrcModal .amz-sp-leg-row { display: flex; gap: 6px; align-items: center; margin-bottom: 2px; }
        #amzStdPrcModal .amz-sp-swatch { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 8px; }
        #amzStdPrcModal .amz-sp-col .table { font-size: 11px; margin-bottom: 0; }
        #amzStdPrcModal .amz-sp-col .table th,
        #amzStdPrcModal .amz-sp-col .table td { padding: 3px 4px; }
        #amzStdPrcModal .amz-sp-col .btn { font-size: 11px; }
        #amzStdPrcModal .amz-sp-margins {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        #amzStdPrcModal .amz-sp-margin {
            flex: 1 1 240px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
            background: #f8fafc;
        }
        #amzStdPrcModal .amz-sp-margin strong { font-size: 16px; }
        #amzStdPrcModal .amz-sp-metric-grid {
            display: grid;
            grid-template-columns: 3.2rem 1fr auto;
            gap: 2px 8px;
            margin-top: 6px;
            font-size: 12px;
            align-items: baseline;
        }
        #amzStdPrcModal .amz-sp-metric { display: contents; }
        #amzStdPrcModal .amz-sp-metric .amz-sp-metric-name { color: #64748b; font-weight: 700; }
        #amzStdPrcModal .amz-sp-metric .amz-sp-metric-money { font-weight: 700; text-align: right; }
        #amzStdPrcModal .amz-sp-metric .amz-sp-metric-pct { font-weight: 700; text-align: right; }
        #amzStdPrcModal .amz-sp-section { font-weight: 700; font-size: 12px; margin: 12px 0 6px; color: #334155; }
        #amzStdPrcModal .amz-sp-input {
            width: 100%;
            max-width: 72px;
            margin-left: auto;
            text-align: right;
            font-weight: 600;
            padding: 2px 4px;
            font-size: 12px;
        }
        #amzStdPrcModal .amz-sp-count { font-weight: 700; text-align: center; }
@endif

@if($amazonStdPrcPart === 'buttons' || $amazonStdPrcPart === 'all')
                    <button type="button" class="btn btn-sm" id="amz-std-prc-btn"
                        title="Std Prc minus Age, Dil, CVR, and Review promotional discounts. S PRC = Std − Sum disc.">
                        <i class="fas fa-tags"></i> Std prc vs dil
                    </button>
@endif

@if($amazonStdPrcPart === 'modals' || $amazonStdPrcPart === 'all')
    <div class="modal fade" id="amzStdPrcModal" tabindex="-1" aria-labelledby="amzStdPrcModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzStdPrcModalLabel">
                        <i class="fas fa-tags me-1"></i> Std prc vs dil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Every price starts at <strong>Std Prc</strong>.
                        S PRC = Std Prc − (Age Disc + Dil Disc + CVR Disc + Rev Disc).
                        CVR Disc is the CVR slab plus the up/down promotional discount below.
                    </p>
                    <div class="amz-sp-margins">
                        <div class="amz-sp-margin">
                            <div class="small text-muted">Projected margin · last L30 sales</div>
                            <strong id="amz-sp-margin-l30">—</strong>
                            <div class="small text-muted" id="amz-sp-margin-l30-sub"></div>
                            <div class="amz-sp-metric-grid" id="amz-sp-margin-l30-metrics"></div>
                        </div>
                        <div class="amz-sp-margin">
                            <div class="small text-muted">Projected margin · total INV</div>
                            <strong id="amz-sp-margin-inv">—</strong>
                            <div class="small text-muted" id="amz-sp-margin-inv-sub"></div>
                            <div class="amz-sp-metric-grid" id="amz-sp-margin-inv-metrics"></div>
                        </div>
                    </div>

                    <div class="amz-sp-cols">
                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">Dil</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-dil"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-dil"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amz-sp-dil-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-dil-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="amz-sp-dil-add">Add slab</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">Reviews</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-rev"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-rev"></div>
                            </div>
                            <div class="d-flex align-items-center gap-1 mb-2">
                                <label for="amz-sp-review-max" class="small fw-semibold mb-0 text-nowrap">Max</label>
                                <input type="number" id="amz-sp-review-max" class="form-control form-control-sm amz-sp-input" min="1" step="1" value="4" title="No review discount when reviews are above this">
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-rev-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="amz-sp-rev-add">Add range</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">CVR up / down</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-cvr"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-cvr"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0" id="amz-sp-cvr-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>When</th>
                                            <th class="text-center">CVR%</th>
                                            <th class="text-end">Disc %</th>
                                            <th class="text-center">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Down</td>
                                            <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm d-inline-block amz-sp-input amz-sp-cvr-down-lt" value="7"></td>
                                            <td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-down-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-down-count">0</td>
                                        </tr>
                                        <tr>
                                            <td>Flat</td>
                                            <td class="text-center text-muted">mid</td>
                                            <td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-flat-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-flat-count">0</td>
                                        </tr>
                                        <tr>
                                            <td>Up</td>
                                            <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm d-inline-block amz-sp-input amz-sp-cvr-up-gt" value="10"></td>
                                            <td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input amz-sp-cvr-up-disc" value="0"></td>
                                            <td class="amz-sp-count" id="amz-sp-cvr-up-count">0</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">Age Days</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-age"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-age"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">From</th>
                                            <th class="text-center">To</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-end">Disc %</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-age-tbody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="amz-sp-age-add">Add range</button>
                        </div>

                        <div class="amz-sp-col">
                            <div class="amz-sp-pie">
                                <div class="amz-sp-pie-title">All discounts</div>
                                <div class="amz-sp-pie-canvas"><canvas id="amz-sp-pie-all"></canvas></div>
                                <div class="amz-sp-pie-legend" id="amz-sp-leg-all"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Discount</th>
                                            <th class="text-center">SKUs</th>
                                            <th class="text-end">Disc %</th>
                                            <th class="text-end">$ off</th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-sp-all-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" id="amz-sp-status"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-primary" id="amz-sp-apply"
                        title="Save these promotional discounts and write S PRC as Std Prc minus the sum.">
                        <i class="fas fa-save me-1"></i> Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amazonStdPrcPart === 'script' || $amazonStdPrcPart === 'all')
        const AMZ_STD_DIL_DEFAULTS = [
            { min: 0, max: 0, disc: 0 },
            { min: 0.1, max: 10, disc: 0 },
            { min: 10, max: 25, disc: 0 },
            { min: 25, max: 50, disc: 0 },
            { min: 50, max: 100, disc: 0 },
            { min: 100, max: 9999, disc: 0 },
        ];
        const AMZ_STD_AGE_DEFAULTS = [
            { min: 0, max: 30, disc: 0 },
            { min: 31, max: 60, disc: 0 },
            { min: 61, max: 90, disc: 0 },
            { min: 91, max: 180, disc: 0 },
            { min: 181, max: 365, disc: 0 },
            { min: 366, max: 9999, disc: 0 },
        ];
        const AMZ_STD_CVR_DEFAULT = { down_lt: 7, down_disc: 0, up_gt: 10, up_disc: 0, flat_disc: 0 };
        const AMZ_STD_PIE_COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b'];
        let amzStdDilRules = AMZ_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzStdAgeRules = AMZ_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let amzStdCvrCfg = Object.assign({}, AMZ_STD_CVR_DEFAULT);
        const amzStdPieCharts = {};
        let amzStdPieGen = 0;
        let amzStdApplied = false;

        function fmtAmzStdDiscBadge(pct, kind) {
            const n = Number(pct);
            const cls = kind === 'age' ? 'amz-age-discount-badge'
                : (kind === 'dil' ? 'amz-dil-discount-badge' : 'amz-sum-discount-badge');
            if (!isFinite(n) || n <= 0) {
                return '<span class="' + cls + ' is-zero">—</span>';
            }
            return '<span class="' + cls + '">' + n + '</span>';
        }
        function amzStdRangeDisc(value, rules) {
            const n = Number(value);
            if (!isFinite(n) || n < 0 || !rules || !rules.length) return 0;
            const last = rules.length - 1;
            for (let i = 0; i < rules.length; i++) {
                const rule = rules[i];
                if (!rule) continue;
                let min = Number(rule.min);
                let max = Number(rule.max);
                if (!isFinite(min) || !isFinite(max)) continue;
                if (max < min) { const swap = min; min = max; max = swap; }
                const exact = Math.abs(max - min) < 0.00001;
                const hit = exact
                    ? Math.abs(n - min) < 0.00001
                    : (n >= min && (i === last ? n <= max : n < max));
                if (!hit) continue;
                const disc = Number(rule.disc);
                return isFinite(disc) && disc > 0 ? disc : 0;
            }
            return 0;
        }
        function computeAmzAgeDiscountPct(d) {
            if (typeof amzPefIsChildRow === 'function' && !amzPefIsChildRow(d)) return null;
            if (typeof amzPefInv === 'function' && amzPefInv(d) === 0) return 0;
            if (!d || d.age_days == null || d.age_days === '') return 0;
            return amzStdRangeDisc(d.age_days, amzStdAgeRules);
        }
        function computeAmzDilDiscountPct(d) {
            if (typeof amzPefIsChildRow === 'function' && !amzPefIsChildRow(d)) return null;
            if (typeof amzPefInv === 'function' && amzPefInv(d) === 0) return 0;
            const dil = (typeof amzPefDil === 'function') ? amzPefDil(d) : 0;
            return amzStdRangeDisc(dil, amzStdDilRules);
        }
        function amzStdCvrTrendDisc(d) {
            if (!d || (typeof amzPefInv === 'function' && amzPefInv(d) === 0)) return 0;
            const cfg = amzStdCvrCfg || AMZ_STD_CVR_DEFAULT;
            const cvr = (typeof amzPefCvrL30Live === 'function') ? amzPefCvrL30Live(d) : 0;
            const trend = (typeof amzPefCvrTrend === 'function') ? amzPefCvrTrend(d) : 'flat';
            let disc = Number(cfg.flat_disc) || 0;
            if (trend === 'down' && cvr < (Number(cfg.down_lt) || 0)) disc = Number(cfg.down_disc) || 0;
            else if (trend === 'up' && cvr > (Number(cfg.up_gt) || 0)) disc = Number(cfg.up_disc) || 0;
            return isFinite(disc) && disc > 0 ? disc : 0;
        }
        function amzStdCvrBand(d, cfg) {
            const cvr = (typeof amzPefCvrL30Live === 'function') ? amzPefCvrL30Live(d) : 0;
            const trend = (typeof amzPefCvrTrend === 'function') ? amzPefCvrTrend(d) : 'flat';
            if (trend === 'down' && cvr < (Number(cfg.down_lt) || 0)) return 'down';
            if (trend === 'up' && cvr > (Number(cfg.up_gt) || 0)) return 'up';
            return 'flat';
        }
        function computeAmzSumDiscountPct(d) {
            if (typeof computeAmzRuleStack !== 'function') return 0;
            const stack = computeAmzRuleStack(d);
            return stack && isFinite(stack.totalDisc) ? stack.totalDisc : 0;
        }
        function amzStdSprice(d) {
            const std = Number(d && d.STANDARD_PRICE) || 0;
            if (!(std > 0)) return 0;
            const pct = Math.min(99.99, Math.max(0, Number(computeAmzSumDiscountPct(d)) || 0));
            return (typeof amzPefRound2 === 'function')
                ? amzPefRound2(std * (1 - (pct / 100)))
                : Math.round(std * (1 - (pct / 100)) * 100) / 100;
        }
        window.computeAmzAgeDiscountPct = computeAmzAgeDiscountPct;
        window.computeAmzDilDiscountPct = computeAmzDilDiscountPct;
        window.computeAmzSumDiscountPct = computeAmzSumDiscountPct;
        window.amzStdCvrTrendDisc = amzStdCvrTrendDisc;
        window.fmtAmzStdDiscBadge = fmtAmzStdDiscBadge;

        function amzStdNormRange(r) {
            let min = Number(r && r.min);
            let max = Number(r && r.max);
            if (!isFinite(min) || !isFinite(max)) return null;
            if (max < min) { const swap = min; min = max; max = swap; }
            let disc = Number(r && r.disc);
            if (!isFinite(disc) || disc < 0) disc = 0;
            if (disc > 100) disc = 100;
            return { min: min, max: max, disc: disc };
        }
        function amzStdNormCvr(raw) {
            const out = Object.assign({}, AMZ_STD_CVR_DEFAULT);
            if (!raw) return out;
            ['down_lt', 'up_gt', 'down_disc', 'up_disc', 'flat_disc'].forEach(function(key) {
                const n = Number(raw[key]);
                if (!isFinite(n) || n < 0) return;
                out[key] = n > 100 && key.indexOf('disc') !== -1 ? 100 : n;
            });
            return out;
        }
        function amzStdEachInvChild(fn) {
            if (typeof amzDgEachInvChild === 'function') {
                amzDgEachInvChild(fn);
                return;
            }
            const rows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData : [];
            rows.forEach(function(d) {
                if (typeof amzPefIsChildRow === 'function' && amzPefIsChildRow(d) && amzPefInv(d) > 0) fn(d);
            });
        }
        function amzStdReadRanges(tbodySel, minCls, maxCls, discCls) {
            const rules = [];
            $(tbodySel).find('tr').each(function() {
                const rule = amzStdNormRange({
                    min: $(this).find(minCls).val(),
                    max: $(this).find(maxCls).val(),
                    disc: $(this).find(discCls).val(),
                });
                if (rule) rules.push(rule);
            });
            return rules;
        }
        function amzStdReadDraft() {
            const cvr = amzStdNormCvr({
                down_lt: $('#amzStdPrcModal .amz-sp-cvr-down-lt').val(),
                down_disc: $('#amzStdPrcModal .amz-sp-cvr-down-disc').val(),
                up_gt: $('#amzStdPrcModal .amz-sp-cvr-up-gt').val(),
                up_disc: $('#amzStdPrcModal .amz-sp-cvr-up-disc').val(),
                flat_disc: $('#amzStdPrcModal .amz-sp-cvr-flat-disc').val(),
            });
            const reviews = amzStdReadRanges('#amz-sp-rev-tbody', '.amz-sp-rev-min', '.amz-sp-rev-max', '.amz-sp-rev-disc');
            let reviewMax = parseInt($('#amz-sp-review-max').val(), 10);
            if (!isFinite(reviewMax) || reviewMax < 1) reviewMax = 4;
            return {
                dil: amzStdReadRanges('#amz-sp-dil-tbody', '.amz-sp-dil-min', '.amz-sp-dil-max', '.amz-sp-dil-disc'),
                age: amzStdReadRanges('#amz-sp-age-tbody', '.amz-sp-age-min', '.amz-sp-age-max', '.amz-sp-age-disc'),
                cvr: cvr,
                reviews: reviews,
                reviewMax: reviewMax,
            };
        }
        function amzStdRangeRow(prefix, rule) {
            const min = rule.min;
            const max = rule.max;
            const disc = rule.disc;
            return '<tr>'
                + '<td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm amz-sp-input ' + prefix + '-min" value="' + min + '"></td>'
                + '<td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm amz-sp-input ' + prefix + '-max" value="' + max + '"></td>'
                + '<td class="amz-sp-count ' + prefix + '-count">0</td>'
                + '<td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm amz-sp-input ' + prefix + '-disc" value="' + disc + '"></td>'
                + '<td><button type="button" class="btn btn-sm text-danger ' + prefix + '-del" title="Remove">&times;</button></td>'
                + '</tr>';
        }
        function amzStdPaintRanges(tbody, prefix, rules) {
            const html = (rules || []).map(function(r) { return amzStdRangeRow(prefix, r); }).join('');
            $(tbody).html(html);
        }
        function amzStdPaintCvr(cfg) {
            cfg = amzStdNormCvr(cfg);
            $('#amzStdPrcModal .amz-sp-cvr-down-lt').val(cfg.down_lt);
            $('#amzStdPrcModal .amz-sp-cvr-down-disc').val(cfg.down_disc);
            $('#amzStdPrcModal .amz-sp-cvr-up-gt').val(cfg.up_gt);
            $('#amzStdPrcModal .amz-sp-cvr-up-disc').val(cfg.up_disc);
            $('#amzStdPrcModal .amz-sp-cvr-flat-disc').val(cfg.flat_disc);
        }
        function amzStdPaintModal() {
            amzStdPaintRanges('#amz-sp-dil-tbody', 'amz-sp-dil', amzStdDilRules);
            amzStdPaintRanges('#amz-sp-age-tbody', 'amz-sp-age', amzStdAgeRules);
            const reviews = (typeof amzReviewDiscRules !== 'undefined' && amzReviewDiscRules.length)
                ? amzReviewDiscRules
                : [{ min: 1, max: 2, disc: 4 }, { min: 2, max: 3, disc: 4 }];
            amzStdPaintRanges('#amz-sp-rev-tbody', 'amz-sp-rev', reviews);
            const maxRev = (typeof amzReviewDiscMax !== 'undefined') ? amzReviewDiscMax : 4;
            $('#amz-sp-review-max').val(maxRev);
            amzStdPaintCvr(amzStdCvrCfg);
            amzStdRefreshModal();
        }
        function amzStdMoney(n) {
            const v = Number(n) || 0;
            const sign = v < 0 ? '-' : '';
            return sign + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        function amzStdRowMargin(d) {
            const marginRaw = parseFloat(d && d.percentage);
            return (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.80;
        }
        function amzStdUnitProfit(d, sprice) {
            const lp = parseFloat(d && d.LP_productmaster) || 0;
            const ship = parseFloat(d && d.Ship_productmaster) || 0;
            return (sprice * amzStdRowMargin(d)) - ship - lp;
        }
        function amzStdEmptyBucket() {
            return { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
        }
        function amzStdMetricHtml(bucket) {
            const sales = bucket.sales;
            const cogs = bucket.cogs;
            const rows = [
                ['GROI', bucket.gross, cogs > 0 ? (bucket.gross / cogs) * 100 : 0, 'groi'],
                ['GPFT', bucket.gross, sales > 0 ? (bucket.gross / sales) * 100 : 0, 'gpft'],
                ['NROI', bucket.net, cogs > 0 ? (bucket.net / cogs) * 100 : 0, 'nroi'],
                ['NPFT', bucket.net, sales > 0 ? (bucket.net / sales) * 100 : 0, 'npft'],
            ];
            return rows.map(function(row) {
                const moneyColor = row[1] < 0 ? '#dc3545' : '#166534';
                let pctHtml = Math.round(row[2]) + '%';
                if (window.MetricPctColors && typeof MetricPctColors.htmlFor === 'function') {
                    const painted = MetricPctColors.htmlFor(row[3], row[2], { decimals: 0, empty: '—' });
                    if (painted) pctHtml = painted;
                }
                return '<div class="amz-sp-metric">'
                    + '<span class="amz-sp-metric-name">' + row[0] + '</span>'
                    + '<span class="amz-sp-metric-money" style="color:' + moneyColor + '">' + amzStdMoney(row[1]) + '</span>'
                    + '<span class="amz-sp-metric-pct">' + pctHtml + '</span>'
                    + '</div>';
            }).join('');
        }
        function amzStdLegend(el, slices, counts) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const html = slices.map(function(s) {
                const n = counts[s.key] || 0;
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                return '<div class="amz-sp-leg-row"><span class="amz-sp-swatch" style="background:' + s.color + '"></span>'
                    + '<span style="flex:1">' + s.label + '</span><strong>' + n + '</strong><span class="text-muted">' + pct + '%</span></div>';
            }).join('');
            $(el).html(html);
        }
        function amzStdDrawPieNow(id, slices, counts) {
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : amzStdPieCharts[id];
            if (bound) bound.destroy();
            amzStdPieCharts[id] = null;
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const data = slices.map(function(s) { return counts[s.key] || 0; });
            amzStdPieCharts[id] = new Chart(canvas.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: slices.map(function(s) { return s.label; }),
                    datasets: [{
                        data: total > 0 ? data : slices.map(function() { return 1; }),
                        backgroundColor: total > 0
                            ? slices.map(function(s) { return s.color; })
                            : slices.map(function() { return '#e2e8f0'; }),
                        borderColor: '#fff',
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: { legend: { display: false } },
                },
            });
        }
        function amzStdDrawPies(jobs) {
            const gen = ++amzStdPieGen;
            const draw = function() {
                if (gen !== amzStdPieGen) return;
                jobs.forEach(function(job) {
                    amzStdDrawPieNow(job.id, job.slices, job.counts);
                });
            };
            if (typeof window.loadChartJs === 'function') {
                window.loadChartJs().then(draw).catch(function() {});
                return;
            }
            draw();
        }
        function amzStdRefreshModal() {
            const draft = amzStdReadDraft();
            const dilCounts = {};
            const ageCounts = {};
            const revCounts = {};
            const cvrCounts = { down: 0, flat: 0, up: 0 };
            draft.dil.forEach(function(r, i) { dilCounts['d' + i] = 0; });
            dilCounts.outside = 0;
            draft.age.forEach(function(r, i) { ageCounts['a' + i] = 0; });
            ageCounts.none = 0;
            draft.reviews.forEach(function(r, i) { revCounts['r' + i] = 0; });
            revCounts.none = 0;
            const dollars = { age: 0, dil: 0, cvr: 0, rev: 0 };
            const pctTotals = { age: 0, dil: 0, cvr: 0, rev: 0 };
            const skuHits = { age: 0, dil: 0, cvr: 0, rev: 0, all: 0 };
            const l30 = amzStdEmptyBucket();
            const inv = amzStdEmptyBucket();
            const adsPct = (typeof amzAmazonAdsPct === 'function') ? amzAmazonAdsPct() : 0;
            amzStdEachInvChild(function(d) {
                const std = Number(d.STANDARD_PRICE) || 0;
                const dilVal = (typeof amzPefDil === 'function') ? amzPefDil(d) : 0;
                let dilIdx = -1;
                for (let i = 0; i < draft.dil.length; i++) {
                    if (amzStdInRange(dilVal, draft.dil[i], i === draft.dil.length - 1)) { dilIdx = i; break; }
                }
                if (dilIdx >= 0) dilCounts['d' + dilIdx] += 1;
                else dilCounts.outside += 1;
                const ageVal = (d.age_days == null || d.age_days === '') ? null : Number(d.age_days);
                let ageIdx = -1;
                if (ageVal != null && isFinite(ageVal)) {
                    for (let i = 0; i < draft.age.length; i++) {
                        if (amzStdInRange(ageVal, draft.age[i], i === draft.age.length - 1)) { ageIdx = i; break; }
                    }
                }
                if (ageIdx >= 0) ageCounts['a' + ageIdx] += 1;
                else ageCounts.none += 1;
                const reviews = (typeof amzPefReviewCount === 'function') ? amzPefReviewCount(d) : 0;
                let revIdx = -1;
                if (reviews > 0 && reviews <= draft.reviewMax) {
                    for (let i = 0; i < draft.reviews.length; i++) {
                        const rule = draft.reviews[i];
                        if (reviews >= rule.min && reviews <= rule.max) { revIdx = i; break; }
                    }
                }
                if (revIdx >= 0) revCounts['r' + revIdx] += 1;
                else revCounts.none += 1;
                const band = amzStdCvrBand(d, draft.cvr);
                cvrCounts[band] = (cvrCounts[band] || 0) + 1;

                const ageDisc = ageIdx >= 0 ? (Number(draft.age[ageIdx].disc) || 0) : 0;
                const dilDisc = dilIdx >= 0 ? (Number(draft.dil[dilIdx].disc) || 0) : 0;
                const revDisc = revIdx >= 0 ? (Number(draft.reviews[revIdx].disc) || 0) : 0;
                const slab = (typeof amzDiscForCvr === 'function' && typeof amzPefCvr === 'function') ? (amzDiscForCvr(amzPefCvr(d)) || 0) : 0;
                let trend = Number(draft.cvr.flat_disc) || 0;
                const cvrLive = (typeof amzPefCvrL30Live === 'function') ? amzPefCvrL30Live(d) : 0;
                if (band === 'down' && cvrLive < draft.cvr.down_lt) trend = Number(draft.cvr.down_disc) || 0;
                else if (band === 'up' && cvrLive > draft.cvr.up_gt) trend = Number(draft.cvr.up_disc) || 0;
                const cvrDisc = Math.max(0, slab + (trend > 0 ? trend : 0));
                if (ageDisc > 0) { skuHits.age++; dollars.age += std * ageDisc / 100; pctTotals.age += ageDisc; }
                if (dilDisc > 0) { skuHits.dil++; dollars.dil += std * dilDisc / 100; pctTotals.dil += dilDisc; }
                if (cvrDisc > 0) { skuHits.cvr++; dollars.cvr += std * cvrDisc / 100; pctTotals.cvr += cvrDisc; }
                if (revDisc > 0) { skuHits.rev++; dollars.rev += std * revDisc / 100; pctTotals.rev += revDisc; }
                const sum = Math.min(99.99, ageDisc + dilDisc + cvrDisc + revDisc);
                if (sum > 0) skuHits.all++;
                if (std > 0) {
                    const sprice = Math.round(std * (1 - (sum / 100)) * 100) / 100;
                    const profit = amzStdUnitProfit(d, sprice);
                    const net = profit - (sprice * adsPct / 100);
                    const lp = parseFloat(d.LP_productmaster) || 0;
                    const al30 = (typeof amzPefAL30 === 'function') ? amzPefAL30(d) : 0;
                    const onHand = (typeof amzPefInv === 'function') ? amzPefInv(d) : 0;
                    l30.gross += profit * al30;
                    l30.net += net * al30;
                    l30.sales += sprice * al30;
                    l30.cogs += lp * al30;
                    l30.units += al30;
                    inv.gross += profit * onHand;
                    inv.net += net * onHand;
                    inv.sales += sprice * onHand;
                    inv.cogs += lp * onHand;
                    inv.units += onHand;
                }
            });
            $('#amz-sp-dil-tbody tr').each(function(i) {
                $(this).find('.amz-sp-dil-count').text(dilCounts['d' + i] || 0);
            });
            $('#amz-sp-age-tbody tr').each(function(i) {
                $(this).find('.amz-sp-age-count').text(ageCounts['a' + i] || 0);
            });
            $('#amz-sp-rev-tbody tr').each(function(i) {
                $(this).find('.amz-sp-rev-count').text(revCounts['r' + i] || 0);
            });
            $('#amz-sp-cvr-down-count').text(cvrCounts.down || 0);
            $('#amz-sp-cvr-flat-count').text(cvrCounts.flat || 0);
            $('#amz-sp-cvr-up-count').text(cvrCounts.up || 0);

            const dilSlices = draft.dil.map(function(r, i) {
                return { key: 'd' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const ageSlices = draft.age.map(function(r, i) {
                return { key: 'a' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            }).concat([{ key: 'none', label: 'No age', color: '#cbd5e1' }]);
            const revSlices = draft.reviews.map(function(r, i) {
                return { key: 'r' + i, label: r.min + '–' + r.max, color: AMZ_STD_PIE_COLORS[i % AMZ_STD_PIE_COLORS.length] };
            }).concat([{ key: 'none', label: 'No disc', color: '#cbd5e1' }]);
            const cvrSlices = [
                { key: 'down', label: 'Down < ' + draft.cvr.down_lt + '%', color: '#dc3545' },
                { key: 'flat', label: 'Flat', color: '#94a3b8' },
                { key: 'up', label: 'Up > ' + draft.cvr.up_gt + '%', color: '#198754' },
            ];
            const allSlices = [
                { key: 'age', label: 'Age', color: '#d97706' },
                { key: 'dil', label: 'Dil', color: '#6f42c1' },
                { key: 'cvr', label: 'CVR', color: '#20c997' },
                { key: 'rev', label: 'Reviews', color: '#7c3aed' },
            ];
            const allCounts = {
                age: Math.round(dollars.age),
                dil: Math.round(dollars.dil),
                cvr: Math.round(dollars.cvr),
                rev: Math.round(dollars.rev),
            };
            amzStdDrawPies([
                { id: 'amz-sp-pie-dil', slices: dilSlices, counts: dilCounts },
                { id: 'amz-sp-pie-rev', slices: revSlices, counts: revCounts },
                { id: 'amz-sp-pie-cvr', slices: cvrSlices, counts: cvrCounts },
                { id: 'amz-sp-pie-age', slices: ageSlices, counts: ageCounts },
                { id: 'amz-sp-pie-all', slices: allSlices, counts: allCounts },
            ]);
            amzStdLegend('#amz-sp-leg-dil', dilSlices, dilCounts);
            amzStdLegend('#amz-sp-leg-rev', revSlices, revCounts);
            amzStdLegend('#amz-sp-leg-cvr', cvrSlices, cvrCounts);
            amzStdLegend('#amz-sp-leg-age', ageSlices, ageCounts);
            amzStdLegend('#amz-sp-leg-all', allSlices, allCounts);

            const rows = [
                ['Age', skuHits.age, pctTotals.age, dollars.age],
                ['Dil', skuHits.dil, pctTotals.dil, dollars.dil],
                ['CVR', skuHits.cvr, pctTotals.cvr, dollars.cvr],
                ['Reviews', skuHits.rev, pctTotals.rev, dollars.rev],
            ];
            let pctSum = 0;
            let dollarSum = 0;
            const body = rows.map(function(row) {
                pctSum += row[2];
                dollarSum += row[3];
                return '<tr><td>' + row[0] + '</td><td class="text-center">' + row[1]
                    + '</td><td class="text-end">' + row[2] + '</td><td class="text-end">' + amzStdMoney(row[3]) + '</td></tr>';
            });
            body.push('<tr class="fw-semibold"><td>All</td><td class="text-center">' + skuHits.all
                + '</td><td class="text-end">' + pctSum + '</td><td class="text-end">' + amzStdMoney(dollarSum) + '</td></tr>');
            $('#amz-sp-all-tbody').html(body.join(''));

            const l30Pct = l30.sales > 0 ? (l30.gross / l30.sales) * 100 : 0;
            const invPct = inv.sales > 0 ? (inv.gross / inv.sales) * 100 : 0;
            $('#amz-sp-margin-l30').text(amzStdMoney(l30.gross)).css('color', l30.gross < 0 ? '#dc3545' : '#166534');
            $('#amz-sp-margin-l30-sub').text(Math.round(l30.units) + ' units · ' + Math.round(l30Pct) + '% of sales');
            $('#amz-sp-margin-l30-metrics').html(amzStdMetricHtml(l30));
            $('#amz-sp-margin-inv').text(amzStdMoney(inv.gross)).css('color', inv.gross < 0 ? '#dc3545' : '#166534');
            $('#amz-sp-margin-inv-sub').text(Math.round(inv.units) + ' units · ' + Math.round(invPct) + '% of retail');
            $('#amz-sp-margin-inv-metrics').html(amzStdMetricHtml(inv));
        }
        function amzStdInRange(value, rule, isLast) {
            const n = Number(value);
            if (!rule || !isFinite(n)) return false;
            let min = Number(rule.min);
            let max = Number(rule.max);
            if (!isFinite(min) || !isFinite(max)) return false;
            if (max < min) { const swap = min; min = max; max = swap; }
            if (Math.abs(max - min) < 0.00001) return Math.abs(n - min) < 0.00001;
            return n >= min && (isLast ? n <= max : n < max);
        }
        function loadAmzStdPrcRules() {
            return $.ajax({
                url: '/amazon-std-prc-vs-dil',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).done(function(res) {
                if (!res || !res.success) return;
                amzStdDilRules = (res.dil || AMZ_STD_DIL_DEFAULTS).map(function(r) { return amzStdNormRange(r); }).filter(Boolean);
                amzStdAgeRules = (res.age || AMZ_STD_AGE_DEFAULTS).map(function(r) { return amzStdNormRange(r); }).filter(Boolean);
                amzStdCvrCfg = amzStdNormCvr(res.cvr);
                if (!amzStdDilRules.length) amzStdDilRules = AMZ_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!amzStdAgeRules.length) amzStdAgeRules = AMZ_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            });
        }
        window.loadAmzStdPrcRules = loadAmzStdPrcRules;
        function amzStdApplyRules() {
            const draft = amzStdReadDraft();
            if (!draft.dil.length || !draft.age.length) {
                if (typeof amzPefToast === 'function') amzPefToast('error', 'Dil and Age need at least one range');
                return;
            }
            amzStdApplied = true;
            amzStdDilRules = draft.dil;
            amzStdAgeRules = draft.age;
            amzStdCvrCfg = draft.cvr;
            if (typeof amzReviewDiscRules !== 'undefined') {
                amzReviewDiscRules = draft.reviews.map(function(r) {
                    return (typeof amzNormalizeReviewDiscRule === 'function')
                        ? (amzNormalizeReviewDiscRule(r) || r)
                        : r;
                }).filter(Boolean);
            }
            if (typeof amzReviewDiscMax !== 'undefined') amzReviewDiscMax = draft.reviewMax;
            const status = $('#amz-sp-status');
            status.text('Saving…');
            const stdSave = $.ajax({
                url: '/amazon-std-prc-vs-dil',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), dil: draft.dil, age: draft.age, cvr: draft.cvr },
            });
            const revSave = $.ajax({
                url: '/amazon-review-disc',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof amzPefCsrf === 'function' ? amzPefCsrf() : ''), rules: draft.reviews, max_reviews: draft.reviewMax },
            });
            $.when(stdSave, revSave).done(function(stdRes) {
                const res = stdRes && stdRes[0] ? stdRes[0] : stdRes;
                if (res && res.dil) amzStdDilRules = res.dil.map(amzStdNormRange).filter(Boolean);
                if (res && res.age) amzStdAgeRules = res.age.map(amzStdNormRange).filter(Boolean);
                if (res && res.cvr) amzStdCvrCfg = amzStdNormCvr(res.cvr);
                if (typeof table !== 'undefined' && table && typeof amzTableRedrawPreserveScroll === 'function') {
                    amzTableRedrawPreserveScroll(true);
                }
                const picked = (typeof collectAmzPromoApplyTargets === 'function')
                    ? collectAmzPromoApplyTargets('No rows to price')
                    : { targets: [], cancelled: true, label: '' };
                if (!picked.cancelled && typeof applyAmzCombinedPlanToTargets === 'function') {
                    applyAmzCombinedPlanToTargets(picked.targets, picked.label, { toastLabel: 'Std prc vs dil' });
                }
                status.text('Saved. S PRC = Std Prc − Sum disc.');
                if (typeof amzPefToast === 'function') amzPefToast('success', 'Std prc vs dil saved');
            }).fail(function() {
                amzStdApplied = false;
                status.text('Save failed');
                if (typeof amzPefToast === 'function') amzPefToast('error', 'Could not save Std prc vs dil');
            });
        }
        function bindAmzStdPrcUi() {
            $('#amz-std-prc-btn').off('click.amzsp').on('click.amzsp', function(e) {
                e.preventDefault();
                amzStdApplied = false;
                amzStdPaintModal();
                const el = document.getElementById('amzStdPrcModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $('#amzStdPrcModal').off('shown.bs.modal.amzsp').on('shown.bs.modal.amzsp', function() {
                amzStdRefreshModal();
            });
            $('#amzStdPrcModal').off('hidden.bs.modal.amzsp').on('hidden.bs.modal.amzsp', function() {
                amzStdPieGen++;
                Object.keys(amzStdPieCharts).forEach(function(id) {
                    if (amzStdPieCharts[id]) { amzStdPieCharts[id].destroy(); amzStdPieCharts[id] = null; }
                });
                if (!amzStdApplied && typeof loadAmzStdPrcRules === 'function') {
                    loadAmzStdPrcRules();
                }
            });
            $('#amz-sp-apply').off('click.amzsp').on('click.amzsp', function(e) {
                e.preventDefault();
                amzStdApplyRules();
            });
            $('#amz-sp-dil-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-dil-tbody', '.amz-sp-dil-min', '.amz-sp-dil-max', '.amz-sp-dil-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                amzStdPaintRanges('#amz-sp-dil-tbody', 'amz-sp-dil', rules);
                amzStdRefreshModal();
            });
            $('#amz-sp-age-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-age-tbody', '.amz-sp-age-min', '.amz-sp-age-max', '.amz-sp-age-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last + 1, max: last + 30, disc: 0 });
                amzStdPaintRanges('#amz-sp-age-tbody', 'amz-sp-age', rules);
                amzStdRefreshModal();
            });
            $('#amz-sp-rev-add').off('click.amzsp').on('click.amzsp', function() {
                const rules = amzStdReadRanges('#amz-sp-rev-tbody', '.amz-sp-rev-min', '.amz-sp-rev-max', '.amz-sp-rev-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 1, disc: 4 });
                amzStdPaintRanges('#amz-sp-rev-tbody', 'amz-sp-rev', rules);
                amzStdRefreshModal();
            });
            $(document).off('click.amzspdel').on('click.amzspdel', '#amzStdPrcModal .amz-sp-dil-del, #amzStdPrcModal .amz-sp-age-del, #amzStdPrcModal .amz-sp-rev-del', function() {
                $(this).closest('tr').remove();
                amzStdRefreshModal();
            });
            $(document).off('input.amzsp').on('input.amzsp', '#amzStdPrcModal input', function() {
                clearTimeout(bindAmzStdPrcUi._t);
                bindAmzStdPrcUi._t = setTimeout(amzStdRefreshModal, 180);
            });
        }
        $(function() { bindAmzStdPrcUi(); });
@endif
