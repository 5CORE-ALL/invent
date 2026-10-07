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
        #chStdPrcModal .ch-sp-cols { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; align-items: stretch; width: 100%; min-width: 0; }
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
        #chStdPrcModal .ch-sp-input { width: 100%; max-width: 58px; margin: 0 auto; display: inline-block; text-align: center; font-weight: 600; font-size: 12px; height: 28px; border-radius: 7px; }
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
                    <button type="button" class="btn btn-sm" id="ch-std-prc-btn" title="Std Prc minus Age, Dil, CVR, and Review discounts. Same slabs as Amazon.">
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
                        <div class="ch-sp-sub">S PRC = Std Prc − Age − Dil − CVR − Reviews. Same slabs as Amazon.</div>
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
        const CH_STD_COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b'];
        let chStdDil = CH_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdAge = CH_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
        let chStdRev = CH_STD_REV_DEFAULTS.map(function(r) { return Object.assign({}, r); });
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
        function chStdRangeDisc(value, rules) {
            const n = Number(value);
            if (!isFinite(n) || n < 0 || !rules || !rules.length) return 0;
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
        function chStdCvrLive(d) {
            const a = chStdNum(d, ['A_L30', 'a_l30', 'al30', 'AL30']);
            const sess = chStdNum(d, ['Sess30', 'sess30', 'sessions_l30', 'views']);
            if (sess > 0) return (a / sess) * 100;
            return (typeof chPromoCvr === 'function') ? (Number(chPromoCvr(d)) || 0) : 0;
        }
        function chStdCvrTrend(d) {
            const cvr = chStdCvrLive(d);
            const a30 = chStdNum(d, ['A_L30', 'a_l30', 'al30', 'AL30']);
            const s30 = chStdNum(d, ['Sess30', 'sess30', 'sessions_l30', 'views']);
            const a60 = chStdNum(d, ['units_ordered_l60', 'a_l60', 'al60']);
            const s60 = chStdNum(d, ['sessions_l60', 'sess60']);
            const sess45 = (s30 + s60) / 2;
            if (!(sess45 > 0)) return 'flat';
            const cvr45 = (((a30 + a60) / 2) / sess45) * 100;
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
        function chStdAgeDays(d) {
            const raw = d && (d.age_days != null && d.age_days !== '' ? d.age_days : d.age);
            const n = Number(raw);
            return isFinite(n) ? n : null;
        }
        function chStdReviews(d) {
            return chStdNum(d, ['review_count', 'reviews', 'Reviews', 'rating_count', 'ratings']);
        }
        function chStdSumDisc(d, draft) {
            draft = draft || { dil: chStdDil, age: chStdAge, cvr: chStdCvr, reviews: chStdRev, reviewMax: chStdReviewMax };
            if (typeof chPromoInv === 'function' && chPromoInv(d) <= 0) return 0;
            const dil = (typeof chPromoDil === 'function') ? chPromoDil(d) : 0;
            const ageDisc = chStdRangeDisc(chStdAgeDays(d), draft.age);
            const dilDisc = chStdRangeDisc(dil, draft.dil);
            const reviews = chStdReviews(d);
            let revDisc = 0;
            if (reviews > 0 && reviews <= draft.reviewMax) {
                for (let i = 0; i < draft.reviews.length; i++) {
                    const rule = draft.reviews[i];
                    if (reviews >= rule.min && reviews <= rule.max) { revDisc = Number(rule.disc) || 0; break; }
                }
            }
            const slab = (typeof chPromoCvrDiscForRow === 'function') ? (Number(chPromoCvrDiscForRow(d)) || 0) : 0;
            const hit = chStdCvrMatch(d, draft.cvr);
            const trend = hit ? (Number(hit.disc) || 0) : (Number(draft.cvr.flat_disc) || 0);
            return Math.min(99.99, Math.max(0, ageDisc + dilDisc + Math.max(0, slab + (trend > 0 ? trend : 0)) + revDisc));
        }
        function chStdRows() {
            const out = [];
            const take = function(d) {
                if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                if (typeof chPromoInv === 'function' && chPromoInv(d) <= 0) return;
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
            const dilCounts = { outside: 0 }, ageCounts = { none: 0 }, revCounts = { none: 0 };
            const cvrCounts = { down2: 0, down: 0, flat: 0, up: 0, up2: 0 };
            draft.dil.forEach(function(r, i) { dilCounts['d' + i] = 0; });
            draft.age.forEach(function(r, i) { ageCounts['a' + i] = 0; });
            draft.reviews.forEach(function(r, i) { revCounts['r' + i] = 0; });
            const dollars = { age: 0, dil: 0, cvr: 0, rev: 0 };
            const pctTotals = { age: 0, dil: 0, cvr: 0, rev: 0 };
            const skuHits = { age: 0, dil: 0, cvr: 0, rev: 0, all: 0 };
            const l30 = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const invB = { gross: 0, net: 0, sales: 0, cogs: 0, units: 0 };
            const ads = (typeof chPromoAdsFrac === 'function') ? ((Number(chPromoAdsFrac()) || 0) * 100) : 0;
            chStdRows().forEach(function(d) {
                const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
                const dilVal = (typeof chPromoDil === 'function') ? chPromoDil(d) : 0;
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
                if (reviews > 0 && reviews <= draft.reviewMax) {
                    for (let i = 0; i < draft.reviews.length; i++) {
                        if (reviews >= draft.reviews[i].min && reviews <= draft.reviews[i].max) { revIdx = i; break; }
                    }
                }
                if (revIdx >= 0) revCounts['r' + revIdx] += 1; else revCounts.none += 1;
                const hit = chStdCvrMatch(d, draft.cvr);
                cvrCounts[hit ? hit.key : 'flat'] += 1;
                const ageDisc = ageIdx >= 0 ? (Number(draft.age[ageIdx].disc) || 0) : 0;
                const dilDisc = dilIdx >= 0 ? (Number(draft.dil[dilIdx].disc) || 0) : 0;
                const revDisc = revIdx >= 0 ? (Number(draft.reviews[revIdx].disc) || 0) : 0;
                const slab = (typeof chPromoCvrDiscForRow === 'function') ? (Number(chPromoCvrDiscForRow(d)) || 0) : 0;
                const trend = hit ? (Number(hit.disc) || 0) : (Number(draft.cvr.flat_disc) || 0);
                const cvrDisc = Math.max(0, slab + (trend > 0 ? trend : 0));
                if (ageDisc > 0) { skuHits.age++; dollars.age += std * ageDisc / 100; pctTotals.age += ageDisc; }
                if (dilDisc > 0) { skuHits.dil++; dollars.dil += std * dilDisc / 100; pctTotals.dil += dilDisc; }
                if (cvrDisc > 0) { skuHits.cvr++; dollars.cvr += std * cvrDisc / 100; pctTotals.cvr += cvrDisc; }
                if (revDisc > 0) { skuHits.rev++; dollars.rev += std * revDisc / 100; pctTotals.rev += revDisc; }
                const sum = Math.min(99.99, ageDisc + dilDisc + cvrDisc + revDisc);
                if (sum > 0) skuHits.all++;
                if (std > 0) {
                    const sprice = Math.round(std * (1 - sum / 100) * 100) / 100;
                    const lp = (typeof chPromoLp === 'function') ? chPromoLp(d) : 0;
                    const ship = (typeof chPromoShipCost === 'function') ? chPromoShipCost(d) : 0;
                    const gross = (sprice * chStdMargin(d)) - ship - lp;
                    const net = gross - (sprice * ads / 100);
                    const units = (typeof chPromoOvL30 === 'function') ? chPromoOvL30(d) : 0;
                    const inv = (typeof chPromoInv === 'function') ? chPromoInv(d) : 0;
                    l30.gross += gross * units; l30.net += net * units; l30.sales += sprice * units; l30.cogs += lp * units; l30.units += units;
                    invB.gross += gross * inv; invB.net += net * inv; invB.sales += sprice * inv; invB.cogs += lp * inv; invB.units += inv;
                }
            });
            ['dil', 'age', 'rev'].forEach(function(prefix) {
                const counts = prefix === 'dil' ? dilCounts : (prefix === 'age' ? ageCounts : revCounts);
                $('#ch-sp-' + prefix + '-tbody tr').each(function(i) { $(this).find('.ch-sp-' + prefix + '-count').text(counts[(prefix === 'dil' ? 'd' : prefix === 'age' ? 'a' : 'r') + i] || 0); });
            });
            $('#ch-sp-cvr-down2-count').text(cvrCounts.down2);
            $('#ch-sp-cvr-down-count').text(cvrCounts.down);
            $('#ch-sp-cvr-flat-count').text(cvrCounts.flat);
            $('#ch-sp-cvr-up-count').text(cvrCounts.up);
            $('#ch-sp-cvr-up2-count').text(cvrCounts.up2);
            const dilSlices = draft.dil.map(function(r, i) { return { key: 'd' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'outside', label: 'Outside', color: '#cbd5e1' }]);
            const ageSlices = draft.age.map(function(r, i) { return { key: 'a' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'none', label: 'No age', color: '#cbd5e1' }]);
            const revSlices = draft.reviews.map(function(r, i) { return { key: 'r' + i, label: r.min + '–' + r.max, color: CH_STD_COLORS[i % CH_STD_COLORS.length] }; }).concat([{ key: 'none', label: 'No disc', color: '#cbd5e1' }]);
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
            ];
            const allCounts = { age: Math.round(dollars.age), dil: Math.round(dollars.dil), cvr: Math.round(dollars.cvr), rev: Math.round(dollars.rev) };
            chStdHistLive = {};
            chStdLegend('#ch-sp-leg-dil', dilSlices, dilCounts, 'dil');
            chStdLegend('#ch-sp-leg-rev', revSlices, revCounts, 'rev');
            chStdLegend('#ch-sp-leg-cvr', cvrSlices, cvrCounts, 'cvr');
            chStdLegend('#ch-sp-leg-age', ageSlices, ageCounts, 'age');
            chStdLegend('#ch-sp-leg-all', allSlices, allCounts, 'all');
            chStdDrawBars([
                { id: 'ch-sp-pie-dil', slices: dilSlices, counts: dilCounts },
                { id: 'ch-sp-pie-rev', slices: revSlices, counts: revCounts },
                { id: 'ch-sp-pie-cvr', slices: cvrSlices, counts: cvrCounts },
                { id: 'ch-sp-pie-age', slices: ageSlices, counts: ageCounts },
                { id: 'ch-sp-pie-all', slices: allSlices, counts: allCounts },
            ]);
            let pctSum = 0, dollarSum = 0;
            const body = [['Age', skuHits.age, pctTotals.age, dollars.age], ['Dil', skuHits.dil, pctTotals.dil, dollars.dil], ['CVR', skuHits.cvr, pctTotals.cvr, dollars.cvr], ['Reviews', skuHits.rev, pctTotals.rev, dollars.rev]].map(function(row) {
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
        }
        function chStdPaint() {
            $('#ch-sp-dil-tbody').html(chStdDil.map(function(r) { return chStdRangeRow('ch-sp-dil', r); }).join(''));
            $('#ch-sp-age-tbody').html(chStdAge.map(function(r) { return chStdRangeRow('ch-sp-age', r); }).join(''));
            $('#ch-sp-rev-tbody').html(chStdRev.map(function(r) { return chStdRangeRow('ch-sp-rev', r); }).join(''));
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
                chStdCvr = chStdNormCvr(res.cvr);
                chStdReviewMax = parseInt(res.review_max, 10) || 4;
                if (!chStdDil.length) chStdDil = CH_STD_DIL_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdAge.length) chStdAge = CH_STD_AGE_DEFAULTS.map(function(r) { return Object.assign({}, r); });
                if (!chStdRev.length) chStdRev = CH_STD_REV_DEFAULTS.map(function(r) { return Object.assign({}, r); });
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
            return { dil: chStdDil, age: chStdAge, cvr: chStdCvr, reviews: chStdRev, reviewMax: chStdReviewMax };
        }
        function chStdPriceForRow(d, draft) {
            if (!d) return 0;
            if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return 0;
            if (typeof chPromoInv === 'function' && chPromoInv(d) <= 0) return 0;
            const std = (typeof chPromoStdBase === 'function') ? chPromoStdBase(d) : 0;
            if (!(std > 0)) return 0;
            const sum = chStdSumDisc(d, draft || chStdDraftNow());
            const raw = std * (1 - Math.min(99.99, sum) / 100);
            if (!(raw > 0)) return 0;
            return (typeof chPromoRoundChannelSprice === 'function')
                ? chPromoRoundChannelSprice(raw)
                : Math.round(raw * 100) / 100;
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
        let chStdAutoApplied = false;
        let chStdAutoWaits = 0;
        function chStdScheduleAutoApply() {
            if (chStdAutoApplied) return;
            const extraN = (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData.length : 0;
            const tblN = (typeof table !== 'undefined' && table && typeof table.getDataCount === 'function') ? table.getDataCount() : 0;
            if (!(extraN > 0) && !(tblN > 0)) {
                if (chStdAutoWaits++ < 40) setTimeout(chStdScheduleAutoApply, 500);
                return;
            }
            chStdLoad().always(function() {
                if (chStdAutoApplied) return;
                chStdAutoApplied = true;
                const updates = chStdWritePrices(chStdDraftNow());
                chStdSavePrices(updates).always(function() {
                    if (updates.length && typeof chPromoToast === 'function') {
                        chPromoToast('success', 'S PRC set from Std prc vs dil (' + updates.length + ')');
                    }
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
                data: { _token: (typeof chPromoCsrf === 'function' ? chPromoCsrf() : ''), dil: draft.dil, age: draft.age, cvr: draft.cvr, reviews: draft.reviews, review_max: draft.reviewMax },
            }).done(function(res) {
                if (res && res.dil) chStdDil = res.dil.map(chStdNormRange).filter(Boolean);
                if (res && res.age) chStdAge = res.age.map(chStdNormRange).filter(Boolean);
                if (res && res.reviews) chStdRev = res.reviews.map(chStdNormRange).filter(Boolean);
                if (res && res.cvr) chStdCvr = chStdNormCvr(res.cvr);
                if (res && res.review_max) chStdReviewMax = parseInt(res.review_max, 10) || 4;
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
            $(document).off('click.chstddel').on('click.chstddel', '#chStdPrcModal .ch-sp-dil-del, #chStdPrcModal .ch-sp-age-del, #chStdPrcModal .ch-sp-rev-del', function() {
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
        $(function() {
            bindChStdPrcUi();
            chStdScheduleAutoApply();
        });
@endif
