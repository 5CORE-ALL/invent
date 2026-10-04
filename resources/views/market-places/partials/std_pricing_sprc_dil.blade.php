@php $stdSprcDilPart = $stdSprcDilPart ?? 'all'; @endphp

@if($stdSprcDilPart === 'css' || $stdSprcDilPart === 'all')
        #std-sprc-dil-btn {
            background: #6f42c1;
            border-color: #6f42c1;
            color: #fff;
        }
        #std-sprc-dil-btn:hover,
        #std-sprc-dil-btn:focus {
            background: #5a32a3;
            border-color: #5a32a3;
            color: #fff;
        }
        #stdDilGroiModal .std-dg-pies { display: flex; gap: 10px; margin: 0 0 10px; flex-wrap: wrap; }
        #stdDilGroiModal .std-dg-pie-wrap {
            display: flex; align-items: flex-start; gap: 10px; flex: 1 1 280px; min-width: 260px;
            padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;
        }
        #stdDilGroiModal .std-dg-pie-canvas-wrap { width: 140px; height: 140px; flex: 0 0 140px; }
        #stdDilGroiModal .std-dg-pie-legend { flex: 1 1 auto; min-width: 0; max-height: 168px; overflow-y: auto; }
        #stdDilGroiModal .std-dg-pie-row { display: flex; align-items: center; gap: 6px; font-size: 11px; line-height: 1.3; padding: 1px 0; }
        #stdDilGroiModal .std-dg-pie-swatch { width: 8px; height: 8px; border-radius: 2px; flex: 0 0 8px; }
        #stdDilGroiModal .std-dg-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        #stdDilGroiModal .std-dg-pie-count { font-weight: 700; min-width: 24px; text-align: right; }
        #stdDilGroiModal .std-dg-pie-pct { color: #64748b; min-width: 24px; text-align: right; }
        #std-dil-groi-table .std-dg-input { max-width: 90px; text-align: right; font-weight: 600; }
        #std-dil-groi-table .std-dg-min, #std-dil-groi-table .std-dg-max { margin-left: 0; }
        #std-dil-groi-table .std-dg-count { font-weight: 700; text-align: center; white-space: nowrap; }
        #std-dil-groi-table .std-dg-del { border: none; background: none; color: #dc3545; cursor: pointer; line-height: 1; }
        #std-dil-groi-table tr[data-clearance] .std-dg-clearance-count,
        #std-dil-groi-table tr[data-clearance] .std-dg-clearance-nroi { color: #dc3545; font-weight: 700; }
        #std-cvr-groi-table .std-cvr-input { max-width: 72px; display: inline-block; text-align: right; font-weight: 600; }
        #stdDilGroiModal .std-dg-rules { margin: 0 0 10px; padding-left: 1.15rem; }
        #stdDilGroiModal .std-dg-rules li { margin-bottom: 0.28rem; }
        #stdDilGroiModal .std-dg-rules-title { margin: 0 0 4px; font-size: 12px; font-weight: 700; color: #334155; }
        .std-sprc-dil-price { font-weight: 700; color: #6f42c1; }
@endif

@if($stdSprcDilPart === 'button' || $stdSprcDilPart === 'all')
                        <button type="button" class="btn btn-sm" id="std-sprc-dil-btn"
                            title="Dil slabs → Target NROI%. Saved in std_pricing_sprc_dil, not Amazon.">
                            <i class="fas fa-sliders-h"></i> Sprc Dil
                        </button>
@endif

@if($stdSprcDilPart === 'modal' || $stdSprcDilPart === 'all')
    <div class="modal fade" id="stdDilGroiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6">
                        <i class="fas fa-sliders-h me-1"></i> Dil vs Target NROI — Sprc Dil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <div class="std-dg-pies">
                        <div class="std-dg-pie-wrap">
                            <div class="std-dg-pie-canvas-wrap"><canvas id="std-dg-dil-pie"></canvas></div>
                            <div class="std-dg-pie-legend" id="std-dg-dil-legend"></div>
                        </div>
                        <div class="std-dg-pie-wrap">
                            <div class="std-dg-pie-canvas-wrap"><canvas id="std-dg-cvr-pie"></canvas></div>
                            <div class="std-dg-pie-legend" id="std-dg-cvr-legend"></div>
                        </div>
                    </div>
                    <div class="std-dg-rules-title">Rules — when each condition applies</div>
                    <ul class="small text-muted std-dg-rules">
                        <li><strong>When</strong> Dil = 0 (INV &gt; 0 and ovl30 = 0): use the <strong>0–0</strong> slab’s Target NROI.</li>
                        <li><strong>When</strong> Dil sits in a From–To range (INV &gt; 0): use that slab’s Target NROI. Sprc Dil = (LP × (1 + NROI%/100) + Ship) / (0.80 − 10%).</li>
                        <li><strong>When</strong> you change the first Target NROI%: later rows fill as first +5, +10, …</li>
                        <li><strong>When</strong> you click <strong>Save and Apply</strong>: rules are stored in <strong>std_pricing_sprc_dil</strong> (not Amazon), then Sprc Dil is written on this table.</li>
                        <li><strong>When</strong> INV ≤ 0: count and pies skip that SKU.</li>
                        <li><strong>Clearance</strong> count: INV &gt; 0 and Clearance is Yes on Inv Days.</li>
                    </ul>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="std-dil-groi-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:90px;">From</th>
                                    <th class="text-center" style="width:90px;">To</th>
                                    <th class="text-center" style="width:80px;">Count</th>
                                    <th class="text-end" style="width:130px;">Target NROI%</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="std-dil-groi-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="std-dil-groi-add-btn">
                        <i class="fas fa-plus me-1"></i> Add slab
                    </button>
                    <div class="std-dg-rules-title mt-3">Use price — Std Price or LMP</div>
                    <p class="small text-muted mb-2">
                        When Dil falls in a row, Use Price is the lower of <strong>Std Price</strong> and <strong>LMP × factor</strong>.
                        LMP is the lowest of Amz, eBay, Temu, and Google. If none is found, My LMP from LMP Overall is used.
                        A blank To means Dil above From. 50 belongs to the 50–100 row, and Dil above 100 uses 1.05.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="std-lmp-cap-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:90px;">From</th>
                                    <th class="text-center" style="width:90px;">To</th>
                                    <th class="text-end" style="width:110px;">LMP ×</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="std-lmp-cap-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="std-lmp-cap-add-btn">
                        <i class="fas fa-plus me-1"></i> Add range
                    </button>
                    <div class="std-dg-rules-title mt-3">CVR overlay — Target NROI</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0" id="std-cvr-groi-table">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th class="text-center">CVR%</th>
                                    <th class="text-end">Adj NROI</th>
                                    <th class="text-center" style="width:80px;">Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Down</td>
                                    <td class="text-center">&lt; <input type="number" min="0" step="0.1" class="form-control form-control-sm std-cvr-input std-cvr-down-lt" value="7"></td>
                                    <td class="text-end"><input type="number" step="1" class="form-control form-control-sm std-cvr-input std-cvr-down-adj" value="-10"></td>
                                    <td class="text-center std-dg-count"><span class="std-cvr-down-count">0</span></td>
                                </tr>
                                <tr>
                                    <td>Up</td>
                                    <td class="text-center">&gt; <input type="number" min="0" step="0.1" class="form-control form-control-sm std-cvr-input std-cvr-up-gt" value="10"></td>
                                    <td class="text-end"><input type="number" step="1" class="form-control form-control-sm std-cvr-input std-cvr-up-adj" value="10"></td>
                                    <td class="text-center std-dg-count"><span class="std-cvr-up-count">0</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2" id="std-dil-groi-status"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-primary" id="std-dil-groi-save-btn">
                        <i class="fas fa-save me-1"></i> Save and Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($stdSprcDilPart === 'script' || $stdSprcDilPart === 'all')
    @include('partials.lazy-chart-js')
    <script>
        (function () {
            const COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c'];
            const MARGIN = 0.80;
            const ADS = 0.10;
            let rules = [];
            let lmpRules = [
                { min: 25, max: 50, factor: 0.95, above: false },
                { min: 50, max: 100, factor: 1, above: false },
                { min: 100, max: null, factor: 1.05, above: true },
            ];
            let clearanceNroi = 0;
            let clearanceSet = null;
            let dilChart = null;
            let cvrChart = null;

            function round2(n) { return Math.round((Number(n) || 0) * 100) / 100; }
            function fmtNum(n) {
                return round2(n).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            }
            function csrf() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }
            function skuKey(sku) {
                return String(sku || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
            }
            function normalizeRule(raw) {
                if (!raw) return null;
                let min = Number(raw.min);
                let max = Number(raw.max);
                if (!isFinite(min) || !isFinite(max)) return null;
                if (min < 0 || max < min) return null;
                min = round2(min);
                max = round2(max);
                let groi = Number(raw.nroi != null && raw.nroi !== '' ? raw.nroi : raw.groi);
                if (!isFinite(groi) || groi < 0) groi = 0;
                groi = round2(groi);
                return { key: fmtNum(min) + '-' + fmtNum(max), label: fmtNum(min) + '–' + fmtNum(max) + '%', min: min, max: max, groi: groi, nroi: groi };
            }
            function normalizeList(list) {
                const out = [];
                (Array.isArray(list) ? list : []).forEach(function (item) {
                    const rule = normalizeRule(item);
                    if (rule) out.push(rule);
                });
                out.sort(function (a, b) { return a.min - b.min || a.max - b.max; });
                return out;
            }
            function inSlab(n, rule, isLast) {
                if (!rule) return false;
                if (rule.max === rule.min) return round2(n) === rule.min;
                return n >= rule.min && (isLast ? n <= rule.max : n < rule.max);
            }
            function matchRule(dil, list) {
                const n = Number(dil);
                if (!isFinite(n) || n < 0 || !list.length) return null;
                const last = list.length - 1;
                for (let i = 0; i < list.length; i++) {
                    if (inSlab(n, list[i], i === last)) return list[i];
                }
                return null;
            }
            function cvrAdjNow() {
                return {
                    down_lt: round2(parseFloat(document.querySelector('.std-cvr-down-lt')?.value) || 7),
                    down_adj: round2(parseFloat(document.querySelector('.std-cvr-down-adj')?.value) || -10),
                    up_gt: round2(parseFloat(document.querySelector('.std-cvr-up-gt')?.value) || 10),
                    up_adj: round2(parseFloat(document.querySelector('.std-cvr-up-adj')?.value) || 10),
                };
            }
            function paintCvr(cfg) {
                cfg = cfg || {};
                const downLt = document.querySelector('.std-cvr-down-lt');
                const downAdj = document.querySelector('.std-cvr-down-adj');
                const upGt = document.querySelector('.std-cvr-up-gt');
                const upAdj = document.querySelector('.std-cvr-up-adj');
                if (downLt && cfg.down_lt != null) downLt.value = cfg.down_lt;
                if (downAdj && cfg.down_adj != null) downAdj.value = cfg.down_adj;
                if (upGt && cfg.up_gt != null) upGt.value = cfg.up_gt;
                if (upAdj && cfg.up_adj != null) upAdj.value = cfg.up_adj;
            }
            function rowDil(d) {
                const inv = parseFloat(d.inv) || 0;
                if (!(inv > 0)) return 0;
                return ((parseFloat(d.ovl30) || 0) / inv) * 100;
            }
            function eachInvRow(fn) {
                const table = window.stdPricingTable;
                const rows = table && typeof table.getData === 'function' ? (table.getData('all') || []) : [];
                const seen = {};
                rows.forEach(function (d) {
                    if (!((parseFloat(d.inv) || 0) > 0)) return;
                    const key = skuKey(d.sku);
                    if (key) {
                        if (seen[key]) return;
                        seen[key] = true;
                    }
                    fn(d);
                });
            }
            function displayRules() {
                const fromDom = [];
                document.querySelectorAll('#std-dil-groi-tbody tr:not([data-clearance])').forEach(function (tr) {
                    const rule = normalizeRule({
                        min: parseFloat(tr.querySelector('.std-dg-min')?.value),
                        max: parseFloat(tr.querySelector('.std-dg-max')?.value),
                        groi: parseFloat(tr.querySelector('.std-dg-groi')?.value),
                    });
                    if (rule) fromDom.push(rule);
                });
                return fromDom.length ? fromDom : normalizeList(rules);
            }
            function collectCounts(list) {
                const counts = { _outside: 0 };
                list.forEach(function (r) { counts[r.key] = 0; });
                eachInvRow(function (d) {
                    const rule = matchRule(rowDil(d), list);
                    if (rule) counts[rule.key] = (counts[rule.key] || 0) + 1;
                    else counts._outside++;
                });
                return counts;
            }
            function legendHtml(title, slices, counts) {
                const total = slices.reduce(function (sum, s) { return sum + (counts[s.key] || 0); }, 0);
                return '<div class="std-dg-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                    + '<span class="std-dg-pie-swatch" style="visibility:hidden;"></span>'
                    + '<span class="std-dg-pie-name">' + title + '</span>'
                    + '<span class="std-dg-pie-count">count</span>'
                    + '<span class="std-dg-pie-pct">of total</span></div>'
                    + slices.map(function (s) {
                        const n = counts[s.key] || 0;
                        const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                        return '<div class="std-dg-pie-row"><span class="std-dg-pie-swatch" style="background:' + s.color + ';"></span>'
                            + '<span class="std-dg-pie-name">' + s.label + '</span>'
                            + '<span class="std-dg-pie-count">' + n + '</span>'
                            + '<span class="std-dg-pie-pct">' + pct + '</span></div>';
                    }).join('');
            }
            function drawPie(canvasId, which, slices, counts) {
                const draw = function () {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas || typeof Chart === 'undefined') return;
                    if (which === 'dil' && dilChart) { dilChart.destroy(); dilChart = null; }
                    if (which === 'cvr' && cvrChart) { cvrChart.destroy(); cvrChart = null; }
                    const chart = new Chart(canvas.getContext('2d'), {
                        type: 'pie',
                        data: {
                            labels: slices.map(function (s) { return s.label; }),
                            datasets: [{
                                data: slices.map(function (s) { return counts[s.key] || 0; }),
                                backgroundColor: slices.map(function (s) { return s.color; }),
                                borderColor: '#fff',
                                borderWidth: 1,
                            }],
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
                    });
                    if (which === 'dil') dilChart = chart;
                    else cvrChart = chart;
                };
                if (typeof window.loadChartJs === 'function') window.loadChartJs().then(draw).catch(function () {});
                else if (typeof Chart !== 'undefined') draw();
            }
            function cvrBands() {
                const cfg = cvrAdjNow();
                const mid = cfg.down_lt + '–' + cfg.up_gt;
                return [
                    { key: 'down-lt', label: 'Down · < ' + cfg.down_lt + '% (' + cfg.down_adj + ' NROI)', color: '#dc3545' },
                    { key: 'flat-lt', label: 'Flat · < ' + cfg.down_lt + '%', color: '#94a3b8' },
                    { key: 'flat-mid', label: 'Flat · ' + mid + '%', color: '#64748b' },
                    { key: 'up-gt', label: 'UP · > ' + cfg.up_gt + '% (+' + cfg.up_adj + ' NROI)', color: '#198754' },
                ];
            }
            function renderPies() {
                const list = displayRules();
                const dilCounts = collectCounts(list);
                const dilSlices = list.map(function (r, i) {
                    return { key: r.key, label: r.label, color: COLORS[i % COLORS.length] };
                });
                if ((dilCounts._outside || 0) > 0) dilSlices.push({ key: '_outside', label: 'Outside', color: '#cbd5e1' });
                const dilLegend = document.getElementById('std-dg-dil-legend');
                if (dilLegend) dilLegend.innerHTML = legendHtml('Dil', dilSlices, dilCounts);
                drawPie('std-dg-dil-pie', 'dil', dilSlices, dilCounts);
                document.querySelectorAll('#std-dil-groi-tbody tr:not([data-clearance])').forEach(function (tr, i) {
                    const cell = tr.querySelector('.std-dg-count-n');
                    if (cell && list[i]) cell.textContent = String(dilCounts[list[i].key] || 0);
                });
                const bands = cvrBands();
                const cvrCounts = {};
                bands.forEach(function (b) { cvrCounts[b.key] = 0; });
                eachInvRow(function (d) {
                    const cvr = parseFloat(d.cvr);
                    const trend = String(d.cvr_trend || '');
                    if (!isFinite(cvr) || (trend !== 'down' && trend !== 'up' && trend !== 'flat')) return;
                    const cfg = cvrAdjNow();
                    let key = 'flat-mid';
                    if (trend === 'down' && cvr < cfg.down_lt) key = 'down-lt';
                    else if (trend === 'up' && cvr > cfg.up_gt) key = 'up-gt';
                    else if (cvr < cfg.down_lt) key = 'flat-lt';
                    cvrCounts[key] = (cvrCounts[key] || 0) + 1;
                });
                const cvrLegend = document.getElementById('std-dg-cvr-legend');
                if (cvrLegend) cvrLegend.innerHTML = legendHtml('CVR Up / Down · CVR%', bands, cvrCounts);
                drawPie('std-dg-cvr-pie', 'cvr', bands, cvrCounts);
                paintClearance();
            }
            function ensureClearance() {
                if (clearanceSet) return Promise.resolve(clearanceSet);
                return fetch(@json(route('inv.days.clearance.yes')), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const set = {};
                        (data.skus || []).forEach(function (sku) { set[skuKey(sku)] = true; });
                        clearanceSet = set;
                        return set;
                    })
                    .catch(function () { clearanceSet = {}; return clearanceSet; });
            }
            function paintClearance() {
                const el = document.querySelector('#std-dil-groi-tbody .std-dg-clearance-count');
                const nroiEl = document.querySelector('#std-dil-groi-tbody .std-dg-clearance-nroi');
                const raw = parseFloat(document.querySelector('#std-dil-groi-tbody .std-dg-clearance-groi')?.value);
                if (isFinite(raw) && raw >= 0) clearanceNroi = round2(raw);
                if (nroiEl) nroiEl.textContent = fmtNum(clearanceNroi) + '%';
                if (!el) return;
                if (!clearanceSet) {
                    el.textContent = '…';
                    ensureClearance().then(paintClearance);
                    return;
                }
                let n = 0;
                eachInvRow(function (d) { if (clearanceSet[skuKey(d.sku)]) n++; });
                el.textContent = String(n);
            }
            function renderTable() {
                const tb = document.getElementById('std-dil-groi-tbody');
                if (!tb) return;
                const list = normalizeList(rules);
                rules = list;
                const canDelete = list.length > 1;
                tb.innerHTML = list.map(function (r, idx) {
                    return '<tr data-idx="' + idx + '">'
                        + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm std-dg-input std-dg-min" value="' + r.min + '"></td>'
                        + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm std-dg-input std-dg-max" value="' + r.max + '"></td>'
                        + '<td class="std-dg-count"><span class="std-dg-count-n">0</span></td>'
                        + '<td class="text-end"><input type="number" step="0.1" class="form-control form-control-sm std-dg-input std-dg-groi" value="' + r.nroi + '"'
                        + (idx === 0 ? ' title="Changing this sets following slabs to +5 each"' : '') + '></td>'
                        + '<td class="text-center">' + (canDelete ? '<button type="button" class="std-dg-del" data-idx="' + idx + '">&times;</button>' : '') + '</td></tr>';
                }).join('')
                    + '<tr data-clearance="1"><td class="text-center fw-semibold">clearance</td><td class="text-center fw-semibold">clearance</td>'
                    + '<td class="std-dg-count"><span class="std-dg-clearance-count">0</span> <span class="std-dg-clearance-nroi">0%</span></td>'
                    + '<td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm std-dg-input std-dg-clearance-groi" value="' + clearanceNroi + '"></td><td></td></tr>';
                renderPies();
            }
            function readRules() {
                rules = displayRules();
                return rules;
            }
            function cascadeFromFirst(input) {
                const inputs = document.querySelectorAll('#std-dil-groi-tbody .std-dg-groi');
                if (!inputs.length || input !== inputs[0]) {
                    readRules();
                    renderPies();
                    window.stdPricingStampSprcDil();
                    return;
                }
                const first = parseFloat(inputs[0].value);
                if (!isFinite(first)) return;
                inputs.forEach(function (inp, i) { if (i > 0) inp.value = round2(first + (i * 5)); });
                readRules();
                renderPies();
                window.stdPricingStampSprcDil();
            }
            function normalizeLmpRule(raw) {
                if (!raw) return null;
                const min = Number(raw.min);
                const factor = Number(raw.factor);
                if (!isFinite(min) || min < 0 || !isFinite(factor) || factor <= 0) return null;
                const above = !!raw.above || raw.max === null || raw.max === '' || !isFinite(Number(raw.max));
                const max = above ? null : round2(Number(raw.max));
                if (!above && max < min) return null;
                return { min: round2(min), max: max, factor: round2(factor * 10000) / 10000, above: above };
            }
            function readLmpRules() {
                const out = [];
                document.querySelectorAll('#std-lmp-cap-tbody tr').forEach(function (tr) {
                    const maxRaw = tr.querySelector('.std-lmp-max')?.value;
                    const rule = normalizeLmpRule({
                        min: parseFloat(tr.querySelector('.std-lmp-min')?.value),
                        max: maxRaw === '' ? null : parseFloat(maxRaw),
                        factor: parseFloat(tr.querySelector('.std-lmp-factor')?.value),
                        above: maxRaw === '',
                    });
                    if (rule) out.push(rule);
                });
                if (out.length) lmpRules = out.sort(function (a, b) { return a.min - b.min; });
                return lmpRules;
            }
            function renderLmpRules() {
                const tb = document.getElementById('std-lmp-cap-tbody');
                if (!tb) return;
                const list = lmpRules.map(normalizeLmpRule).filter(Boolean);
                lmpRules = list.length ? list : lmpRules;
                const canDelete = lmpRules.length > 1;
                tb.innerHTML = lmpRules.map(function (r, idx) {
                    return '<tr data-idx="' + idx + '">'
                        + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm std-dg-input std-lmp-min" value="' + r.min + '"></td>'
                        + '<td><input type="number" min="0" step="0.1" class="form-control form-control-sm std-dg-input std-lmp-max" value="' + (r.above || r.max == null ? '' : r.max) + '" placeholder="and above"></td>'
                        + '<td class="text-end"><input type="number" min="0.01" step="0.01" class="form-control form-control-sm std-dg-input std-lmp-factor" value="' + r.factor + '"></td>'
                        + '<td class="text-center">' + (canDelete ? '<button type="button" class="std-dg-del std-lmp-del" data-idx="' + idx + '">&times;</button>' : '') + '</td></tr>';
                }).join('');
            }
            function matchLmpRule(dil) {
                const list = lmpRules;
                for (let i = 0; i < list.length; i++) {
                    const rule = list[i];
                    const next = list[i + 1];
                    if (rule.above || rule.max == null) {
                        if (dil > rule.min) return rule;
                        continue;
                    }
                    const handEnd = next && !next.above && next.max != null && Number(next.min) === Number(rule.max);
                    if (dil >= rule.min && (handEnd ? dil < rule.max : dil <= rule.max)) return rule;
                }
                return null;
            }
            function lmpChoice(d) {
                const lmp = parseFloat(d && d.lmp);
                if (isFinite(lmp) && lmp > 0) return { price: lmp, source: 'LMP' };
                const mine = parseFloat(d && d.my_lmp);
                if (isFinite(mine) && mine > 0) return { price: mine, source: 'My LMP' };
                return null;
            }
            window.stdPricingUsePrice = function (d) {
                const std = parseFloat(d && d.std_price);
                if (!isFinite(std) || std <= 0) return null;
                const inv = parseFloat(d.inv) || 0;
                if (!(inv > 0)) return round2(std);
                const dil = ((parseFloat(d.ovl30) || 0) / inv) * 100;
                const rule = matchLmpRule(dil);
                if (!rule) return round2(std);
                const chosen = lmpChoice(d);
                if (!chosen) return round2(std);
                return round2(Math.min(std, chosen.price * rule.factor));
            };
            window.stdPricingUsePriceNote = function (d) {
                const std = parseFloat(d && d.std_price);
                const inv = parseFloat(d && d.inv) || 0;
                if (!(std > 0) || !(inv > 0)) return '';
                const dil = ((parseFloat(d.ovl30) || 0) / inv) * 100;
                const rule = matchLmpRule(dil);
                if (!rule) return 'Dil ' + round2(dil) + '% is outside the Use price ranges. Std Price.';
                const chosen = lmpChoice(d);
                if (!chosen) return 'No LMP or My LMP. Std Price.';
                return 'Dil ' + round2(dil) + '% · ' + chosen.source + ' $' + chosen.price.toFixed(2)
                    + ' × ' + rule.factor + ' vs Std $' + std.toFixed(2);
            };
            function redrawUsePrice() {
                const table = window.stdPricingTable;
                if (!table) return;
                try {
                    const col = table.getColumn('use_price');
                    if (col) col.getCells().forEach(function (cell) { cell.getElement() && cell.getRow().reformat && null; });
                    table.redraw(true);
                } catch (e) { /* ignore */ }
            }
            window.stdPricingSprcForRow = function (d) {
                if (!d || !((parseFloat(d.inv) || 0) > 0)) return null;
                const list = displayRules();
                const rule = matchRule(rowDil(d), list);
                if (!rule) return null;
                const lp = parseFloat(d.lp) || 0;
                if (!(lp > 0)) return null;
                const ship = parseFloat(d.ship) || 0;
                const denom = MARGIN - ADS;
                if (!(denom > 0)) return null;
                const price = (lp * (1 + (Number(rule.nroi) || 0) / 100) + ship) / denom;
                return (isFinite(price) && price > 0) ? round2(price) : null;
            };
            let stamping = false;
            window.stdPricingStampSprcDil = function () {
                const table = window.stdPricingTable;
                if (stamping || !table || typeof table.getRows !== 'function') return;
                stamping = true;
                try {
                    table.getRows().forEach(function (row) {
                        const next = window.stdPricingSprcForRow(row.getData());
                        if (row.getData().sprc_dil !== next) row.update({ sprc_dil: next });
                    });
                } finally {
                    stamping = false;
                    if (typeof window.stdPricingUpdateCounts === 'function') window.stdPricingUpdateCounts();
                }
            };
            function loadRules() {
                const status = document.getElementById('std-dil-groi-status');
                if (status) status.textContent = 'Loading saved slabs…';
                return fetch(@json(route('std.pricing.sprc-dil')), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        rules = normalizeList(res && res.rules);
                        if (res && res.cvr_adj) paintCvr(res.cvr_adj);
                        if (res && res.clearance_nroi != null && isFinite(Number(res.clearance_nroi))) {
                            clearanceNroi = round2(Number(res.clearance_nroi));
                        }
                        if (res && Array.isArray(res.lmp_rules) && res.lmp_rules.length) {
                            lmpRules = res.lmp_rules.map(normalizeLmpRule).filter(Boolean);
                        }
                        renderLmpRules();
                        renderTable();
                        window.stdPricingStampSprcDil();
                        if (status) {
                            status.textContent = (res && res.is_default)
                                ? 'Using first-time defaults. Save and Apply stores them in std_pricing_sprc_dil.'
                                : 'Loaded saved Dil → NROI slabs.';
                        }
                    })
                    .catch(function () {
                        if (status) status.textContent = 'Could not load saved rules.';
                    });
            }
            function saveRules() {
                const status = document.getElementById('std-dil-groi-status');
                const payload = {
                    rules: readRules(),
                    cvr_adj: cvrAdjNow(),
                    clearance_nroi: clearanceNroi,
                    lmp_rules: readLmpRules(),
                };
                if (status) status.textContent = 'Saving…';
                return fetch(@json(route('std.pricing.sprc-dil.save')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf(),
                    },
                    body: JSON.stringify(payload),
                }).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error((body && (body.message || body.error)) || 'Save failed');
                        return body;
                    });
                }).then(function (res) {
                    if (res && Array.isArray(res.rules)) rules = normalizeList(res.rules);
                    if (res && res.cvr_adj) paintCvr(res.cvr_adj);
                    if (res && res.clearance_nroi != null && isFinite(Number(res.clearance_nroi))) {
                        clearanceNroi = round2(Number(res.clearance_nroi));
                    }
                    if (res && Array.isArray(res.lmp_rules)) {
                        lmpRules = res.lmp_rules.map(normalizeLmpRule).filter(Boolean);
                    }
                    renderLmpRules();
                    renderTable();
                    window.stdPricingStampSprcDil();
                    redrawUsePrice();
                    if (status) status.textContent = 'Saved in std_pricing_sprc_dil and applied to Sprc Dil and Use Price.';
                }).catch(function (err) {
                    if (status) status.textContent = err.message || 'Save failed';
                });
            }
            document.getElementById('std-sprc-dil-btn')?.addEventListener('click', function () {
                renderTable();
                const modalEl = document.getElementById('stdDilGroiModal');
                if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            document.getElementById('std-dil-groi-save-btn')?.addEventListener('click', saveRules);
            document.getElementById('std-dil-groi-add-btn')?.addEventListener('click', function () {
                readRules();
                let nextMin = 0.1;
                let lastGroi = 50;
                rules.forEach(function (r) {
                    if (r.max > nextMin) nextMin = r.max;
                    lastGroi = r.nroi;
                });
                const added = normalizeRule({ min: nextMin, max: round2(nextMin + 5), groi: round2(lastGroi + 5) });
                if (added) rules.push(added);
                renderTable();
                window.stdPricingStampSprcDil();
            });
            document.getElementById('std-dil-groi-tbody')?.addEventListener('click', function (e) {
                const btn = e.target.closest('.std-dg-del');
                if (!btn) return;
                readRules();
                const idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (rules.length <= 1 || !isFinite(idx)) return;
                rules.splice(idx, 1);
                renderTable();
                window.stdPricingStampSprcDil();
            });
            document.getElementById('std-dil-groi-tbody')?.addEventListener('input', function (e) {
                if (e.target.classList.contains('std-dg-groi')) cascadeFromFirst(e.target);
                else if (e.target.classList.contains('std-dg-min') || e.target.classList.contains('std-dg-max') || e.target.classList.contains('std-dg-clearance-groi')) {
                    readRules();
                    renderPies();
                    window.stdPricingStampSprcDil();
                }
            });
            document.getElementById('std-cvr-groi-table')?.addEventListener('input', function () {
                renderPies();
            });
            document.getElementById('std-lmp-cap-add-btn')?.addEventListener('click', function () {
                readLmpRules();
                let nextMin = 0;
                lmpRules.forEach(function (r) {
                    const hi = r.above ? r.min : r.max;
                    if (hi > nextMin) nextMin = hi;
                });
                lmpRules.push({ min: round2(nextMin), max: round2(nextMin + 25), factor: 1, above: false });
                renderLmpRules();
                redrawUsePrice();
            });
            document.getElementById('std-lmp-cap-tbody')?.addEventListener('click', function (e) {
                const btn = e.target.closest('.std-lmp-del');
                if (!btn) return;
                readLmpRules();
                const idx = parseInt(btn.getAttribute('data-idx'), 10);
                if (lmpRules.length <= 1 || !isFinite(idx)) return;
                lmpRules.splice(idx, 1);
                renderLmpRules();
                redrawUsePrice();
            });
            document.getElementById('std-lmp-cap-tbody')?.addEventListener('input', function () {
                readLmpRules();
                redrawUsePrice();
            });
            renderLmpRules();
            loadRules();
        })();
    </script>
@endif
