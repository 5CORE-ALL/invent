@extends('layouts.vertical', ['title' => 'eBay 1 — Volume Pricing discount', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #vp-filter-bar { gap: 6px; align-items: center; }
        #vp-filter-bar .form-control, #vp-filter-bar .btn { height: 28px; font-size: 12px; }
        #vp-table { min-height: 70vh; }
        .vp-buy { font-weight: 700; color: #1d4ed8; }
        #vpRuleModal .modal-dialog { width: calc(100vw - 1.25rem); max-width: calc(100vw - 1.25rem); height: calc(100vh - 1.25rem); max-height: calc(100vh - 1.25rem); margin: 0.625rem auto; }
        #vpRuleModal .modal-content { height: 100%; border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18); }
        #vpRuleModal .modal-header, #vpRuleModal .modal-footer { background: #fff; }
        #vpRuleModal .modal-header { border-bottom: 1px solid #e8eef5; }
        #vpRuleModal .modal-body { background: #f4f7fb; overflow: auto; padding: 14px 16px 16px; }
        #vpRuleModal .vp-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
        #vpRuleModal .vp-proj { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        #vpRuleModal .vp-proj-card { display: flex; justify-content: space-between; gap: 16px; border: 1px solid #e6edf5; border-radius: 14px; padding: 12px 16px; background: #fff; }
        #vpRuleModal .vp-proj-card strong { display: block; font-size: 26px; letter-spacing: -0.03em; color: #166534; }
        #vpRuleModal .vp-metric { display: grid; grid-template-columns: 3.6rem auto auto; gap: 4px 12px; width: max-content; font-size: 12px; align-items: center; }
        #vpRuleModal .vp-cols { display: grid; grid-template-columns: repeat(3, minmax(430px, 1.3fr)) repeat(3, minmax(200px, 0.85fr)); gap: 12px; align-items: stretch; min-width: 1890px; }
        #vpRuleModal .vp-col { min-width: 0; display: flex; flex-direction: column; border: 1px solid #e6edf5; border-radius: 14px; padding: 12px; background: #fff; }
        #vpRuleModal .vp-title { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #0f172a; }
        #vpRuleModal .vp-canvas { position: relative; width: 100%; height: 150px; }
        #vpRuleModal .vp-canvas canvas { display: block; width: 100% !important; height: 150px !important; }
        #vpRuleModal .vp-legend { width: 100%; font-size: 12px; margin: 8px 0; color: #334155; max-height: 140px; overflow: auto; }
        #vpRuleModal .vp-leg { display: grid; grid-template-columns: 8px minmax(0, 1fr) auto auto; gap: 6px; align-items: center; padding: 2px 0; }
        #vpRuleModal .vp-swatch { width: 8px; height: 8px; border-radius: 50%; }
        #vpRuleModal .vp-leg-pct { color: #94a3b8; min-width: 2.2rem; text-align: right; }
        #vpRuleModal .table { font-size: 12px; margin-bottom: 0; }
        #vpRuleModal .table th, #vpRuleModal .table td { padding: 5px 6px; vertical-align: middle; }
        #vpRuleModal .table thead th { background: #f8fafc; color: #64748b; font-size: 10px; letter-spacing: 0.04em; text-transform: uppercase; text-align: center; }
        #vpRuleModal .vp-input { width: 52px; height: 28px; text-align: center; font-weight: 600; font-size: 13px; padding: 0 4px; margin: 0 auto; border-radius: 7px; }
        #vpRuleModal input[type=number]::-webkit-inner-spin-button,
        #vpRuleModal input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        #vpRuleModal input[type=number] { -moz-appearance: textfield; appearance: textfield; }
        #vpRuleModal .vp-add { margin-top: auto; width: 100%; border-radius: 8px; border-style: dashed; font-weight: 600; }
        #vpRuleModal.is-off .vp-cols, #vpRuleModal.is-off .vp-proj { opacity: 0.55; }
        #vpRuleModal .vp-count { font-weight: 700; text-align: center; }
        .vp-toast { position: fixed; right: 16px; bottom: 16px; z-index: 20000; min-width: 260px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Volume Pricing discount',
        'sub_title' => 'eBay 1 — Buy 2, Buy 3, and Buy 4 from weight, Dil, and Std NPFT',
    ])
    <div id="vpPage">
        <div class="card shadow-sm">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center mb-2" id="vp-filter-bar">
                    <input type="text" id="vp-parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 170px;">
                    <input type="text" id="vp-sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 170px;">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vpRuleModal">
                        <i class="fas fa-percent me-1"></i> Volume rules
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" id="vp-push-btn" title="Send Buy 2, Buy 3, and Buy 4 to eBay 1. Checked rows on this page push alone. With nothing checked, every listing is pushed.">
                        <i class="fas fa-upload me-1"></i> Push to eBay 1
                    </button>
                    <span class="badge bg-dark" id="vp-rows-badge">Rows: 0</span>
                    <span class="small text-muted" id="vp-status">Loading eBay 1…</span>
                </div>
                <div id="vp-table"></div>
            </div>
        </div>
    </div>

    <div class="modal fade is-off" id="vpRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fs-6 mb-0">
                            <i class="fas fa-tags me-2 text-primary"></i>Volume Pricing discount
                            <span class="badge bg-secondary ms-2" style="font-size:11px;">eBay 1 only</span>
                        </h5>
                        <div class="vp-sub">Buy 2, Buy 3, and Buy 4 = Weight + Dil + Std NPFT %, one discount per SKU. On Weight, Dil, and Std NPFT, the next slab drops those percents by 1. From / To on weight are pounds. Dil is OV L30 ÷ INV. Std NPFT % is ((Std Prc × 0.70 − ship − LP) ÷ Std Prc) × 100.</div>
                    </div>
                    <div class="form-check form-switch mb-0 ms-3 me-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="vp-enabled">
                        <label class="form-check-label small fw-semibold" for="vp-enabled" id="vp-enabled-label">Off</label>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2" id="vp-mode-note">Off. Buy 2, Buy 3, and Buy 4 stay blank.</p>
                    <div class="vp-proj">
                        <div class="vp-proj-card">
                            <div>
                                <div class="small text-muted">Projected discount · E L30 sales</div>
                                <strong id="vp-proj-l30">—</strong>
                                <div class="small text-muted" id="vp-proj-l30-sub"></div>
                            </div>
                            <div class="vp-metric" id="vp-proj-l30-metrics"></div>
                        </div>
                        <div class="vp-proj-card">
                            <div>
                                <div class="small text-muted">Projected discount · total INV</div>
                                <strong id="vp-proj-inv">—</strong>
                                <div class="small text-muted" id="vp-proj-inv-sub"></div>
                            </div>
                            <div class="vp-metric" id="vp-proj-inv-metrics"></div>
                        </div>
                    </div>
                    <div class="vp-cols">
                        <div class="vp-col">
                            <div class="vp-title">Weight</div>
                            <div class="vp-canvas"><canvas id="vp-chart-weight"></canvas></div>
                            <div class="vp-legend" id="vp-leg-weight"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead><tr><th title="Pounds">From</th><th title="Pounds">To</th><th>Count</th><th>Buy 2</th><th>Buy 3</th><th>Buy 4</th><th></th></tr></thead>
                                    <tbody id="vp-weight-body"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary vp-add" id="vp-add-weight"><i class="fas fa-plus me-1"></i>Add slab</button>
                        </div>
                        <div class="vp-col">
                            <div class="vp-title">Dil</div>
                            <div class="vp-canvas"><canvas id="vp-chart-dil"></canvas></div>
                            <div class="vp-legend" id="vp-leg-dil"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead><tr><th>From</th><th>To</th><th>Count</th><th>Buy 2</th><th>Buy 3</th><th>Buy 4</th><th></th></tr></thead>
                                    <tbody id="vp-dil-body"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary vp-add" id="vp-add-dil"><i class="fas fa-plus me-1"></i>Add slab</button>
                        </div>
                        <div class="vp-col">
                            <div class="vp-title">Std NPFT %</div>
                            <div class="vp-canvas"><canvas id="vp-chart-npft"></canvas></div>
                            <div class="vp-legend" id="vp-leg-npft"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead><tr><th>From</th><th>To</th><th>Count</th><th>Buy 2</th><th>Buy 3</th><th>Buy 4</th><th></th></tr></thead>
                                    <tbody id="vp-npft-body"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary vp-add" id="vp-add-npft"><i class="fas fa-plus me-1"></i>Add range</button>
                        </div>
                        <div class="vp-col">
                            <div class="vp-title">Buy 2</div>
                            <div class="vp-canvas"><canvas id="vp-chart-buy2"></canvas></div>
                            <div class="vp-legend" id="vp-leg-buy2"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead><tr><th>Discount</th><th>SKUs</th></tr></thead>
                                    <tbody id="vp-buy2-body"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="vp-col">
                            <div class="vp-title">Buy 3</div>
                            <div class="vp-canvas"><canvas id="vp-chart-buy3"></canvas></div>
                            <div class="vp-legend" id="vp-leg-buy3"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead><tr><th>Discount</th><th>SKUs</th></tr></thead>
                                    <tbody id="vp-buy3-body"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="vp-col">
                            <div class="vp-title">Buy 4</div>
                            <div class="vp-canvas"><canvas id="vp-chart-buy4"></canvas></div>
                            <div class="vp-legend" id="vp-leg-buy4"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead><tr><th>Discount</th><th>SKUs</th></tr></thead>
                                    <tbody id="vp-buy4-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="small text-muted me-auto" id="vp-save-note"></span>
                    <button type="button" class="btn btn-sm btn-primary" id="vp-save-btn">Save rules</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        const VP_DATA_URL = @json(url('/ebay-data-json'));
        const VP_RULES_URL = @json(url('/ebay-volume-pricing/rules'));
        const VP_PUSH_URL = @json(url('/ebay-volume-pricing/push'));
        const VP_CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        let vpRules = null;
        let vpTable = null;
        let vpStdRules = { age: [], dil: [] };
        let vpAgeMap = {};
        let vpAmzMap = {};

        function vpCsrfHeaders() {
            return { 'X-CSRF-TOKEN': VP_CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' };
        }
        function vpToast(kind, text) {
            const el = document.createElement('div');
            el.className = 'alert alert-' + (kind === 'error' ? 'danger' : 'success') + ' vp-toast shadow-sm';
            el.textContent = text;
            document.body.appendChild(el);
            setTimeout(function() { el.remove(); }, 4200);
        }
        function vpNum(v) {
            const n = Number(v);
            return isFinite(n) ? n : 0;
        }
        function vpIsParent(d) {
            if (!d) return false;
            if (d.is_parent_summary) return true;
            return String(d['(Child) sku'] || '').toUpperCase().indexOf('PARENT') === 0;
        }
        function vpSkuKey(sku) {
            return String(sku == null ? '' : sku).replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
        }
        function vpDil(d) {
            const inv = vpNum(d && d.INV);
            const ov = vpNum(d && d.L30);
            return inv > 0 ? (ov / inv) * 100 : 0;
        }
        function vpNpft(d) {
            const std = vpNum(d && d.STANDARD_PRICE);
            if (!(std > 0)) return null;
            const lp = vpNum(d && d.LP_productmaster);
            const ship = vpNum(d && d.Ship_productmaster);
            return Math.round(((std * 0.70 - ship - (lp > 0 ? lp : 0)) / std) * 10000) / 100;
        }
        function vpPct(v) {
            let n = Math.round(vpNum(v) * 10) / 10;
            if (n > 100) n = 100;
            if (n < -100) n = -100;
            return n;
        }
        function vpTier(row) {
            return { buy2: vpPct(row && row.buy2), buy3: vpPct(row && row.buy3), buy4: vpPct(row && row.buy4) };
        }
        function vpContains(value, range, prevMax) {
            const min = vpNum(range.min);
            const max = vpNum(range.max);
            if (Math.abs(min) < 0.0000001 && Math.abs(max) < 0.0000001) return Math.abs(value) < 0.0000001;
            const shares = prevMax !== null && Math.abs(min - prevMax) < 0.0001;
            const loOk = shares ? value > min : value >= min;
            return loOk && value <= max;
        }
        function vpRangeTier(value, ranges) {
            const zero = { buy2: 0, buy3: 0, buy4: 0 };
            if (value === null || !isFinite(Number(value)) || !ranges || !ranges.length) return zero;
            let prevMax = null;
            for (let i = 0; i < ranges.length; i++) {
                if (vpContains(Number(value), ranges[i], prevMax)) return vpTier(ranges[i]);
                prevMax = vpNum(ranges[i].max);
            }
            const last = ranges[ranges.length - 1];
            return Number(value) > vpNum(last.max) ? vpTier(last) : zero;
        }
        function vpSum(d) {
            const zero = { buy2: 0, buy3: 0, buy4: 0, parts: { weight: { buy2: 0, buy3: 0, buy4: 0 }, dil: { buy2: 0, buy3: 0, buy4: 0 }, npft: { buy2: 0, buy3: 0, buy4: 0 } } };
            if (!vpRules || vpIsParent(d)) return zero;
            const weight = vpRangeTier(vpWeightLb(d), vpRules.weight || []);
            const dil = vpRangeTier(vpDil(d), vpRules.dil || []);
            const npft = vpRangeTier(vpNpft(d), vpRules.npft || []);
            const round1 = function(n) { return Math.round(n * 10) / 10; };
            return {
                buy2: round1(weight.buy2 + dil.buy2 + npft.buy2),
                buy3: round1(weight.buy3 + dil.buy3 + npft.buy3),
                buy4: round1(weight.buy4 + dil.buy4 + npft.buy4),
                parts: { weight: weight, dil: dil, npft: npft }
            };
        }
        function vpChildRows() {
            const rows = (vpTable && typeof vpTable.getData === 'function') ? vpTable.getData() : [];
            return rows.filter(function(d) { return d && !vpIsParent(d); });
        }
        function vpDiscRange(value, rules) {
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
        function vpStdPrice(d) {
            const n = vpNum(d && d.STANDARD_PRICE);
            return n > 0 ? n : 0;
        }
        function vpScale(std, disc) {
            if (!(disc > 0)) return 0;
            return (std > 0 && std < 15) ? Math.round(disc * 50) / 100 : disc;
        }
        function vpAmz(d) {
            const hit = vpAmzMap[vpSkuKey(d && d['(Child) sku'])];
            if (!hit || !hit.length) return null;
            return { ov: Number(hit[4]) || 0, inv: Number(hit[5]) || 0 };
        }
        function vpAgeDisc(d) {
            if (vpIsParent(d)) return null;
            const metric = vpAmz(d);
            const inv = metric ? metric.inv : vpNum(d.INV);
            if (!(inv > 0)) return 0;
            const days = Number(vpAgeMap[vpSkuKey(d['(Child) sku'])]);
            if (!isFinite(days)) return 0;
            return vpScale(vpStdPrice(d), vpDiscRange(days, vpStdRules.age));
        }
        function vpDilDisc(d) {
            if (vpIsParent(d)) return null;
            const metric = vpAmz(d);
            const inv = metric ? metric.inv : vpNum(d.INV);
            if (!(inv > 0)) return 0;
            const dil = metric ? (metric.inv > 0 ? (metric.ov / metric.inv) * 100 : 0) : vpDil(d);
            return vpScale(vpStdPrice(d), vpDiscRange(dil, vpStdRules.dil));
        }
        function vpMoney(n) {
            const v = vpNum(n);
            return v ? ('$' + v.toFixed(2)) : '';
        }
        function vpPctCell(n) {
            if (n === null || n === undefined || n === '') return '';
            const v = vpNum(n);
            const color = v < 0 ? '#a00211' : (v > 0 ? '#198754' : '#6c757d');
            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
        }
        function vpBuyCell(cell, key) {
            const d = cell.getRow().getData();
            if (!vpRules || !vpRules.enabled || vpIsParent(d)) return '';
            const sum = vpSum(d);
            const n = sum[key];
            if (!n) return '<span style="color:#adb5bd;">0</span>';
            const p = sum.parts;
            const tip = 'Weight ' + p.weight[key] + ' + Dil ' + p.dil[key] + ' + Std NPFT ' + p.npft[key];
            return '<span class="vp-buy" title="' + tip + '">' + n + '%</span>';
        }
        function vpInput(cls, value) {
            return '<input type="number" step="0.1" class="form-control form-control-sm vp-input ' + cls + '" value="' + vpPct(value) + '">';
        }
        function vpWeightLb(d) {
            const n = Number(d && d.wt_act);
            return isFinite(n) ? n : null;
        }
        function vpReadRulesFromDom() {
            if (!vpRules) return;
            ['weight', 'dil', 'npft'].forEach(function(name) {
                const rows = [];
                document.querySelectorAll('#vp-' + name + '-body tr').forEach(function(tr) {
                    rows.push({
                        min: vpNum(tr.querySelector('.vp-min').value),
                        max: vpNum(tr.querySelector('.vp-max').value),
                        buy2: vpPct(tr.querySelector('.vp-b2').value),
                        buy3: vpPct(tr.querySelector('.vp-b3').value),
                        buy4: vpPct(tr.querySelector('.vp-b4').value)
                    });
                });
                vpRules[name] = rows;
            });
            vpRules.enabled = !!document.getElementById('vp-enabled').checked;
        }
        function vpCountRanges(rows, valueOf) {
            const counts = (rows || []).map(function() { return 0; });
            vpChildRows().forEach(function(d) {
                const value = valueOf(d);
                if (value === null || !isFinite(Number(value))) return;
                let prev = null;
                let hit = false;
                (rows || []).forEach(function(range, i) {
                    if (!hit && vpContains(Number(value), range, prev)) { counts[i] += 1; hit = true; }
                    prev = vpNum(range.max);
                });
                if (!hit && rows && rows.length && Number(value) > vpNum(rows[rows.length - 1].max)) {
                    counts[counts.length - 1] += 1;
                }
            });
            return counts;
        }
        const VP_BAR_COLORS = ['#7c3aed', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444', '#ec4899', '#0ea5e9', '#64748b', '#a16207'];
        let vpChartState = [];
        function vpCounts() {
            return {
                weight: vpCountRanges(vpRules.weight, vpWeightLb),
                dil: vpCountRanges(vpRules.dil, vpDil),
                npft: vpCountRanges(vpRules.npft, vpNpft)
            };
        }
        function vpMoney0(n) {
            const v = Math.round(vpNum(n));
            return (v < 0 ? '-' : '') + '$' + Math.abs(v).toLocaleString('en-US');
        }
        function vpRangeLabel(row) {
            const a = vpNum(row && row.min);
            const b = vpNum(row && row.max);
            if (Math.abs(a) < 0.0001 && Math.abs(b) < 0.0001) return '0';
            return a + '–' + b;
        }
        function vpDrawBars(canvas, counts) {
            if (!canvas) return;
            const parent = canvas.parentElement;
            const w = Math.max(80, parent ? parent.clientWidth : 160);
            const h = 150;
            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(w * dpr);
            canvas.height = Math.round(h * dpr);
            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            const values = counts && counts.length ? counts : [0];
            const max = Math.max.apply(null, values.concat([1]));
            const n = values.length;
            const slot = w / n;
            values.forEach(function(v, i) {
                const barW = Math.max(3, slot - (n > 16 ? 2 : 8));
                const bh = v > 0 ? Math.max(4, Math.round((v / max) * (h - 8))) : 0;
                const x = i * slot + (slot - barW) / 2;
                const y = h - bh;
                ctx.fillStyle = VP_BAR_COLORS[i % VP_BAR_COLORS.length];
                ctx.beginPath();
                if (bh > 0 && ctx.roundRect) ctx.roundRect(x, y, barW, bh, [4, 4, 0, 0]);
                else ctx.rect(x, y, barW, Math.max(bh, 0));
                ctx.fill();
            });
        }
        function vpLegend(el, items, total) {
            if (!el) return;
            el.innerHTML = items.map(function(item, i) {
                const pct = total ? Math.round((item.count / total) * 100) : 0;
                return '<div class="vp-leg"><span class="vp-swatch" style="background:' + VP_BAR_COLORS[i % VP_BAR_COLORS.length] + '"></span><span>' + item.label + '</span><span>' + item.count + '</span><span class="vp-leg-pct">' + pct + '%</span></div>';
            }).join('');
        }
        function vpDiscountBands(key) {
            const map = {};
            vpChildRows().forEach(function(d) {
                const n = vpSum(d)[key];
                const k = String(n);
                map[k] = (map[k] || 0) + 1;
            });
            return Object.keys(map).map(function(k) {
                const pct = Number(k) || 0;
                return { pct: pct, count: map[k], label: pct + '%' };
            }).sort(function(a, b) { return b.pct - a.pct; });
        }
        function vpWithOutside(rows, counts) {
            const items = (rows || []).map(function(row, i) {
                return { label: vpRangeLabel(row), count: counts[i] || 0 };
            });
            const used = items.reduce(function(sum, item) { return sum + item.count; }, 0);
            const outside = Math.max(0, vpChildRows().length - used);
            if (outside) items.push({ label: 'Outside', count: outside });
            return items;
        }
        function vpProject() {
            const kids = vpChildRows();
            const blank = function() { return { dollars: { buy2: 0, buy3: 0, buy4: 0 }, sales: 0, units: 0 }; };
            const l30 = blank();
            const inv = blank();
            const pct = { buy2: 0, buy3: 0, buy4: 0 };
            kids.forEach(function(d) {
                const sum = vpSum(d);
                pct.buy2 += sum.buy2;
                pct.buy3 += sum.buy3;
                pct.buy4 += sum.buy4;
                const price = vpNum(d['eBay Price']);
                const l30u = vpNum(d['eBay L30']);
                const invu = vpNum(d.INV);
                ['buy2', 'buy3', 'buy4'].forEach(function(key) {
                    const rate = sum[key] / 100;
                    l30.dollars[key] += price * l30u * rate;
                    inv.dollars[key] += price * invu * rate;
                });
                l30.sales += price * l30u;
                l30.units += l30u;
                inv.sales += price * invu;
                inv.units += invu;
            });
            const n = kids.length;
            return {
                n: n,
                avg: {
                    buy2: n ? pct.buy2 / n : 0,
                    buy3: n ? pct.buy3 / n : 0,
                    buy4: n ? pct.buy4 / n : 0
                },
                l30: l30,
                inv: inv
            };
        }
        function vpPaintCharts() {
            if (!vpRules) return;
            const counts = vpCounts();
            ['weight', 'dil', 'npft'].forEach(function(name) {
                document.querySelectorAll('#vp-' + name + '-body .vp-count').forEach(function(cell, i) {
                    cell.textContent = (counts[name] && counts[name][i]) || 0;
                });
            });
            const buy2 = vpDiscountBands('buy2');
            const buy3 = vpDiscountBands('buy3');
            const buy4 = vpDiscountBands('buy4');
            vpChartState = [
                ['vp-chart-weight', 'vp-leg-weight', vpWithOutside(vpRules.weight, counts.weight)],
                ['vp-chart-dil', 'vp-leg-dil', vpWithOutside(vpRules.dil, counts.dil)],
                ['vp-chart-npft', 'vp-leg-npft', vpWithOutside(vpRules.npft, counts.npft)],
                ['vp-chart-buy2', 'vp-leg-buy2', buy2],
                ['vp-chart-buy3', 'vp-leg-buy3', buy3],
                ['vp-chart-buy4', 'vp-leg-buy4', buy4]
            ];
            vpRedrawCharts();
            function bandRows(items) {
                if (!items.length) return '<tr><td colspan="2" class="text-muted">No SKUs</td></tr>';
                return items.map(function(item) {
                    return '<tr><td class="text-center fw-bold">' + item.label + '</td><td class="vp-count">' + item.count + '</td></tr>';
                }).join('');
            }
            document.getElementById('vp-buy2-body').innerHTML = bandRows(buy2);
            document.getElementById('vp-buy3-body').innerHTML = bandRows(buy3);
            document.getElementById('vp-buy4-body').innerHTML = bandRows(buy4);
            const project = vpProject();
            function fillCard(id, bucket, word) {
                const dollars = bucket.dollars.buy4;
                const head = document.getElementById(id);
                head.textContent = vpMoney0(dollars);
                head.style.color = dollars < 0 ? '#dc3545' : '#166534';
                const share = bucket.sales > 0 ? Math.round((dollars / bucket.sales) * 100) : 0;
                const avg4 = Math.round(project.avg.buy4 * 10) / 10;
                document.getElementById(id + '-sub').textContent = Math.round(bucket.units) + ' units · ' + share + '% of ' + word + ' · Buy 4 avg ' + avg4 + '% · ' + project.n + ' SKUs';
                const rows = [
                    ['Buy 2', bucket.dollars.buy2, project.avg.buy2],
                    ['Buy 3', bucket.dollars.buy3, project.avg.buy3],
                    ['Buy 4', bucket.dollars.buy4, project.avg.buy4]
                ];
                document.getElementById(id + '-metrics').innerHTML = rows.map(function(row) {
                    const color = row[1] < 0 ? '#dc3545' : '#166534';
                    return '<span>' + row[0] + '</span><span style="text-align:right;font-weight:700;color:' + color + '">' + vpMoney0(row[1]) + '</span><span style="text-align:right;font-weight:700;">' + (Math.round(row[2] * 10) / 10) + '%</span>';
                }).join('');
            }
            fillCard('vp-proj-l30', project.l30, 'sales');
            fillCard('vp-proj-inv', project.inv, 'retail');
        }
        function vpRedrawCharts() {
            vpChartState.forEach(function(row) {
                const items = row[2] || [];
                const total = items.reduce(function(sum, item) { return sum + item.count; }, 0);
                vpDrawBars(document.getElementById(row[0]), items.map(function(item) { return item.count; }));
                vpLegend(document.getElementById(row[1]), items, total);
            });
        }
        function vpPaintModal() {
            if (!vpRules) return;
            const counts = vpCounts();
            const on = !!vpRules.enabled;
            document.getElementById('vpRuleModal').classList.toggle('is-off', !on);
            document.getElementById('vp-enabled').checked = on;
            document.getElementById('vp-enabled-label').textContent = on ? 'On' : 'Off';
            document.getElementById('vp-mode-note').textContent = on
                ? 'On. Each SKU gets Buy 2, Buy 3, and Buy 4 from Weight + Dil + Std NPFT.'
                : 'Off. Buy 2, Buy 3, and Buy 4 stay blank on the grid.';
            function rangeRows(name, rows, rowCounts) {
                return rows.map(function(row, i) {
                    return '<tr><td><input type="number" step="0.01" class="form-control form-control-sm vp-input vp-min" value="' + row.min + '"></td>'
                        + '<td><input type="number" step="0.01" class="form-control form-control-sm vp-input vp-max" value="' + row.max + '"></td>'
                        + '<td class="vp-count">' + (rowCounts[i] || 0) + '</td><td>' + vpInput('vp-b2', row.buy2) + '</td><td>' + vpInput('vp-b3', row.buy3) + '</td><td>' + vpInput('vp-b4', row.buy4) + '</td>'
                        + '<td><button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 vp-del" data-table="' + name + '" data-i="' + i + '">×</button></td></tr>';
                }).join('');
            }
            document.getElementById('vp-weight-body').innerHTML = rangeRows('weight', vpRules.weight || [], counts.weight);
            document.getElementById('vp-dil-body').innerHTML = rangeRows('dil', vpRules.dil || [], counts.dil);
            document.getElementById('vp-npft-body').innerHTML = rangeRows('npft', vpRules.npft || [], counts.npft);
            vpPaintCharts();
        }
        function vpRefreshGrid() {
            if (!vpTable) return;
            ['buy2', 'buy3', 'buy4'].forEach(function(field) {
                try {
                    const col = vpTable.getColumn(field);
                    if (col) col.getCells().forEach(function(cell) { cell.reformat(); });
                } catch (e) { /* column not ready */ }
            });
        }
        function vpApplyFilters() {
            if (!vpTable) return;
            const parent = document.getElementById('vp-parent-search').value.trim().toLowerCase();
            const sku = document.getElementById('vp-sku-search').value.trim().toLowerCase();
            const filters = [];
            if (parent) filters.push({ field: 'Parent', type: 'like', value: parent });
            if (sku) filters.push({ field: '(Child) sku', type: 'like', value: sku });
            vpTable.setFilter(filters);
        }
        function vpNeedsAds(d) {
            if (!d || vpIsParent(d)) return false;
            const status = String(d.ca_campaign_status || '').toUpperCase();
            if (status === 'RUNNING' || status === 'PAUSED' || status === 'SYSTEM_PAUSED') return false;
            if (String(d.ca_promote_with_ad || '').toUpperCase() === 'AD_ALREADY_CREATED') return false;
            if (vpNum(d.ca_bid_percentage) > 0) return false;
            return vpNum(d.INV) > 0 && vpNum(d['eBay L30']) <= 0;
        }

        document.getElementById('vp-parent-search').addEventListener('input', vpApplyFilters);
        document.getElementById('vp-sku-search').addEventListener('input', vpApplyFilters);
        document.getElementById('vp-enabled').addEventListener('change', function() {
            vpReadRulesFromDom();
            vpPaintModal();
            vpRefreshGrid();
        });
        function vpCascadeBuys(input) {
            const row = input.closest('#vp-weight-body tr, #vp-dil-body tr, #vp-npft-body tr');
            if (!row) return;
            const field = input.classList.contains('vp-b2') ? 'vp-b2' : (input.classList.contains('vp-b3') ? 'vp-b3' : (input.classList.contains('vp-b4') ? 'vp-b4' : ''));
            if (!field) return;
            let value = vpPct(input.value);
            let next = row.nextElementSibling;
            while (next) {
                value = Math.max(0, Math.round((value - 1) * 10) / 10);
                const box = next.querySelector('.' + field);
                if (box) box.value = value;
                next = next.nextElementSibling;
            }
        }
        let vpLiveTimer = null;
        document.getElementById('vpRuleModal').addEventListener('input', function(e) {
            if (!e.target.classList.contains('vp-input')) return;
            vpCascadeBuys(e.target);
            vpReadRulesFromDom();
            vpRefreshGrid();
            clearTimeout(vpLiveTimer);
            vpLiveTimer = setTimeout(vpPaintCharts, 120);
        });
        document.getElementById('vpRuleModal').addEventListener('click', function(e) {
            const del = e.target.closest('.vp-del');
            if (!del || !vpRules) return;
            vpReadRulesFromDom();
            const name = del.getAttribute('data-table');
            const i = Number(del.getAttribute('data-i'));
            if ((vpRules[name] || []).length <= 1) return;
            vpRules[name].splice(i, 1);
            vpPaintModal();
            vpRefreshGrid();
        });
        function vpAddRange(name, step) {
            if (!vpRules) return;
            vpReadRulesFromDom();
            const rows = vpRules[name] || [];
            const last = rows[rows.length - 1] || { max: 0, buy2: 0, buy3: 0, buy4: 0 };
            const bump = step == null ? 10 : step;
            const min = vpNum(last.max);
            const buys = {
                buy2: Math.max(0, vpPct(last.buy2) - 1),
                buy3: Math.max(0, vpPct(last.buy3) - 1),
                buy4: Math.max(0, vpPct(last.buy4) - 1)
            };
            rows.push(Object.assign({ min: min, max: Math.round((min + bump) * 100) / 100 }, buys));
            vpRules[name] = rows;
            vpPaintModal();
        }
        document.getElementById('vp-add-weight').addEventListener('click', function() { vpAddRange('weight', 1); });
        document.getElementById('vp-add-dil').addEventListener('click', function() { vpAddRange('dil'); });
        document.getElementById('vp-add-npft').addEventListener('click', function() { vpAddRange('npft'); });
        document.getElementById('vpRuleModal').addEventListener('show.bs.modal', function() { vpPaintModal(); });
        document.getElementById('vpRuleModal').addEventListener('shown.bs.modal', function() { vpRedrawCharts(); });

        document.getElementById('vp-save-btn').addEventListener('click', function() {
            if (!vpRules) return;
            vpReadRulesFromDom();
            const btn = this;
            btn.disabled = true;
            fetch(VP_RULES_URL, { method: 'POST', headers: vpCsrfHeaders(), body: JSON.stringify(vpRules) })
                .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(res) {
                    if (res.j && res.j.rules) vpRules = res.j.rules;
                    vpPaintModal();
                    vpRefreshGrid();
                    vpToast(res.ok ? 'ok' : 'error', (res.j && res.j.message) || 'Saved');
                })
                .catch(function() { vpToast('error', 'Could not save the rules'); })
                .finally(function() { btn.disabled = false; });
        });

        document.getElementById('vp-push-btn').addEventListener('click', function() {
            if (!vpRules) return;
            vpReadRulesFromDom();
            if (!vpRules.enabled) {
                vpToast('error', 'Turn the volume pricing rules on before pushing to eBay.');
                return;
            }
            const picked = Array.from(document.querySelectorAll('.vp-check:checked')).map(function(el) {
                return String(el.getAttribute('data-sku') || '').trim().toUpperCase();
            });
            const source = picked.length
                ? vpChildRows().filter(function(d) { return picked.indexOf(String(d['(Child) sku'] || '').trim().toUpperCase()) !== -1; })
                : vpChildRows();
            const rows = source.map(function(d) {
                return {
                    sku: d['(Child) sku'] || '',
                    item_id: d.eBay_item_id || '',
                    weight_lb: vpWeightLb(d),
                    dil: vpDil(d),
                    npft: vpNpft(d)
                };
            }).filter(function(row) { return String(row.item_id || '').trim() !== ''; });
            if (!rows.length) {
                vpToast('error', 'No eBay listing ids to push.');
                return;
            }
            const btn = this;
            btn.disabled = true;
            document.getElementById('vp-status').textContent = 'Pushing ' + rows.length + ' listings to eBay 1…';
            fetch(VP_PUSH_URL, {
                method: 'POST',
                headers: vpCsrfHeaders(),
                body: JSON.stringify({ rules: vpRules, rows: rows })
            }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(res) {
                    const msg = (res.j && res.j.message) || 'Push finished';
                    const extra = (res.j && res.j.errors && res.j.errors.length) ? ' ' + res.j.errors[0] : '';
                    document.getElementById('vp-status').textContent = msg;
                    vpToast(res.ok ? 'ok' : 'error', msg + extra);
                })
                .catch(function() {
                    document.getElementById('vp-status').textContent = 'Push failed';
                    vpToast('error', 'Could not reach eBay');
                })
                .finally(function() { btn.disabled = false; });
        });

        fetch(VP_RULES_URL, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) { vpRules = res.rules || null; vpPaintModal(); vpRefreshGrid(); })
            .catch(function() { document.getElementById('vp-status').textContent = 'Could not load rules'; });

        fetch('/channel-promo-pricing/ebay1/std-prc-vs-dil', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                vpStdRules = { age: (res && res.age) || [], dil: (res && res.dil) || [] };
                vpRefreshGrid();
            }).catch(function() {});
        fetch('/inv-days/age-map', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) { vpAgeMap = (res && res.age_days) || {}; vpRefreshGrid(); })
            .catch(function() {});
        fetch('/inv-days/amazon-std-map', { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) { vpAmzMap = (res && res.metrics) || {}; vpRefreshGrid(); })
            .catch(function() {});

        function vpParseJson(text) {
            const raw = String(text == null ? '' : text).replace(/^\uFEFF/, '');
            try {
                return JSON.parse(raw);
            } catch (e) {
                const msg = String((e && e.message) || '');
                const m = msg.match(/position\s+(\d+)/i);
                if (m) {
                    const pos = Number(m[1]);
                    if (pos > 0) {
                        try { return JSON.parse(raw.slice(0, pos)); } catch (e2) { /* keep the original error */ }
                    }
                }
                throw e;
            }
        }
        function vpRowsFromPayload(payload) {
            if (Array.isArray(payload)) return payload;
            if (payload && Array.isArray(payload.data)) return payload.data;
            return [];
        }
        function vpLoadRows() {
            const status = document.getElementById('vp-status');
            status.textContent = 'Loading eBay 1…';
            return fetch(VP_DATA_URL, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                return res.text().then(function(text) {
                    if (!res.ok) {
                        let message = 'eBay 1 data failed (' + res.status + ')';
                        try {
                            const body = vpParseJson(text);
                            if (body && body.error) message = String(body.error);
                        } catch (e) { /* keep the status code */ }
                        throw new Error(message);
                    }
                    const rows = vpRowsFromPayload(vpParseJson(text));
                    return vpTable.setData(rows).then(function() {
                        vpTable.setSort('buy4', 'desc');
                        status.textContent = rows.length + ' rows from eBay 1';
                        document.getElementById('vp-rows-badge').textContent = 'Rows: ' + rows.length;
                    });
                });
            }).catch(function(err) {
                status.textContent = (err && err.message) ? err.message : 'Could not load eBay 1 data';
                vpToast('error', status.textContent);
            });
        }

        vpTable = new Tabulator('#vp-table', {
            data: [],
            paginationMode: 'local',
            layout: 'fitDataStretch',
            height: 'calc(100vh - 210px)',
            placeholder: 'No eBay 1 rows',
            pagination: true,
            paginationSize: 100,
            initialSort: [{ column: 'buy4', dir: 'desc' }],
            paginationCounter: 'rows',
            columnDefaults: { headerSort: true, resizable: true, vertAlign: 'middle' },
            columns: [
                { title: 'Parent', field: 'Parent', frozen: true, width: 120, headerFilter: 'input', cssClass: 'text-primary',
                    formatter: function(cell) {
                        const value = String(cell.getValue() || '');
                        return value.toUpperCase().indexOf('PARENT ') === 0 ? value.replace(/^PARENT\s+/i, '') : value;
                    } },
                { title: '', field: '_select', width: 36, hozAlign: 'center', headerSort: false, frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'] || '';
                        if (vpIsParent(cell.getRow().getData())) return '';
                        return '<input type="checkbox" class="vp-check" data-sku="' + sku + '">';
                    } },
                { title: 'Image', field: 'image_path', width: 56, headerSort: false, frozen: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        const u = String(value).replace(/"/g, '&quot;');
                        return '<img src="' + u + '" alt="" style="width:28px;height:28px;object-fit:cover;">';
                    } },
                { title: 'SKU', field: '(Child) sku', width: 180, frozen: true, cssClass: 'fw-bold text-primary', headerFilter: 'input',
                    formatter: function(cell) {
                        const sku = cell.getValue() || '';
                        return '<span>' + sku + '</span>';
                    } },
                { title: 'INV', field: 'INV', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: function(cell) { return Math.round(vpNum(cell.getValue())); } },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 70, sorter: 'number',
                    formatter: function(cell) { return Math.round(vpNum(cell.getValue())); } },
                { title: 'Dil', field: 'E Dil%', hozAlign: 'center', width: 58,
                    sorter: function(a, b, aRow, bRow) { return vpDil(aRow.getData()) - vpDil(bRow.getData()); },
                    headerTooltip: 'Dil = OV L30 ÷ INV. Same Dil the volume rules use.',
                    formatter: function(cell) {
                        const dil = vpDil(cell.getRow().getData());
                        let color = '#6c757d';
                        if (dil > 0 && dil < 25) color = '#a00211';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else if (dil >= 50) color = '#e83e8c';
                        return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                    } },
                { title: 'E L30', field: 'eBay L30', hozAlign: 'center', width: 58, sorter: 'number',
                    formatter: function(cell) { return Math.round(vpNum(cell.getValue())); } },
                { title: 'Req Ads', field: 'req_ads', hozAlign: 'center', width: 70, headerSort: false,
                    headerTooltip: 'INV > 0, E L30 is 0, and the listing is not already in a campaign.',
                    formatter: function(cell) {
                        if (!vpNeedsAds(cell.getRow().getData())) return '';
                        return '<i class="fas fa-exclamation-triangle" style="color:#dc3545;" title="Ads needed"></i>';
                    } },
                { title: 'Coupon', field: 'zero_sold_coupon_code', hozAlign: 'center', width: 90,
                    formatter: function(cell) {
                        const code = String(cell.getValue() || '').trim();
                        return code ? '<span style="color:#0d6efd;font-weight:600;font-size:11px;">' + code + '</span>' : '';
                    } },
                { title: 'CVR 30', field: 'SCVR', hozAlign: 'center', width: 70, sorter: 'number',
                    formatter: function(cell) {
                        const val = vpNum(cell.getValue());
                        const color = val <= 4 ? '#a00211' : (val <= 7 ? '#ffc107' : (val <= 13 ? '#28a745' : '#e83e8c'));
                        return '<span style="color:' + color + ';font-weight:600;">' + val.toFixed(1) + '%</span>';
                    } },
                @include('partials.analytics-sku-reviews-column', ['marketplace' => 'ebay'])
                { title: 'Price', field: 'eBay Price', hozAlign: 'center', width: 80, sorter: 'number',
                    formatter: function(cell) {
                        const value = vpNum(cell.getValue());
                        const lmp = vpNum(cell.getRow().getData().lmp_price);
                        const over = lmp > 0 && value > lmp;
                        return '<span style="color:' + (over ? '#dc3545' : 'inherit') + ';font-weight:' + (over ? '600' : '500') + ';">$' + value.toFixed(2) + '</span>';
                    } },
                { title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', width: 64, sorter: 'number',
                    formatter: function(cell) { return vpPctCell(cell.getValue()); } },
                { title: 'GROI%', field: 'ROI%', hozAlign: 'center', width: 64, sorter: 'number',
                    formatter: function(cell) { return vpPctCell(cell.getValue()); } },
                { title: 'NPFT%', field: 'PFT %', hozAlign: 'center', width: 64, sorter: 'number',
                    formatter: function(cell) { return vpPctCell(cell.getValue()); } },
                { title: 'LMP', field: 'lmp_price', hozAlign: 'center', width: 72, sorter: 'number',
                    formatter: function(cell) { return vpMoney(cell.getValue()); } },
                { title: 'Push Prc', field: 'SPRICE', hozAlign: 'center', width: 88, sorter: 'number',
                    headerTooltip: 'S PRC from the same eBay 1 row. The upload control stays on Ebay Analytics.',
                    formatter: function(cell) {
                        const price = vpNum(cell.getValue());
                        if (!(price > 0)) return '<span style="color:#adb5bd;">—</span>';
                        const status = String(cell.getRow().getData().PUSH_PRC_STATUS || '');
                        const color = status === 'pushed' ? '#198754' : (status === 'error' ? '#dc3545' : '#fd7e14');
                        return '<span style="font-weight:600;">$' + price.toFixed(2) + '</span> <i class="fas fa-upload" style="color:' + color + ';"></i>';
                    } },
                { title: 'Age Disc', field: 'age_discount', hozAlign: 'center', width: 72,
                    headerTooltip: 'Age Disc from Std prc vs dil. Same age clock and slabs as Ebay Analytics.',
                    formatter: function(cell) {
                        const n = vpAgeDisc(cell.getRow().getData());
                        if (n === null) return '';
                        return n > 0 ? '<span style="font-weight:700;">' + n + '</span>' : '<span style="color:#adb5bd;">—</span>';
                    } },
                { title: 'Dil Disc', field: 'dil_discount', hozAlign: 'center', width: 72,
                    headerTooltip: 'Dil Disc from Std prc vs dil. INV = 0 stays 0.',
                    formatter: function(cell) {
                        const n = vpDilDisc(cell.getRow().getData());
                        if (n === null) return '';
                        return n > 0 ? '<span style="font-weight:700;">' + n + '</span>' : '<span style="color:#adb5bd;">—</span>';
                    } },
                { title: 'Weight', field: 'wt_act', hozAlign: 'center', width: 78, sorter: 'number',
                    headerTooltip: 'ACT weight from CP Master, in pounds. This picks the Shipping Master weight slab.',
                    formatter: function(cell) {
                        const n = Number(cell.getValue());
                        if (!isFinite(n)) return '<span style="color:#adb5bd;">—</span>';
                        return n.toFixed(2);
                    } },
                { title: 'Buy 2', field: 'buy2', hozAlign: 'center', width: 70,
                    sorter: function(a, b, aRow, bRow) { return vpSum(aRow.getData()).buy2 - vpSum(bRow.getData()).buy2; },
                    headerTooltip: 'SUM of Weight + Dil + Std NPFT for Buy 2.',
                    formatter: function(cell) { return vpBuyCell(cell, 'buy2'); } },
                { title: 'Buy 3', field: 'buy3', hozAlign: 'center', width: 70,
                    sorter: function(a, b, aRow, bRow) { return vpSum(aRow.getData()).buy3 - vpSum(bRow.getData()).buy3; },
                    headerTooltip: 'SUM of Weight + Dil + Std NPFT for Buy 3.',
                    formatter: function(cell) { return vpBuyCell(cell, 'buy3'); } },
                { title: 'Buy 4', field: 'buy4', hozAlign: 'center', width: 70,
                    sorter: function(a, b, aRow, bRow) { return vpSum(aRow.getData()).buy4 - vpSum(bRow.getData()).buy4; },
                    headerTooltip: 'SUM of Weight + Dil + Std NPFT for Buy 4.',
                    formatter: function(cell) { return vpBuyCell(cell, 'buy4'); } }
            ]
        });
        vpTable.on('dataProcessed', function() {
            const n = vpTable.getDataCount('active');
            document.getElementById('vp-rows-badge').textContent = 'Rows: ' + n;
            if (document.getElementById('vpRuleModal').classList.contains('show')) vpPaintModal();
        });
        vpLoadRows();
    </script>
@endsection
