{{-- Std prc vs dil for every analytics page. Same slabs as Amazon. Parts: css, buttons, modals, script. --}}
@php $channelStdPrcPart = $channelStdPrcPart ?? 'all'; @endphp

@if($channelStdPrcPart === 'css' || $channelStdPrcPart === 'all')
        #chStdPrcModal .modal-dialog {
            width: calc(100vw - 1.25rem);
            max-width: calc(100vw - 1.25rem);
            height: calc(100vh - 1.25rem);
            max-height: calc(100vh - 1.25rem);
            margin: 0.625rem auto;
        }
        #chStdPrcModal .modal-content { height: 100%; max-height: 100%; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18); }
        #chStdPrcModal .modal-header { background: #fff; border-bottom: 1px solid #e8eef5; padding: 14px 18px; }
        #chStdPrcModal .modal-title { font-weight: 700; color: #0f172a; }
        #chStdPrcModal .ch-sp-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
        #chStdPrcModal .modal-body { background: #f4f7fb; padding: 14px 16px 16px; }
        #chStdPrcModal .modal-footer { background: #fff; border-top: 1px solid #e8eef5; }
        #chStdPrcModal .ch-sp-cols { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 12px; align-items: stretch; width: 100%; min-width: 0; }
        #chStdPrcModal .ch-sp-col { min-width: 0; display: flex; flex-direction: column; border: 1px solid #e6edf5; border-radius: 14px; padding: 12px; background: #fff; }
        #chStdPrcModal .ch-sp-pie-title { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #0f172a; }
        #chStdPrcModal .ch-sp-pie-canvas { position: relative; width: 100%; height: 150px; }
        #chStdPrcModal .ch-sp-pie-canvas canvas { display: block; width: 100% !important; height: 150px !important; }
        #chStdPrcModal .ch-sp-pie-legend { width: 100%; font-size: 12px; margin: 8px 0; color: #334155; }
        #chStdPrcModal .ch-sp-leg-row { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto auto 8px; gap: 6px; align-items: center; padding: 2px 0; }
        #chStdPrcModal .ch-sp-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #chStdPrcModal .ch-sp-leg-pct { color: #94a3b8; min-width: 2.2rem; text-align: right; }
        #chStdPrcModal .ch-sp-hist-dot { width: 8px; height: 8px; border-radius: 50%; border: none; padding: 0; cursor: pointer; justify-self: end; box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12); }
        #chStdPrcModal .ch-sp-hist-dot:hover { transform: scale(1.35); }
        #chStdPrcModal .ch-sp-hist-wrap { display: none; margin: 0 0 12px; padding: 8px 10px 6px; border: 1px solid #e6edf5; border-radius: 12px; background: #fff; }
        #chStdPrcModal .ch-sp-hist-wrap.is-open { display: block; }
        #chStdPrcModal .ch-sp-hist-canvas-wrap { height: 180px; }
        #chStdPrcModal .ch-sp-col .table { font-size: 12px; margin-bottom: 0; }
        #chStdPrcModal .ch-sp-col .table th, #chStdPrcModal .ch-sp-col .table td { padding: 5px 6px; vertical-align: middle; }
        #chStdPrcModal .ch-sp-col .table thead th { background: #f8fafc; color: #64748b; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; }
        #chStdPrcModal .ch-sp-input {
            width: 52px;
            min-width: 52px;
            max-width: 52px;
            margin: 0 auto;
            display: inline-block;
            box-sizing: border-box;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            line-height: 24px;
            height: 28px;
            padding: 0 4px;
            color: #0f172a;
            background: #fff;
            border-radius: 7px;
            -moz-appearance: textfield;
            appearance: textfield;
        }
        #chStdPrcModal .ch-sp-input::-webkit-outer-spin-button,
        #chStdPrcModal .ch-sp-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #chStdPrcModal td.text-end .ch-sp-input { margin-left: auto; margin-right: 0; }
        #chStdPrcModal .ch-sp-cvr-thresh { display: inline-flex; align-items: center; gap: 4px; color: #64748b; font-weight: 700; }
        #chStdPrcModal .ch-sp-cvr-thresh .ch-sp-input { width: 52px; margin: 0; }
        #chStdPrcModal .ch-sp-count { font-weight: 700; text-align: center; }
        #chStdPrcModal .ch-sp-when { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; white-space: nowrap; }
        #chStdPrcModal .ch-sp-when::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; }
        #chStdPrcModal .ch-sp-when-down2::before { background: #9f1239; }
        #chStdPrcModal .ch-sp-when-down::before { background: #dc3545; }
        #chStdPrcModal .ch-sp-when-up::before { background: #198754; }
        #chStdPrcModal .ch-sp-when-up2::before { background: #14532d; }
        #chStdPrcModal .ch-sp-margins { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        #chStdPrcModal .ch-sp-margin { display: flex; justify-content: space-between; gap: 16px; border: 1px solid #e6edf5; border-radius: 14px; padding: 12px 16px; background: #fff; }
        #chStdPrcModal .ch-sp-margin strong { display: block; font-size: 26px; letter-spacing: -0.03em; }
        #chStdPrcModal .ch-sp-metric-grid { display: grid; grid-template-columns: 3.4rem auto auto; gap: 4px 12px; width: max-content; font-size: 12px; align-items: center; }
        #chStdPrcModal .ch-sp-metric { display: contents; }
        #chStdPrcModal .ch-sp-add { margin-top: auto; width: 100%; border-radius: 8px; border-style: dashed; font-weight: 600; }
        #chStdPrcModal .ch-sp-del { width: 22px; height: 22px; padding: 0; border-radius: 6px; color: #94a3b8; }
@endif

@if($channelStdPrcPart === 'buttons' || $channelStdPrcPart === 'all')
                    <button type="button" class="btn btn-sm" id="ch-std-prc-btn" title="Std Prc minus Age, Dil, B Disc, 0 Sold, CVR, Review, and ROI discounts. Same slabs as Amazon.">
                        <i class="fas fa-tags"></i> Std prc vs dil
                    </button>
@endif

@if($channelStdPrcPart === 'modals' || $channelStdPrcPart === 'all')
    <div class="modal fade" id="chStdPrcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0"><i class="fas fa-tags me-1"></i> Std prc vs dil</h5>
                        <div class="ch-sp-sub">S PRC = Std Prc − Age − Dil − B Disc − 0 Sold − CVR − Reviews − ROI.@if(($channelPromoChannel ?? '') === 'shopify_b2b') Shopify B2B then subtracts the Ship column.@endif 0 Sold applies only when sold qty is 0. ROI slabs use this page's GROI%. Std Prc under $15 uses half of Age, Dil, 0 Sold, CVR, Review, and ROI discounts. B Disc stays at the full Disc %.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ch-sp-margins">
                        <div class="ch-sp-margin">
                            <div>
                                <div class="small text-muted">Projected margin · last L30 sales</div>
                                <strong id="ch-sp-margin-l30">—</strong>
                                <div class="small text-muted" id="ch-sp-margin-l30-sub"></div>
                            </div>
                            <div class="ch-sp-metric-grid" id="ch-sp-margin-l30-metrics"></div>
                        </div>
                        <div class="ch-sp-margin">
                            <div>
                                <div class="small text-muted">Projected margin · total INV</div>
                                <strong id="ch-sp-margin-inv">—</strong>
                                <div class="small text-muted" id="ch-sp-margin-inv-sub"></div>
                            </div>
                            <div class="ch-sp-metric-grid" id="ch-sp-margin-inv-metrics"></div>
                        </div>
                    </div>
                    <div class="ch-sp-hist-wrap" id="ch-sp-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="ch-sp-hist-title">Daily history</span>
                            <button type="button" class="btn-close" id="ch-sp-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="ch-sp-hist-canvas-wrap"><canvas id="ch-sp-hist"></canvas></div>
                    </div>
                    <div class="ch-sp-cols">
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title">Dil</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-dil"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-dil"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-dil-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-dil-add">Add slab</button>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title">Reviews</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-rev"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-rev"></div>
                            <div class="d-flex justify-content-between align-items-center mb-2"><label class="small fw-semibold mb-0" for="ch-sp-review-max">Max reviews</label><input type="number" id="ch-sp-review-max" class="form-control form-control-sm ch-sp-input" min="1" step="1" value="4"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-rev-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-rev-add">Add range</button>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title" title="Std Prc ranges. Disc % updates the B Disc column.">B Disc</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-buss"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-buss"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-buss-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-buss-add">Add range</button>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title" title="Disc % when this page's sold qty is 0. Sold &gt; 0 stays 0%.">0 Sold</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-zs"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-zs"></div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="small fw-semibold mb-0" for="ch-sp-zs-disc">Disc %</label>
                                <input type="number" id="ch-sp-zs-disc" class="form-control form-control-sm ch-sp-input" min="0" step="0.1" value="0">
                            </div>
                            <div class="small text-muted">0 sold SKUs: <strong id="ch-sp-zs-count">0</strong></div>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title">CVR up / down</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-cvr"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-cvr"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0" id="ch-sp-cvr-table"><thead class="table-light"><tr><th>When</th><th class="text-center">CVR%</th><th class="text-end">Disc %</th><th class="text-center">Count</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="ch-sp-when ch-sp-when-down2">Down</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&lt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down2-lt" value="4"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down2-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-down2-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when ch-sp-when-down">Down</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&lt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down-lt" value="7"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-down-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-down-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when">Flat</span></td><td class="text-center text-muted">mid</td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-flat-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-flat-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when ch-sp-when-up">Up</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&gt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up-gt" value="10"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-up-count">0</td></tr>
                                    <tr><td><span class="ch-sp-when ch-sp-when-up2">Up</span></td><td class="text-center"><span class="ch-sp-cvr-thresh">&gt;<input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up2-gt" value="15"></span></td><td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ch-sp-cvr-up2-disc" value="0"></td><td class="ch-sp-count" id="ch-sp-cvr-up2-count">0</td></tr>
                                </tbody>
                            </table></div>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title">Age Days</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-age"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-age"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-age-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-age-add">Add range</button>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title" title="GROI% ranges. Disc % updates the ROI disc column. Add or remove slabs.">ROI Discount</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-roi"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-roi"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th class="text-center">From</th><th class="text-center">To</th><th class="text-center">Count</th><th class="text-end">Disc %</th><th></th></tr></thead><tbody id="ch-sp-roi-tbody"></tbody></table></div>
                            <button type="button" class="btn btn-sm btn-outline-primary ch-sp-add" id="ch-sp-roi-add">Add slab</button>
                        </div>
                        <div class="ch-sp-col">
                            <div class="ch-sp-pie-title">All discounts</div>
                            <div class="ch-sp-pie-canvas"><canvas id="ch-sp-pie-all"></canvas></div>
                            <div class="ch-sp-pie-legend" id="ch-sp-leg-all"></div>
                            <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Discount</th><th class="text-center">SKUs</th><th class="text-end">Disc %</th><th class="text-end">$ off</th></tr></thead><tbody id="ch-sp-all-tbody"></tbody></table></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="small text-muted me-auto" id="ch-sp-status"></div>
                    <button type="button" class="btn btn-sm btn-primary" id="ch-sp-apply"><i class="fas fa-save me-1"></i> Save and Apply</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($channelStdPrcPart === 'script' || $channelStdPrcPart === 'all')
        const CH_STD_DIL_DEFAULTS = [
            { min: 0, max: 0, disc: 0 }, { min: 0.1, max: 10, disc: 0 }, { min: 10, max: 25, disc: 0 },
            { min: 25, max: 50, disc: 0 }, { min: 50, max: 100, disc: 0 }, { min: 100, max: 9999, disc: 0 },
        ];
        const CH_STD_AGE_DEFAULTS = [
            { min: 0, max: 30, disc: 0 }, { min: 31, max: 60, disc: 0 }, { min: 61, max: 90, disc: 0 },
            { min: 91, max: 180, disc: 0 }, { min: 181, max: 365, disc: 0 }, { min: 366, max: 9999, disc: 0 },
        ];
        const CH_STD_CVR_DEFAULT = { down2_lt: 4, down2_disc: 0, down_lt: 7, down_disc: 0, up_gt: 10, up_disc: 0, up2_gt: 15, up2_disc: 0, flat_disc: 0 };
        const CH_STD_REV_DEFAULTS = [{ min: 1, max: 2, disc: 4 }, { min: 2, max: 3, disc: 4 }];
        const CH_STD_BUSS_DEFAULTS = [{ min: 0, max: 15, disc: 0 }, { min: 15, max: 50, disc: 0 }, { min: 50, max: 9999, disc: 0 }];
        const CH_STD_ROI_DEFAULTS = [
            { min: -9999, max: 0, disc: 0 }, { min: 0, max: 50, disc: 0 }, { min: 50, max: 75, disc: 0 },
            { min: 75, max: 125, disc: 0 }, { min: 125, max: 9999, disc: 0 },
        ];
        let chStdZeroSoldDisc = 0;
        const CH_STD_COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b'];
        let chStdDil = CH_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdAge = CH_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdRev = CH_STD_REV_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdBuss = CH_STD_BUSS_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdRoi = CH_STD_ROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdCvr = Object.assign({}, CH_STD_CVR_DEFAULT);
        let chStdReviewMax = 4;
        const chStdCharts = {};
        let chStdPieGen = 0;
        let chStdHistChart = null;
        let chStdHistLive = {};

        function chStdEsc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }
        function chStdNormRange(r) {
            let min = Number(r && r.min), max = Number(r && r.max);
            if (!isFinite(min) || !isFinite(max)) return null;
            if (max < min) { const t = min; min = max; max = t; }
            let disc = Number(r && r.disc);
            if (!isFinite(disc) || disc < 0) disc = 0;
            if (disc > 100) disc = 100;
            return { min: min, max: max, disc: disc };
        }
        function chStdNormCvr(raw) {
            const out = Object.assign({}, CH_STD_CVR_DEFAULT);
            if (!raw) return out;
            const hadDown2 = raw.down2_disc !== undefined && raw.down2_disc !== null && raw.down2_disc !== '';
            const hadUp2 = raw.up2_disc !== undefined && raw.up2_disc !== null && raw.up2_disc !== '';
            ['down2_lt', 'down_lt', 'up_gt', 'up2_gt', 'down2_disc', 'down_disc', 'up_disc', 'up2_disc', 'flat_disc'].forEach(function(key) {
                const n = Number(raw[key]);
                if (!isFinite(n) || n < 0) return;
                out[key] = key.indexOf('disc') !== -1 && n > 100 ? 100 : n;
            });
            if (!hadDown2) out.down2_disc = out.down_disc;
            if (!hadUp2) out.up2_disc = out.up_disc;
            return out;
        }
        function chStdRowStd(d) {
            return (typeof chPromoStdBase === 'function') ? (Number(chPromoStdBase(d)) || 0) : 0;
        }
        function chStdLowFactor(std) {
            const n = Number(std);
            return (isFinite(n) && n > 0 && n < 15) ? 0.5 : 1;
        }
        function chStdScaleDisc(std, disc) {
            const n = Number(disc);
            if (!isFinite(n)) return 0;
            const factor = chStdLowFactor(std);
            if (factor === 1) return n;
            return Math.round(n * factor * 100) / 100;
        }
        function chStdRangeDisc(value, rules, allowNegative) {
            const n = Number(value);
            if (!isFinite(n) || (!allowNegative && n < 0) || !rules || !rules.length) return 0;
            const last = rules.length - 1;
            for (let i = 0; i < rules.length; i++) {
                const rule = rules[i];
                if (!rule) continue;
                let min = Number(rule.min), max = Number(rule.max);
                if (!isFinite(min) || !isFinite(max)) continue;
                if (max < min) { const t = min; min = max; max = t; }
                const hit = Math.abs(max - min) < 0.00001 ? Math.abs(n - min) < 0.00001 : (n >= min && (i === last ? n <= max : n < max));
                if (!hit) continue;
                const disc = Number(rule.disc);
                return isFinite(disc) && disc > 0 ? disc : 0;
            }
            return 0;
        }
        function chStdNum(d, keys) {
            if (!d) return 0;
            for (let i = 0; i < keys.length; i++) {
                const n = Number(d[keys[i]]);
                if (isFinite(n)) return n;
            }
            return 0;
        }
        let chStdAmzBySku = null;
        let chStdAmzMapReq = null;
        function chStdLoadAmzMap() {
            if (chStdAmzBySku) return $.Deferred().resolve(chStdAmzBySku).promise();
            if (chStdAmzMapReq) return chStdAmzMapReq;
            chStdAmzMapReq = $.ajax({
                url: '/inv-days/amazon-std-map',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).done(function(res) {
                chStdAmzBySku = (res && res.metrics && typeof res.metrics === 'object') ? res.metrics : {};
            }).fail(function() {
                chStdAmzBySku = {};
            });
            return chStdAmzMapReq;
        }
        function chStdAmzMetric(d) {
            if (!chStdAmzBySku || !d) return null;
            const hit = chStdAmzBySku[chStdSkuKey(chStdRowSku(d))];
            if (!hit || !hit.length) return null;
            return {
                a30: Number(hit[0]) || 0,
                s30: Number(hit[1]) || 0,
                a60: Number(hit[2]) || 0,
                s60: Number(hit[3]) || 0,
                ov: Number(hit[4]) || 0,
                inv: Number(hit[5]) || 0,
            };
        }
        function chStdCvrInputs(d) {
            const m = chStdAmzMetric(d);
            if (m) return m;
            return {
                a30: chStdNum(d, ['A_L30', 'a_l30', 'al30', 'AL30']),
                s30: chStdNum(d, ['Sess30', 'sess30', 'sessions_l30']),
                a60: chStdNum(d, ['units_ordered_l60', 'a_l60', 'al60']),
                s60: chStdNum(d, ['sessions_l60', 'sess60']),
            };
        }
        function chStdCvrLive(d) {
            const inp = chStdCvrInputs(d);
            return inp.s30 > 0 ? (inp.a30 / inp.s30) * 100 : 0;
        }
        function chStdCvrTrend(d) {
            const inp = chStdCvrInputs(d);
            const cvr = inp.s30 > 0 ? (inp.a30 / inp.s30) * 100 : 0;
            const sess45 = (inp.s30 + inp.s60) / 2;
            const cvr45 = sess45 > 0 ? (((inp.a30 + inp.a60) / 2) / sess45) * 100 : 0;
            if (cvr === 0 || cvr < cvr45 - 0.1) return 'down';
            if (cvr > cvr45 + 0.1) return 'up';
            return 'flat';
        }
        function chStdCvrSlabs(cfg) {
            const down = [
                { key: 'down2', lt: Number(cfg.down2_lt), disc: Number(cfg.down2_disc) || 0 },
                { key: 'down', lt: Number(cfg.down_lt), disc: Number(cfg.down_disc) || 0 },
            ].filter(function(s) { return isFinite(s.lt) && s.lt > 0; });
            down.sort(function(a, b) { return a.lt - b.lt; });
            const up = [
                { key: 'up', gt: Number(cfg.up_gt), disc: Number(cfg.up_disc) || 0 },
                { key: 'up2', gt: Number(cfg.up2_gt), disc: Number(cfg.up2_disc) || 0 },
            ].filter(function(s) { return isFinite(s.gt) && s.gt >= 0; });
            up.sort(function(a, b) { return b.gt - a.gt; });
            return { down: down, up: up };
        }
        function chStdCvrMatch(d, cfg) {
            const cvr = chStdCvrLive(d);
            const trend = chStdCvrTrend(d);
            const slabs = chStdCvrSlabs(cfg);
            if (trend === 'down') {
                for (let i = 0; i < slabs.down.length; i++) if (cvr < slabs.down[i].lt) return slabs.down[i];
            }
            if (trend === 'up') {
                for (let i = 0; i < slabs.up.length; i++) if (cvr > slabs.up[i].gt) return slabs.up[i];
            }
            return null;
        }
        let chStdAgeBySku = null;
        let chStdAgeMapReq = null;
        function chStdSkuKey(sku) {
            return String(sku == null ? '' : sku).replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
        }
        function chStdRowSku(d) {
            if (!d) return '';
            if (typeof chPromoSku === 'function') {
                const fromPromo = chPromoSku(d);
                if (fromPromo) return fromPromo;
            }
            return d['(Child) sku'] || d.sku || d.SKU || d.seller_sku || '';
        }
        function chStdStockForAge(d) {
            const m = chStdAmzMetric(d);
            if (m) return m.inv;
            const ch = (typeof CHANNEL_PROMO_CHANNEL === 'string') ? CHANNEL_PROMO_CHANNEL : '';
            if ((ch === 'doba' || ch === 'doba_withoutship') && d && d.shopify_inv != null && d.shopify_inv !== '') {
                return Number(d.shopify_inv) || 0;
            }
            if (typeof chPromoInv === 'function') return Number(chPromoInv(d)) || 0;
            if (d && d.INV != null && d.INV !== '') return Number(d.INV) || 0;
            if (d && d.inv != null && d.inv !== '') return Number(d.inv) || 0;
            if (d && d.shopify_inv != null && d.shopify_inv !== '') return Number(d.shopify_inv) || 0;
            return 0;
        }
        function chStdLoadAgeMap() {
            if (chStdAgeBySku) return $.Deferred().resolve(chStdAgeBySku).promise();
            if (chStdAgeMapReq) return chStdAgeMapReq;
            chStdAgeMapReq = $.ajax({
                url: '/inv-days/age-map',
                method: 'GET',
                headers: { 'Accept': 'application/json' },
            }).done(function(res) {
                chStdAgeBySku = (res && res.age_days && typeof res.age_days === 'object') ? res.age_days : {};
            }).fail(function() {
                chStdAgeBySku = {};
            });
            return chStdAgeMapReq;
        }
        function chStdAgeDays(d) {
            let raw = d && (d.age_days != null && d.age_days !== '' ? d.age_days : d.age);
            if ((raw == null || raw === '') && chStdAgeBySku && d) {
                const hit = chStdAgeBySku[chStdSkuKey(chStdRowSku(d))];
                if (hit != null && hit !== '') {
                    raw = hit;
                    d.age_days = hit;
                }
            }
            const n = Number(raw);
            return isFinite(n) ? n : null;
        }
        function chStdReviews(d) {
            return chStdNum(d, ['review_count', 'reviews', 'Reviews', 'rating_count', 'ratings']);
        }
        function chStdExcludesShip() {
            if (typeof CHANNEL_PROMO_CHANNEL === 'undefined') return false;
            return CHANNEL_PROMO_CHANNEL === 'shopify_b2b'
                || CHANNEL_PROMO_CHANNEL === 'faire'
                || CHANNEL_PROMO_CHANNEL === 'wayfair'
                || CHANNEL_PROMO_CHANNEL === 'topdawg'
                || CHANNEL_PROMO_CHANNEL === 'depop'
                || CHANNEL_PROMO_CHANNEL === 'mercari_woship';
        }
        function chStdRoiPct(d) {
            if (typeof shopifyB2bRowPriceMetrics === 'function') {
                const m = shopifyB2bRowPriceMetrics(d);
                const n = m && Number(m.sroi);
                return isFinite(n) ? n : 0;
            }
            const price = (typeof chPromoPrice === 'function') ? (Number(chPromoPrice(d)) || 0) : 0;
            const lp = (typeof chPromoLp === 'function') ? (Number(chPromoLp(d)) || 0) : 0;
            if (!(price > 0) || !(lp > 0)) return 0;
            const margin = chStdMargin(d);
            const ship = chStdExcludesShip() ? 0 : ((typeof chPromoShipCost === 'function') ? (Number(chPromoShipCost(d)) || 0) : 0);
            return (((price * margin) - ship - lp) / lp) * 100;
        }
        function chStdSumDisc(d, draft) {
            draft = draft || { dil: chStdDil, age: chStdAge, cvr: chStdCvr, reviews: chStdRev, reviewMax: chStdReviewMax, buss: chStdBussRulesLive(), roi: chStdRoi, zeroSoldDisc: chStdZeroSoldDisc };
            if (!(chStdStockForAge(d) > 0)) return 0;
            const std = chStdRowStd(d);
            const dil = chStdDilPct(d);
            const ageDisc = chStdAgeDisc(d, draft.age);
            const dilDisc = chStdScaleDisc(std, chStdRangeDisc(dil, draft.dil));
            const reviews = chStdReviews(d);
            let revDisc = 0;
            if (reviews > 0 && reviews < draft.reviewMax) {
                for (let i = 0; i < draft.reviews.length; i++) {
                    const rule = draft.reviews[i];
                    if (reviews >= rule.min && reviews <= rule.max) { revDisc = Number(rule.disc) || 0; break; }
                }
            }
            revDisc = chStdScaleDisc(std, revDisc);
            const cvrDisc = chStdScaleDisc(std, chStdCvrDiscPct(d, draft));
            const bussRules = draft.buss || chStdBuss;
            const bussDisc = chStdRangeDisc(std, bussRules);
            const roiRules = draft.roi || chStdRoi;
            const roiDisc = chStdScaleDisc(std, chStdRangeDisc(chStdRoiPct(d), roiRules, true));
            const zsRaw = chStdRowIsZeroSold(d) ? (Number(draft.zeroSoldDisc != null ? draft.zeroSoldDisc : chStdZeroSoldDisc) || 0) : 0;
            const zsDisc = chStdScaleDisc(std, zsRaw);
            return Math.min(99.99, Math.max(0, ageDisc + dilDisc + cvrDisc + revDisc + bussDisc + zsDisc + roiDisc));
        }
        function chStdRows() {
            const out = [];
            const take = function(d) {
                if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                if (!(chStdStockForAge(d) > 0)) return;
                out.push(d);
            };
            if (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) {
                allTableData.forEach(take);
                if (out.length) return out;
            }
            if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(function(row, d) { take(d); });
            return out;
        }
        function chStdMoney(n) {
            const v = Number(n) || 0;
            return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString('en-US', { maximumFractionDigits: 0 });
        }
        function chStdMargin(d) {
            if (typeof chPromoTakehomeMargin === 'function') {
                const m = Number(chPromoTakehomeMargin(d));
                if (isFinite(m) && m > 0) return m;
            }
            return 0.80;
        }
        function chStdReadRanges(tbody, minCls, maxCls, discCls) {
            const rules = [];
            $(tbody).find('tr').each(function() {
                const rule = chStdNormRange({ min: $(this).find(minCls).val(), max: $(this).find(maxCls).val(), disc: $(this).find(discCls).val() });
                if (rule) rules.push(rule);
            });
            return rules;
        }
        function chStdReadDraft() {
            return {
                dil: chStdReadRanges('#ch-sp-dil-tbody', '.ch-sp-dil-min', '.ch-sp-dil-max', '.ch-sp-dil-disc'),
                age: chStdReadRanges('#ch-sp-age-tbody', '.ch-sp-age-min', '.ch-sp-age-max', '.ch-sp-age-disc'),
                reviews: chStdReadRanges('#ch-sp-rev-tbody', '.ch-sp-rev-min', '.ch-sp-rev-max', '.ch-sp-rev-disc'),
                buss: chStdReadRanges('#ch-sp-buss-tbody', '.ch-sp-buss-min', '.ch-sp-buss-max', '.ch-sp-buss-disc'),
                roi: chStdReadRanges('#ch-sp-roi-tbody', '.ch-sp-roi-min', '.ch-sp-roi-max', '.ch-sp-roi-disc'),
                zeroSoldDisc: (function() { const n = Number($('#ch-sp-zs-disc').val()); return isFinite(n) ? Math.min(100, Math.max(0, n)) : 0; })(),
                reviewMax: (function() { const n = parseInt($('#ch-sp-review-max').val(), 10); return isFinite(n) && n > 0 ? n : 4; })(),
                cvr: chStdNormCvr({
                    down2_lt: $('#chStdPrcModal .ch-sp-cvr-down2-lt').val(), down2_disc: $('#chStdPrcModal .ch-sp-cvr-down2-disc').val(),
                    down_lt: $('#chStdPrcModal .ch-sp-cvr-down-lt').val(), down_disc: $('#chStdPrcModal .ch-sp-cvr-down-disc').val(),
                    up_gt: $('#chStdPrcModal .ch-sp-cvr-up-gt').val(), up_disc: $('#chStdPrcModal .ch-sp-cvr-up-disc').val(),
                    up2_gt: $('#chStdPrcModal .ch-sp-cvr-up2-gt').val(), up2_disc: $('#chStdPrcModal .ch-sp-cvr-up2-disc').val(),
                    flat_disc: $('#chStdPrcModal .ch-sp-cvr-flat-disc').val(),
                }),
            };
        }
        function chStdRangeRow(prefix, rule) {
            return '<tr><td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm ch-sp-input ' + prefix + '-min" value="' + rule.min + '"></td>'
                + '<td class="text-center"><input type="number" step="0.1" class="form-control form-control-sm ch-sp-input ' + prefix + '-max" value="' + rule.max + '"></td>'
                + '<td class="ch-sp-count ' + prefix + '-count">0</td>'
                + '<td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm ch-sp-input ' + prefix + '-disc" value="' + rule.disc + '"></td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm ch-sp-del ' + prefix + '-del" title="Remove">&times;</button></td></tr>';
        }
        function chStdLegend(el, slices, counts, chart) {
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            $(el).html(slices.map(function(s) {
                const n = counts[s.key] || 0;
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                chStdHistLive[chart + ':' + s.key] = n;
                return '<div class="ch-sp-leg-row"><span class="ch-sp-swatch" style="background:' + s.color + '"></span><span>' + chStdEsc(s.label) + '</span><strong>' + n + '</strong><span class="ch-sp-leg-pct">' + pct + '%</span>'
                    + '<button type="button" class="ch-sp-hist-dot" data-chart="' + chStdEsc(chart) + '" data-band="' + chStdEsc(s.key) + '" data-label="' + chStdEsc(s.label) + '" data-color="' + chStdEsc(s.color) + '" style="background:' + s.color + ';" title="' + chStdEsc(s.label) + ' daily history"></button></div>';
            }).join(''));
        }
        function chStdDrawBar(id, slices, counts) {
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : chStdCharts[id];
            if (bound) bound.destroy();
            const total = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            chStdCharts[id] = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: slices.map(function(s) { return s.label; }),
                    datasets: [{
                        data: slices.map(function(s) { return counts[s.key] || 0; }),
                        backgroundColor: slices.map(function(s) { return s.color; }),
                        borderWidth: 0, borderRadius: 2, borderSkipped: 'bottom',
                        barPercentage: 0.78, categoryPercentage: 0.72, maxBarThickness: 42,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) {
                        const n = Number(ctx.raw) || 0;
                        return ' ' + n + ' · ' + (total > 0 ? Math.round((n / total) * 100) : 0) + '%';
                    } } } },
                    scales: {
                        x: { grid: { display: false }, border: { display: true, color: '#111827' }, ticks: { display: false } },
                        y: { beginAtZero: true, grid: { display: false }, border: { display: true, color: '#111827' }, ticks: { font: { size: 9 }, precision: 0 } },
                    },
                },
            });
        }
        function chStdDrawBars(jobs) {
            const gen = ++chStdPieGen;
            const draw = function() {
                if (gen !== chStdPieGen) return;
                jobs.forEach(function(job) { chStdDrawBar(job.id, job.slices, job.counts); });
            };
            if (typeof window.loadChartJs === 'function') window.loadChartJs().then(draw).catch(function() {});
            else draw();
        }
        function chStdInRange(value, rule, isLast) {
            const n = Number(value);
            if (!rule || !isFinite(n)) return false;
            let min = Number(rule.min), max = Number(rule.max);
            if (max < min) { const t = min; min = max; max = t; }
            if (Math.abs(max - min) < 0.00001) return Math.abs(n - min) < 0.00001;
            return n >= min && (isLast ? n <= max : n < max);
        }
        function chStdRefresh() {
            const draft = chStdReadDraft();
            const dilCounts = { outside: 0 }, ageCounts = { none: 0 }, revCounts = { none: 0 }, bussCounts = { outside: 0 }, roiCounts = { outside: 0 };
            const cvrCounts = { down2: 0, down: 0, flat: 0, up: 0, up2: 0 };
            draft.dil.forEach(function(r, i) { dilCounts['d' + i] = 0; });
            draft.age.forEach(function(r, i) { ageCounts['a' + i] = 0; });
            draft.reviews.forEach(function(r, i) { revCounts['r' + i] = 0; });
            draft.buss.forEach(function(r, i) { bussCounts['u' + i] = 0; });
            (draft.roi || []).forEach(function(r, i) { roiCounts['o' + i] = 0; });
            const dollars = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, zs: 0, roi: 0 };
            const pctTotals = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, zs: 0, roi: 0 };
            const skuHits = { age: 0, dil: 0, cvr: 0, rev: 0, buss: 0, zs: 0, roi: 0, all: 0 };
            const zsCounts = { zero: 0, sold: 0 };
            const l30 = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const invB = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const ads = (typeof chPromoAdsFrac === 'function') ? ((Number(chPromoAdsFrac()) || 0) * 100) : 0;
            chStdRows().forEach(function(d) {
                const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
                const dilVal = chStdDilPct(d);
                const invOk = chStdStockForAge(d) > 0;
                let dilIdx = -1;
                for (let i = 0; i < draft.dil.length; i++) if (chStdInRange(dilVal, draft.dil[i], i === draft.dil.length - 1)) { dilIdx = i; break; }
                if (dilIdx >= 0) dilCounts['d' + dilIdx] += 1; else dilCounts.outside += 1;
                const ageVal = chStdAgeDays(d);
                let ageIdx = -1;
                if (ageVal != null) {
                    for (let i = 0; i < draft.age.length; i++) if (chStdInRange(ageVal, draft.age[i], i === draft.age.length - 1)) { ageIdx = i; break; }
                }
                if (ageIdx >= 0) ageCounts['a' + ageIdx] += 1; else ageCounts.none += 1;
                const reviews = chStdReviews(d);
                let revIdx = -1;
                if (reviews > 0 && reviews < draft.reviewMax) {
                    for (let i = 0; i < draft.reviews.length; i++) {
                        if (reviews >= draft.reviews[i].min && reviews <= draft.reviews[i].max) { revIdx = i; break; }
                    }
                }
                if (revIdx >= 0) revCounts['r' + revIdx] += 1; else revCounts.none += 1;
                const stdHit = Number(std) || 0;
                let bussIdx = -1;
                for (let i = 0; i < draft.buss.length; i++) if (chStdInRange(stdHit, draft.buss[i], i === draft.buss.length - 1)) { bussIdx = i; break; }
                if (bussIdx >= 0) bussCounts['u' + bussIdx] += 1; else bussCounts.outside += 1;
                const roiVal = chStdRoiPct(d);
                const roiRules = draft.roi || [];
                let roiIdx = -1;
                for (let i = 0; i < roiRules.length; i++) if (chStdInRange(roiVal, roiRules[i], i === roiRules.length - 1)) { roiIdx = i; break; }
                if (roiIdx >= 0) roiCounts['o' + roiIdx] += 1; else roiCounts.outside += 1;
                const hit = chStdCvrMatch(d, draft.cvr);
                cvrCounts[hit ? hit.key : 'flat'] += 1;
                const ageDisc = invOk ? chStdScaleDisc(std, ageIdx >= 0 ? (Number(draft.age[ageIdx].disc) || 0) : 0) : 0;
                const dilDisc = invOk ? chStdScaleDisc(std, dilIdx >= 0 ? (Number(draft.dil[dilIdx].disc) || 0) : 0) : 0;
                const revDisc = chStdScaleDisc(std, revIdx >= 0 ? (Number(draft.reviews[revIdx].disc) || 0) : 0);
                const bussDisc = bussIdx >= 0 ? (Number(draft.buss[bussIdx].disc) || 0) : 0;
                const roiDisc = invOk ? chStdScaleDisc(std, roiIdx >= 0 ? (Number(roiRules[roiIdx].disc) || 0) : 0) : 0;
                const cvrDisc = invOk ? chStdScaleDisc(std, Math.max(0, hit ? (Number(hit.disc) || 0) : (Number(draft.cvr.flat_disc) || 0))) : 0;
                const zeroSold = invOk && chStdRowIsZeroSold(d);
                if (zeroSold) zsCounts.zero += 1; else zsCounts.sold += 1;
                const zsDisc = zeroSold ? chStdScaleDisc(std, Number(draft.zeroSoldDisc) || 0) : 0;
                if (ageDisc > 0) { skuHits.age++; dollars.age += std * ageDisc / 100; pctTotals.age += ageDisc; }
                if (dilDisc > 0) { skuHits.dil++; dollars.dil += std * dilDisc / 100; pctTotals.dil += dilDisc; }
                if (cvrDisc > 0) { skuHits.cvr++; dollars.cvr += std * cvrDisc / 100; pctTotals.cvr += cvrDisc; }
                if (revDisc > 0) { skuHits.rev++; dollars.rev += std * revDisc / 100; pctTotals.rev += revDisc; }
                if (bussDisc > 0) { skuHits.buss++; dollars.buss += std * bussDisc / 100; pctTotals.buss += bussDisc; }
                if (zsDisc > 0) { skuHits.zs++; dollars.zs += std * zsDisc / 100; pctTotals.zs += zsDisc; }
                if (roiDisc > 0) { skuHits.roi++; dollars.roi += std * roiDisc / 100; pctTotals.roi += roiDisc; }
                const sum = Math.min(99.99, ageDisc + dilDisc + cvrDisc + revDisc + bussDisc + zsDisc + roiDisc);
                if (sum > 0) skuHits.all++;
                if (std > 0) {
                    const sprice = Math.round(std * (1 - sum / 100) * 100) / 100;
                    const lp = (typeof chPromoLp === 'function') ? chPromoLp(d) : 0;
                    const ship = chStdExcludesShip() ? 0 : ((typeof chPromoShipCost === 'function') ? chPromoShipCost(d) : 0);
                    const gross = (sprice * chStdMargin(d)) - ship - lp;
                    const net = gross - (sprice * ads / 100);
                    const units = (typeof chPromoOvL30 === 'function') ? chPromoOvL30(d) : 0;
                    const inv = (typeof chPromoInv === 'function') ? chPromoInv(d) : 0;
                    l30.gross += gross * units; l30.net += net * units; l30.sales += sprice * units; l30.cogs += lp * units; l30.units += units;
                    invB.gross += gross * inv; invB.net += net * inv; invB.sales += sprice * inv; invB.cogs += lp * inv; invB.units += inv;
                }
            });
            const countKey = { dil: 'd', age: 'a', rev: 'r', buss: 'u', roi: 'o' };
            const countMap = { dil: dilCounts, age: ageCounts, rev: revCounts, buss: bussCounts, roi: roiCounts };
            ['dil', 'age', 'rev', 'buss', 'roi'].forEach(function(prefix) {
                $('#ch-sp-' + prefix + '-tbody tr').each(function(i) { $(this).find('.ch-sp-' + prefix + '-count').text(countMap[prefix][countKey[prefix] + i] || 0); });
            });
            $('#ch-sp-cvr-down2-count').text(cvrCounts.down2);
            $('#ch-sp-cvr-down-count').text(cvrCounts.down);
            $('#ch-sp-cvr-flat-count').text(cvrCounts.flat);
            $('#ch-sp-cvr-up-count').text(cvrCounts.up);
            $('#ch-sp-cvr-up2-count').text(cvrCounts.up2);
            $('#ch-sp-zs-count').text(zsCounts.zero);
            const dilSlices = draft.dil.map(function(r, i) { return { key: 'd' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const ageSlices = draft.age.map(function(r, i) { return { key: 'a' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'none', label: 'No age', color: '#cbd5e1' }]);
            const revSlices = draft.reviews.map(function(r, i) { return { key: 'r' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'none', label: 'No disc', color: '#cbd5e1' }]);
            const bussSlices = draft.buss.map(function(r, i) { return { key: 'u' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const roiSlices = (draft.roi || []).map(function(r, i) { return { key: 'o' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const cvrSlices = [
                { key: 'down2', label: 'Down < ' + draft.cvr.down2_lt + '%', color: '#9f1239' },
                { key: 'down', label: 'Down < ' + draft.cvr.down_lt + '%', color: '#dc3545' },
                { key: 'flat', label: 'Flat', color: '#94a3b8' },
                { key: 'up', label: 'Up > ' + draft.cvr.up_gt + '%', color: '#198754' },
                { key: 'up2', label: 'Up > ' + draft.cvr.up2_gt + '%', color: '#14532d' },
            ];
            const allSlices = [
                { key: 'age', label: 'Age', color: '#d97706' }, { key: 'dil', label: 'Dil', color: '#6f42c1' },
                { key: 'cvr', label: 'CVR', color: '#20c997' }, { key: 'rev', label: 'Reviews', color: '#7c3aed' },
                { key: 'buss', label: 'B Disc', color: '#0d6efd' },
                { key: 'zs', label: '0 Sold', color: '#f59e0b' },
                { key: 'roi', label: 'ROI', color: '#db2777' },
            ];
            const allCounts = { age: Math.round(dollars.age), dil: Math.round(dollars.dil), cvr: Math.round(dollars.cvr), rev: Math.round(dollars.rev), buss: Math.round(dollars.buss), zs: Math.round(dollars.zs), roi: Math.round(dollars.roi) };
            const zsSlices = [
                { key: 'zero', label: '0 sold', color: '#f59e0b' },
                { key: 'sold', label: 'Sold', color: '#94a3b8' },
            ];
            chStdHistLive = {};
            chStdLegend('#ch-sp-leg-dil', dilSlices, dilCounts, 'dil');
            chStdLegend('#ch-sp-leg-rev', revSlices, revCounts, 'rev');
            chStdLegend('#ch-sp-leg-buss', bussSlices, bussCounts, 'buss');
            chStdLegend('#ch-sp-leg-roi', roiSlices, roiCounts, 'roi');
            chStdLegend('#ch-sp-leg-zs', zsSlices, zsCounts, 'zs');
            chStdLegend('#ch-sp-leg-cvr', cvrSlices, cvrCounts, 'cvr');
            chStdLegend('#ch-sp-leg-age', ageSlices, ageCounts, 'age');
            chStdLegend('#ch-sp-leg-all', allSlices, allCounts, 'all');
            chStdDrawBars([
                { id: 'ch-sp-pie-dil', slices: dilSlices, counts: dilCounts },
                { id: 'ch-sp-pie-rev', slices: revSlices, counts: revCounts },
                { id: 'ch-sp-pie-buss', slices: bussSlices, counts: bussCounts },
                { id: 'ch-sp-pie-roi', slices: roiSlices, counts: roiCounts },
                { id: 'ch-sp-pie-zs', slices: zsSlices, counts: zsCounts },
                { id: 'ch-sp-pie-cvr', slices: cvrSlices, counts: cvrCounts },
                { id: 'ch-sp-pie-age', slices: ageSlices, counts: ageCounts },
                { id: 'ch-sp-pie-all', slices: allSlices, counts: allCounts },
            ]);
            let pctSum = 0, dollarSum = 0;
            const body = [['Age', skuHits.age, pctTotals.age, dollars.age], ['Dil', skuHits.dil, pctTotals.dil, dollars.dil], ['B Disc', skuHits.buss, pctTotals.buss, dollars.buss], ['0 Sold', skuHits.zs, pctTotals.zs, dollars.zs], ['CVR', skuHits.cvr, pctTotals.cvr, dollars.cvr], ['Reviews', skuHits.rev, pctTotals.rev, dollars.rev], ['ROI', skuHits.roi, pctTotals.roi, dollars.roi]].map(function(row) {
                pctSum += row[2]; dollarSum += row[3];
                return '<tr><td>' + row[0] + '</td><td class="text-center">' + row[1] + '</td><td class="text-end">' + row[2] + '</td><td class="text-end">' + chStdMoney(row[3]) + '</td></tr>';
            });
            body.push('<tr class="fw-semibold"><td>All</td><td class="text-center">' + skuHits.all + '</td><td class="text-end">' + pctSum + '</td><td class="text-end">' + chStdMoney(dollarSum) + '</td></tr>');
            $('#ch-sp-all-tbody').html(body.join(''));
            function metricHtml(bucket) {
                const rows = [
                    ['GROI', bucket.gross, bucket.cogs > 0 ? (bucket.gross / bucket.cogs) * 100 : 0],
                    ['GPFT', bucket.gross, bucket.sales > 0 ? (bucket.gross / bucket.sales) * 100 : 0],
                    ['NROI', bucket.net, bucket.cogs > 0 ? (bucket.net / bucket.cogs) * 100 : 0],
                    ['NPFT', bucket.net, bucket.sales > 0 ? (bucket.net / bucket.sales) * 100 : 0],
                ];
                return rows.map(function(row) {
                    return '<div class="ch-sp-metric"><span>' + row[0] + '</span><span style="text-align:right;font-weight:700;color:' + (row[1] < 0 ? '#dc3545' : '#166534') + '">' + chStdMoney(row[1]) + '</span><span style="text-align:right;font-weight:700;">' + Math.round(row[2]) + '%</span></div>';
                }).join('');
            }
            $('#ch-sp-margin-l30').text(chStdMoney(l30.gross)).css('color', l30.gross < 0 ? '#dc3545' : '#166534');
            $('#ch-sp-margin-l30-sub').text(Math.round(l30.units) + ' units · ' + (l30.sales > 0 ? Math.round((l30.gross / l30.sales) * 100) : 0) + '% of sales');
            $('#ch-sp-margin-l30-metrics').html(metricHtml(l30));
            $('#ch-sp-margin-inv').text(chStdMoney(invB.gross)).css('color', invB.gross < 0 ? '#dc3545' : '#166534');
            $('#ch-sp-margin-inv-sub').text(Math.round(invB.units) + ' units · ' + (invB.sales > 0 ? Math.round((invB.gross / invB.sales) * 100) : 0) + '% of retail');
            $('#ch-sp-margin-inv-metrics').html(metricHtml(invB));
            chStdRepaintDiscColumns();
        }
        function chStdPaint() {
            $('#ch-sp-dil-tbody').html(chStdDil.map(function(r) { return chStdRangeRow('ch-sp-dil', r); }).join(''));
            $('#ch-sp-age-tbody').html(chStdAge.map(function(r) { return chStdRangeRow('ch-sp-age', r); }).join(''));
            $('#ch-sp-rev-tbody').html(chStdRev.map(function(r) { return chStdRangeRow('ch-sp-rev', r); }).join(''));
            $('#ch-sp-buss-tbody').html(chStdBuss.map(function(r) { return chStdRangeRow('ch-sp-buss', r); }).join(''));
            $('#ch-sp-roi-tbody').html(chStdRoi.map(function(r) { return chStdRangeRow('ch-sp-roi', r); }).join(''));
            $('#ch-sp-zs-disc').val(chStdZeroSoldDisc);
            $('#ch-sp-review-max').val(chStdReviewMax);
            const cfg = chStdNormCvr(chStdCvr);
            $('#chStdPrcModal .ch-sp-cvr-down2-lt').val(cfg.down2_lt);
            $('#chStdPrcModal .ch-sp-cvr-down2-disc').val(cfg.down2_disc);
            $('#chStdPrcModal .ch-sp-cvr-down-lt').val(cfg.down_lt);
            $('#chStdPrcModal .ch-sp-cvr-down-disc').val(cfg.down_disc);
            $('#chStdPrcModal .ch-sp-cvr-up-gt').val(cfg.up_gt);
            $('#chStdPrcModal .ch-sp-cvr-up-disc').val(cfg.up_disc);
            $('#chStdPrcModal .ch-sp-cvr-up2-gt').val(cfg.up2_gt);
            $('#chStdPrcModal .ch-sp-cvr-up2-disc').val(cfg.up2_disc);
            $('#chStdPrcModal .ch-sp-cvr-flat-disc').val(cfg.flat_disc);
            chStdRefresh();
        }
        function chStdLoad() {
            return $.ajax({ url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil', method: 'GET', headers: { 'Accept': 'application/json' } }).done(function(res) {
                if (!res || !res.success) return;
                chStdDil = (res.dil || CH_STD_DIL_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdAge = (res.age || CH_STD_AGE_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdRev = (res.reviews || CH_STD_REV_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdBuss = (res.buss || CH_STD_BUSS_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdRoi = (res.roi || CH_STD_ROI_DEFAULTS).map(chStdNormRange).filter(Boolean);
                chStdZeroSoldDisc = isFinite(Number(res.zero_sold_disc)) ? Math.min(100, Math.max(0, Number(res.zero_sold_disc))) : 0;
                chStdCvr = chStdNormCvr(res.cvr);
                chStdReviewMax = parseInt(res.review_max, 10) || 4;
                if (!chStdDil.length) chStdDil = CH_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdAge.length) chStdAge = CH_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdRev.length) chStdRev = CH_STD_REV_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdBuss.length) chStdBuss = CH_STD_BUSS_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdRoi.length) chStdRoi = CH_STD_ROI_DEFAULTS.map(function(r) { return Object.assign({}, r); });
            });
        }
        function chStdTodayKey() {
            try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
            catch (e) { return new Date().toISOString().slice(0, 10); }
        }
        function chStdSaveHistory() {
            const counts = Object.assign({}, chStdHistLive);
            if (!Object.keys(counts).some(function(key) { return Number(counts[key]) > 0; })) return;
            $.ajax({
                url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil-history',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), counts: counts },
            });
        }
        function chStdPadHistory(rows) {
            const byDate = {};
            (rows || []).forEach(function(r) { if (r && r.date) byDate[r.date] = r; });
            const today = chStdTodayKey();
            const parts = today.split('-').map(Number);
            const end = new Date(Date.UTC(parts[0], (parts[1] || 1) - 1, parts[2] || 1));
            const out = [];
            for (let i = 29; i >= 0; i--) {
                const d = new Date(end);
                d.setUTCDate(d.getUTCDate() - i);
                const key = d.toISOString().slice(0, 10);
                const rec = byDate[key] ? Object.assign({}, byDate[key]) : {};
                rec.date = key; rec.label = key.slice(5);
                out.push(rec);
            }
            const last = out[out.length - 1];
            if (last) Object.assign(last, chStdHistLive, { date: last.date, label: last.label });
            return out;
        }
        function chStdOpenHist(chart, band, label, color) {
            const draw = function(rows) {
                const plot = chStdPadHistory(rows);
                const field = chart + ':' + band;
                $('#ch-sp-hist-title').text(label + (chart === 'all' ? ' · $ off' : ' count') + ' · last 30 days');
                $('#ch-sp-hist-wrap').addClass('is-open');
                const go = function() {
                    const canvas = document.getElementById('ch-sp-hist');
                    if (!canvas || typeof Chart === 'undefined') return;
                    if (chStdHistChart) { chStdHistChart.destroy(); chStdHistChart = null; }
                    chStdHistChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: { labels: plot.map(function(r) { return r.label; }), datasets: [{ data: plot.map(function(r) { return Number(r[field]) || 0; }), borderColor: color, backgroundColor: color + '22', fill: true, tension: 0.3, pointRadius: 3 }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { ticks: { maxRotation: 90, minRotation: 90, font: { size: 8 } } } } },
                    });
                };
                if (typeof window.loadChartJs === 'function') window.loadChartJs().then(go).catch(go); else go();
            };
            $.ajax({ url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil-history', method: 'GET', data: { days: 30 } })
                .done(function(res) { draw((res && res.success && Array.isArray(res.data)) ? res.data : []); })
                .fail(function() { draw([]); });
        }
        function chStdDraftNow() {
            return { dil: chStdDil, age: chStdAge, cvr: chStdCvr, reviews: chStdRev, reviewMax: chStdReviewMax, buss: chStdBuss, roi: chStdRoi, zeroSoldDisc: chStdZeroSoldDisc };
        }
        function chStdDiscBadge(pct) {
            const n = Number(pct) || 0;
            if (!(n > 0)) return '<span style="color:#adb5bd;font-weight:600;">—</span>';
            return '<span style="font-weight:700;color:#0f172a;">' + n + '</span>';
        }
        function chStdAgeDisc(d, rules) {
            // Same as Amazon analytics: INV = 0 → 0%. Age days come from the Shopify push clock.
            if (!(chStdStockForAge(d) > 0)) return 0;
            const days = chStdAgeDays(d);
            if (days == null) return 0;
            return chStdScaleDisc(chStdRowStd(d), chStdRangeDisc(days, rules || chStdAge));
        }
        function chStdDilPct(d) {
            const m = chStdAmzMetric(d);
            if (m) return m.inv > 0 ? (m.ov / m.inv) * 100 : 0;
            return (typeof chPromoDil === 'function') ? chPromoDil(d) : 0;
        }
        function chStdDilDisc(d) {
            if (!(chStdStockForAge(d) > 0)) return 0;
            return chStdScaleDisc(chStdRowStd(d), chStdRangeDisc(chStdDilPct(d), chStdDil));
        }
        function chStdCvrDiscPct(d, draft) {
            if (!(chStdStockForAge(d) > 0)) return 0;
            const cfg = (draft && draft.cvr) ? draft.cvr : chStdCvr;
            const hit = chStdCvrMatch(d, cfg);
            const trend = hit ? (Number(hit.disc) || 0) : (Number(cfg.flat_disc) || 0);
            return Math.max(0, trend);
        }
        function chStdRevDiscPct(d) {
            const reviews = chStdReviews(d);
            if (!(reviews > 0) || reviews >= chStdReviewMax) return 0;
            for (let i = 0; i < chStdRev.length; i++) {
                const rule = chStdRev[i];
                if (reviews >= rule.min && reviews <= rule.max) return chStdScaleDisc(chStdRowStd(d), Number(rule.disc) || 0);
            }
            return 0;
        }
        function chStdRoiDiscPct(d) {
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return null;
            if (!(chStdStockForAge(d) > 0)) return 0;
            return chStdScaleDisc(chStdRowStd(d), chStdRangeDisc(chStdRoiPct(d), chStdRoi, true));
        }
        window.chStdRoiDiscPct = chStdRoiDiscPct;
        function chStdBussRulesLive() {
            const modal = document.getElementById('chStdPrcModal');
            if (modal && modal.classList.contains('show')) {
                const live = chStdReadRanges('#ch-sp-buss-tbody', '.ch-sp-buss-min', '.ch-sp-buss-max', '.ch-sp-buss-disc');
                if (live.length) return live;
            }
            return chStdBuss;
        }
        function chStdBussDiscPct(d) {
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return null;
            if (!(chStdStockForAge(d) > 0)) return 0;
            return chStdRangeDisc(chStdRowStd(d), chStdBussRulesLive());
        }
        window.chStdBussDiscPct = chStdBussDiscPct;
        window.analyticsBussDiscountPct = chStdBussDiscPct;
        function chStdRepaintDiscColumns() {
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function') return;
            ['buss_discount', 'sum_discount'].forEach(function(field) {
                let col = null;
                try { col = table.getColumn(field); } catch (e) { col = null; }
                if (!col || typeof col.getCells !== 'function') return;
                col.getCells().forEach(function(cell) {
                    try { if (cell && typeof cell.reformat === 'function') cell.reformat(); } catch (err) { /* ignore */ }
                });
            });
        }
        function chStdRowIsZeroSold(d) {
            if (!d || d.is_parent_summary) return false;
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return false;
            if (!(chStdStockForAge(d) > 0)) return false;
            if (typeof chPromoIsZeroSoldRow === 'function') return !!chPromoIsZeroSoldRow(d);
            return !(chStdNum(d, ['L30', 'l30', 'AL30', 'al30', 'A_L30', 'sold', 'E L30', 'RV L30']) > 0);
        }
        function chStdZeroSoldDiscPct(d) {
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return null;
            if (!chStdRowIsZeroSold(d)) return 0;
            return chStdScaleDisc(chStdRowStd(d), Number(chStdZeroSoldDisc) || 0);
        }
        window.chStdZeroSoldDiscPct = chStdZeroSoldDiscPct;
        function chStdDiscCol(title, field, tip, read) {
            return {
                title: title,
                field: field,
                width: 72,
                hozAlign: 'center',
                vertAlign: 'middle',
                headerSort: true,
                headerTooltip: tip,
                sorter: function(a, b, aRow, bRow) {
                    return (Number(read(aRow.getData() || {})) || 0) - (Number(read(bRow.getData() || {})) || 0);
                },
                formatter: function(cell) {
                    const d = cell.getRow().getData() || {};
                    if (d.is_parent_summary || (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d))) return '';
                    return chStdDiscBadge(read(d));
                },
            };
        }
        function chStdDiscColumns() {
            return [
                Object.assign(chStdDiscCol('Age Disc', 'age_discount', 'Age Disc — promotional % from Age Days slabs in Std prc vs dil. Same Shopify age clock as Amazon. INV = 0 → 0%. Read-only.', chStdAgeDisc), {
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d))) return '';
                        const pct = chStdAgeDisc(d);
                        const days = chStdAgeDays(d);
                        const tip = (days == null ? 'No age' : (days + ' age days'))
                            + ' → discount ' + (pct || 0) + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                Object.assign(chStdDiscCol('Dil Disc', 'dil_discount', 'Dil Disc — same as Amazon: Shopify OV L30 ÷ Shopify INV. INV = 0 → 0%.', chStdDilDisc), {
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d))) return '';
                        const pct = chStdDilDisc(d);
                        const dil = chStdDilPct(d);
                        const tip = 'Dil ' + (isFinite(dil) ? dil.toFixed(1) : '0') + '% → discount ' + (pct || 0) + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                Object.assign(chStdDiscCol('B Disc', 'buss_discount', 'B Disc from Std Prc ranges in Std prc vs dil. Full Disc % at every Std Prc, including under $15.', function(d) { return chStdBussDiscPct(d) || 0; }), {
                    visible: true,
                    minWidth: 64,
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d))) return '';
                        const pct = chStdBussDiscPct(d) || 0;
                        const std = chStdRowStd(d);
                        const tip = (std > 0 ? ('Std Prc $' + std.toFixed(2) + ' → ') : '') + 'B Disc ' + pct + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                Object.assign(chStdDiscCol('0 Sold', 'zero_sold_discount', '0 Sold discount from Std prc vs dil. Applies only when this page sold qty is 0. INV = 0 → 0%. Std Prc under $15 is 0.5×.', function(d) { return chStdZeroSoldDiscPct(d) || 0; }), { visible: true, minWidth: 64 }),
                Object.assign(chStdDiscCol('CVR Disc.', 'cvr_discount', 'CVR Disc — same as Amazon: A L30 ÷ Sess30, CVR 0 is Down. INV = 0 → 0%. Std Prc under $15 is 0.5×.', function(d) { return chStdScaleDisc(chStdRowStd(d), chStdCvrDiscPct(d)); }), {
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d))) return '';
                        const pct = chStdScaleDisc(chStdRowStd(d), chStdCvrDiscPct(d));
                        const cvr = chStdCvrLive(d);
                        const tip = 'CVR ' + (isFinite(cvr) ? cvr.toFixed(1) : '0') + '% ' + chStdCvrTrend(d) + ' → discount ' + (pct || 0) + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                chStdDiscCol('Rev Disc.', 'review_discount', 'Review discount from Std prc vs dil. Max reviews or above → 0.', chStdRevDiscPct),
                Object.assign(chStdDiscCol('ROI disc', 'roi_discount', 'ROI disc from GROI% slabs in Std prc vs dil. Std Prc under $15 is 0.5×.', function(d) { return chStdRoiDiscPct(d) || 0; }), {
                    visible: true,
                    minWidth: 64,
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        if (d.is_parent_summary || (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d))) return '';
                        const pct = chStdRoiDiscPct(d) || 0;
                        const roi = chStdRoiPct(d);
                        const tip = 'GROI ' + (isFinite(roi) ? roi.toFixed(0) : '0') + '% → discount ' + pct + '%';
                        return '<span title="' + chStdEsc(tip) + '">' + chStdDiscBadge(pct) + '</span>';
                    },
                }),
                chStdDiscCol('Sum disc', 'sum_discount', 'Age + Dil + B Disc + 0 Sold + CVR + Rev + ROI. S PRC = Std Prc × (1 − Sum disc / 100).', function(d) { return chStdSumDisc(d); }),
            ];
        }
        function chStdAppendDiscColumns(cols) {
            const out = Array.isArray(cols) ? cols.slice() : [];
            const have = {};
            out.forEach(function(c) { if (c && c.field) have[c.field] = 1; });
            chStdDiscColumns().forEach(function(c) {
                if (!have[c.field]) out.push(c);
            });
            return out;
        }
        function chStdPriceForRow(d, draft) {
            if (!d) return 0;
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return 0;
            if (!(chStdStockForAge(d) > 0)) return 0;
            const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
            if (!(std > 0)) return 0;
            const sum = chStdSumDisc(d, draft || chStdDraftNow());
            let raw = std * (1 - Math.min(99.99, sum) / 100);
            if (typeof CHANNEL_PROMO_CHANNEL !== 'undefined' && CHANNEL_PROMO_CHANNEL === 'shopify_b2b') {
                const ship = (typeof chPromoShipCost === 'function') ? (Number(chPromoShipCost(d)) || 0) : 0;
                raw -= ship;
            }
            if (!(raw > 0)) return 0;
            return (typeof chPromoRoundChannelSprice === 'function')
                ? chPromoRoundChannelSprice(raw)
                : Math.round(raw * 100) / 100;
        }
        window.chStdPriceForRow = chStdPriceForRow;
        if (typeof channelPromoPricingColumns === 'function' && !channelPromoPricingColumns._chStd) {
            const chStdPrevPricingCols = channelPromoPricingColumns;
            channelPromoPricingColumns = function() { return chStdAppendDiscColumns(chStdPrevPricingCols()); };
            channelPromoPricingColumns._chStd = true;
            window.channelPromoPricingColumns = channelPromoPricingColumns;
        }
        if (typeof channelPromoAnalyticsColumns === 'function' && !channelPromoAnalyticsColumns._chStd) {
            const chStdPrevAnalyticsCols = channelPromoAnalyticsColumns;
            channelPromoAnalyticsColumns = function() { return chStdAppendDiscColumns(chStdPrevAnalyticsCols()); };
            channelPromoAnalyticsColumns._chStd = true;
            window.channelPromoAnalyticsColumns = channelPromoAnalyticsColumns;
        }
        let chStdBussPlaced = false;
        function chStdPlaceBussColumn() {
            if (chStdBussPlaced) return;
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let dil = null;
            try { dil = table.getColumn('dil_discount'); } catch (e) { dil = null; }
            if (!dil) return;
            let buss = null;
            try { buss = table.getColumn('buss_discount'); } catch (e) { buss = null; }
            if (!buss) {
                const spec = chStdDiscColumns().filter(function(c) { return c && c.field === 'buss_discount'; })[0];
                if (!spec) return;
                try { table.addColumn(spec, false, 'dil_discount'); } catch (err) { return; }
            } else if (typeof table.moveColumn === 'function') {
                try { table.moveColumn('buss_discount', 'dil_discount', true); } catch (err) { /* already beside Dil Disc */ }
            }
            try {
                const col = table.getColumn('buss_discount');
                if (col && typeof col.show === 'function') col.show();
            } catch (err) { /* ignore */ }
            chStdBussPlaced = true;
            setTimeout(function() {
                try {
                    const col = table.getColumn('buss_discount');
                    if (col && typeof col.show === 'function') col.show();
                } catch (err) { /* ignore */ }
            }, 1600);
        }
        window.chStdPlaceBussColumn = chStdPlaceBussColumn;
        let chStdZeroSoldPlaced = false;
        function chStdPlaceZeroSoldColumn() {
            if (chStdZeroSoldPlaced) return;
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let anchor = null;
            try { anchor = table.getColumn('buss_discount') || table.getColumn('dil_discount'); } catch (e) { anchor = null; }
            if (!anchor) return;
            const afterField = anchor.getField ? anchor.getField() : 'buss_discount';
            let zs = null;
            try { zs = table.getColumn('zero_sold_discount'); } catch (e) { zs = null; }
            if (!zs) {
                const spec = chStdDiscColumns().filter(function(c) { return c && c.field === 'zero_sold_discount'; })[0];
                if (!spec) return;
                try { table.addColumn(spec, false, afterField); } catch (err) { return; }
            } else if (typeof table.moveColumn === 'function') {
                try { table.moveColumn('zero_sold_discount', afterField, true); } catch (err) { /* already in place */ }
            }
            try {
                const col = table.getColumn('zero_sold_discount');
                if (col && typeof col.show === 'function') col.show();
            } catch (err) { /* ignore */ }
            chStdZeroSoldPlaced = true;
            setTimeout(function() {
                try {
                    const col = table.getColumn('zero_sold_discount');
                    if (col && typeof col.show === 'function') col.show();
                } catch (err) { /* ignore */ }
            }, 1600);
        }
        function chStdEnsureColumns() {
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let exists = false;
            try { exists = !!table.getColumn('sum_discount'); } catch (e) { exists = false; }
            if (!exists) {
                let anchor = null;
                try { anchor = table.getColumn('SPRICE') || table.getColumn('sprice'); } catch (e) { anchor = null; }
                chStdDiscColumns().forEach(function(col) {
                    try {
                        if (anchor) table.addColumn(col, true, anchor);
                        else table.addColumn(col);
                    } catch (err) { /* column already present */ }
                });
            }
            chStdPlaceBussColumn();
            chStdPlaceZeroSoldColumn();
            chStdPlaceRoiColumn();
        }
        let chStdRoiPlaced = false;
        function chStdPlaceRoiColumn() {
            if (chStdRoiPlaced) return;
            if (typeof table === 'undefined' || !table || typeof table.getColumn !== 'function' || typeof table.addColumn !== 'function') return;
            let anchor = null;
            try { anchor = table.getColumn('review_discount') || table.getColumn('cvr_discount') || table.getColumn('sum_discount'); } catch (e) { anchor = null; }
            if (!anchor) return;
            const afterField = anchor.getField ? anchor.getField() : 'review_discount';
            let roi = null;
            try { roi = table.getColumn('roi_discount'); } catch (e) { roi = null; }
            if (!roi) {
                const spec = chStdDiscColumns().filter(function(c) { return c && c.field === 'roi_discount'; })[0];
                if (!spec) return;
                const beforeSum = afterField === 'sum_discount';
                try { table.addColumn(spec, beforeSum, afterField); } catch (err) { return; }
            } else if (typeof table.moveColumn === 'function' && afterField !== 'roi_discount') {
                try { table.moveColumn('roi_discount', afterField, afterField !== 'sum_discount'); } catch (err) { /* already in place */ }
            }
            try {
                const col = table.getColumn('roi_discount');
                if (col && typeof col.show === 'function') col.show();
            } catch (err) { /* ignore */ }
            chStdRoiPlaced = true;
        }
        function chStdCatalog() {
            const bySku = {};
            const order = [];
            const take = function(d, row) {
                if (!d) return;
                if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                const sku = (typeof chPromoSku === 'function') ? chPromoSku(d) : '';
                if (!sku) return;
                if (bySku[sku]) {
                    if (row && !bySku[sku].row) bySku[sku].row = row;
                    return;
                }
                const item = { d: d, row: row || null, sku: sku };
                bySku[sku] = item;
                order.push(item);
            };
            const extra = (typeof window !== 'undefined' && Array.isArray(window.allTableData) && window.allTableData.length)
                ? window.allTableData
                : ((typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData : []);
            extra.forEach(function(d) { take(d, null); });
            if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(function(row, d) { take(d, row); });
            return order;
        }
        function chStdWritePrices(draft) {
            const updates = [];
            chStdCatalog().forEach(function(item) {
                const price = chStdPriceForRow(item.d, draft);
                if (!(price > 0)) return;
                const patch = (typeof chPromoSpricePatch === 'function')
                    ? chPromoSpricePatch(price)
                    : { SPRICE: price, sprice: price, has_custom_sprice: true };
                if (item.row && typeof chPromoPatchRowData === 'function') chPromoPatchRowData(item.row, patch);
                if (typeof chPromoPatchDatasetSprice === 'function') chPromoPatchDatasetSprice(item.sku, patch);
                updates.push({ sku: item.sku, sprice: price });
            });
            return updates;
        }
        function chStdSavePrices(updates) {
            if (typeof saveChannelSpriceBatch !== 'function' || !updates.length) {
                return $.Deferred().resolve().promise();
            }
            const size = 200;
            let chain = $.Deferred().resolve().promise();
            for (let i = 0; i < updates.length; i += size) {
                const chunk = updates.slice(i, i + size);
                chain = chain.then(function() { return saveChannelSpriceBatch(chunk, { skip_push: 1 }); });
            }
            return chain;
        }
        function chStdOwnsSprice() {
            if (document.getElementById('sprice-rule-switch')) {
                return typeof window.spriceActiveRule === 'function' && window.spriceActiveRule() !== 'dil';
            }
            return true;
        }
        function chStdWhenRuleSettled(fn) {
            if (!document.getElementById('sprice-rule-switch')) {
                fn();
                return;
            }
            let tries = 0;
            (function wait() {
                if (window.spriceActiveRuleReady && typeof window.spriceActiveRuleReady.then === 'function') {
                    window.spriceActiveRuleReady.then(fn);
                    return;
                }
                if (tries++ > 40) {
                    fn();
                    return;
                }
                setTimeout(wait, 50);
            })();
        }
        let chStdAutoApplied = false;
        let chStdAutoWaits = 0;
        function chStdScheduleAutoApply() {
            if (chStdAutoApplied) return;
            const extraN = (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData.length : 0;
            const tblN = (typeof table !== 'undefined' && table && typeof table.getDataCount === 'function') ? table.getDataCount() : 0;
            chStdEnsureColumns();
            if (!(extraN > 0) && !(tblN > 0)) {
                if (chStdAutoWaits++ < 120) setTimeout(chStdScheduleAutoApply, 500);
                return;
            }
            $.when(chStdLoad(), chStdLoadAgeMap(), chStdLoadAmzMap()).always(function() {
                chStdWhenRuleSettled(function() {
                    if (chStdAutoApplied) return;
                    chStdAutoApplied = true;
                    chStdEnsureColumns();
                    chStdRepaintDiscColumns();
                    if (!chStdOwnsSprice()) return;
                    const updates = chStdWritePrices(chStdDraftNow());
                const redraw = function() {
                    if (typeof table !== 'undefined' && table && typeof table.redraw === 'function') {
                        try { table.redraw(true); } catch (e) { /* ignore */ }
                    }
                    if (typeof updateSummary === 'function') updateSummary();
                };
                chStdSavePrices(updates).always(function() {
                    redraw();
                    if (updates.length && typeof chPromoToast === 'function') {
                        chPromoToast('success', 'S PRC set from Std prc vs dil (' + updates.length + ')');
                    }
                });
                });
            });
        }
        function chStdApply() {
            const draft = chStdReadDraft();
            if (!draft.dil.length || !draft.age.length) {
                if (typeof chPromoToast === 'function') chPromoToast('error', 'Dil and Age need at least one range');
                return;
            }
            $('#ch-sp-status').text('Saving…');
            $.ajax({
                url: CH_PROMO_RULES_BASE + '/std-prc-vs-dil',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), 'Accept': 'application/json' },
                data: { _token: (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), dil: draft.dil, age: draft.age, cvr: draft.cvr, reviews: draft.reviews, review_max: draft.reviewMax, buss: draft.buss, roi: draft.roi, zero_sold_disc: draft.zeroSoldDisc },
            }).done(function(res) {
                if (res && res.dil) chStdDil = res.dil.map(chStdNormRange).filter(Boolean);
                if (res && res.age) chStdAge = res.age.map(chStdNormRange).filter(Boolean);
                if (res && res.reviews) chStdRev = res.reviews.map(chStdNormRange).filter(Boolean);
                if (res && res.buss) chStdBuss = res.buss.map(chStdNormRange).filter(Boolean);
                if (res && res.roi) chStdRoi = res.roi.map(chStdNormRange).filter(Boolean);
                if (res && isFinite(Number(res.zero_sold_disc))) chStdZeroSoldDisc = Math.min(100, Math.max(0, Number(res.zero_sold_disc)));
                if (res && res.cvr) chStdCvr = chStdNormCvr(res.cvr);
                if (res && res.review_max) chStdReviewMax = parseInt(res.review_max, 10) || 4;
                if (!chStdOwnsSprice()) {
                    $('#ch-sp-status').text('Saved. S PRC follows the ON rule.');
                    chStdRefresh();
                    chStdSaveHistory();
                    return;
                }
                const updates = chStdWritePrices(draft);
                const done = function() {
                    chStdAutoApplied = true;
                    $('#ch-sp-status').text('Saved. S PRC = Std Prc − Sum disc on ' + updates.length + ' SKU(s).');
                    chStdRefresh();
                    chStdSaveHistory();
                    if (typeof chPromoToast === 'function') chPromoToast('success', 'S PRC applied from Std prc vs dil');
                };
                const pending = chStdSavePrices(updates);
                if (pending && typeof pending.always === 'function') pending.always(done);
                else done();
            }).fail(function() {
                $('#ch-sp-status').text('Save failed');
                if (typeof chPromoToast === 'function') chPromoToast('error', 'Could not save Std prc vs dil');
            });
        }
        function bindChStdPrcUi() {
            $('#ch-std-prc-btn').off('click.chstd').on('click.chstd', function(e) {
                e.preventDefault();
                chStdLoad().always(function() {
                    chStdPaint();
                    const el = document.getElementById('chStdPrcModal');
                    if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
                });
            });
            $('#chStdPrcModal').off('shown.bs.modal.chstd').on('shown.bs.modal.chstd', function() {
                chStdRefresh();
                chStdSaveHistory();
            });
            $('#chStdPrcModal').off('hidden.bs.modal.chstd').on('hidden.bs.modal.chstd', function() {
                chStdPieGen++;
                $('#ch-sp-hist-wrap').removeClass('is-open');
                if (chStdHistChart) { chStdHistChart.destroy(); chStdHistChart = null; }
                chStdRepaintDiscColumns();
            });
            $('#ch-sp-apply').off('click.chstd').on('click.chstd', function(e) { e.preventDefault(); chStdApply(); });
            $('#ch-sp-dil-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-dil-tbody', '.ch-sp-dil-min', '.ch-sp-dil-max', '.ch-sp-dil-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                $('#ch-sp-dil-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-dil', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-age-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-age-tbody', '.ch-sp-age-min', '.ch-sp-age-max', '.ch-sp-age-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last + 1, max: last + 30, disc: 0 });
                $('#ch-sp-age-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-age', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-rev-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-rev-tbody', '.ch-sp-rev-min', '.ch-sp-rev-max', '.ch-sp-rev-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 1, disc: 4 });
                $('#ch-sp-rev-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-rev', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-buss-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-buss-tbody', '.ch-sp-buss-min', '.ch-sp-buss-max', '.ch-sp-buss-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 10, disc: 0 });
                $('#ch-sp-buss-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-buss', r); }).join(''));
                chStdRefresh();
            });
            $('#ch-sp-roi-add').off('click.chstd').on('click.chstd', function() {
                const rules = chStdReadRanges('#ch-sp-roi-tbody', '.ch-sp-roi-min', '.ch-sp-roi-max', '.ch-sp-roi-disc');
                const last = rules.length ? rules[rules.length - 1].max : 0;
                rules.push({ min: last, max: last + 25, disc: 0 });
                $('#ch-sp-roi-tbody').html(rules.map(function(r) { return chStdRangeRow('ch-sp-roi', r); }).join(''));
                chStdRefresh();
            });
            $(document).off('click.chstddel').on('click.chstddel', '#chStdPrcModal .ch-sp-dil-del, #chStdPrcModal .ch-sp-age-del, #chStdPrcModal .ch-sp-rev-del, #chStdPrcModal .ch-sp-buss-del, #chStdPrcModal .ch-sp-roi-del', function() {
                $(this).closest('tr').remove();
                chStdRefresh();
            });
            $(document).off('input.chstd').on('input.chstd', '#chStdPrcModal input', function() {
                clearTimeout(bindChStdPrcUi._t);
                bindChStdPrcUi._t = setTimeout(chStdRefresh, 180);
            });
            $(document).off('click.chsthist', '#chStdPrcModal .ch-sp-hist-dot').on('click.chsthist', '#chStdPrcModal .ch-sp-hist-dot', function(e) {
                e.preventDefault();
                const $dot = $(this);
                chStdOpenHist(String($dot.attr('data-chart') || ''), String($dot.attr('data-band') || ''), String($dot.attr('data-label') || ''), String($dot.attr('data-color') || '#0f172a'));
            });
            $('#ch-sp-hist-close').off('click.chsthist').on('click.chsthist', function() { $('#ch-sp-hist-wrap').removeClass('is-open'); });
        }
        window.chStdApplyActivePrices = function() {
            if (!chStdOwnsSprice()) return;
            const updates = chStdWritePrices(chStdDraftNow());
            const redraw = function() {
                if (typeof table !== 'undefined' && table && typeof table.redraw === 'function') {
                    try { table.redraw(true); } catch (e) { /* ignore */ }
                }
                if (typeof updateSummary === 'function') updateSummary();
                if (typeof window.shopifyB2bRefreshSpriceCells === 'function') window.shopifyB2bRefreshSpriceCells();
            };
            const pending = chStdSavePrices(updates);
            if (pending && typeof pending.always === 'function') pending.always(redraw);
            else redraw();
        };
        $(function() {
            bindChStdPrcUi();
            chStdScheduleAutoApply();
        });
@endif
