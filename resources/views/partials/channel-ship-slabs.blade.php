{{-- Ship: weight-slab shipping cost for OV L30, channel L30, and total INV. Parts: css, buttons, modals, script. --}}
@php $channelShipPart = $channelShipPart ?? 'all'; @endphp

@if($channelShipPart === 'css' || $channelShipPart === 'all')
        #ch-ship-slab-btn {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        #ch-ship-slab-btn:hover,
        #ch-ship-slab-btn:focus {
            background: #115e59;
            border-color: #115e59;
            color: #fff;
        }
        #chShipSlabModal .modal-dialog {
            width: 96vw;
            max-width: 1560px;
            margin: 1vh auto;
        }
        #chShipSlabModal .ch-ship-cols {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            align-items: start;
        }
        #chShipSlabModal .ch-ship-col {
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            background: #fff;
        }
        #chShipSlabModal .ch-ship-pie {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        #chShipSlabModal .ch-ship-pie-canvas {
            position: relative;
            width: 128px;
            height: 128px;
            flex: 0 0 128px;
        }
        #chShipSlabModal .ch-ship-pie-canvas canvas {
            display: block;
            width: 128px !important;
            height: 128px !important;
        }
        #chShipSlabModal .ch-ship-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
            max-height: 140px;
            overflow: auto;
            font-size: 11px;
        }
        #chShipSlabModal .ch-ship-leg-row { display: flex; gap: 6px; align-items: center; margin-bottom: 2px; }
        #chShipSlabModal .ch-ship-swatch { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 8px; }
        #chShipSlabModal .ch-ship-col .table { font-size: 11px; margin-bottom: 0; }
        #chShipSlabModal .ch-ship-col .table th,
        #chShipSlabModal .ch-ship-col .table td { padding: 3px 4px; }
        #chShipSlabModal .ch-ship-total { font-size: 13px; font-weight: 700; color: #166534; }
        @media (max-width: 1100px) {
            #chShipSlabModal .ch-ship-cols { grid-template-columns: 1fr; }
        }
@endif

@if($channelShipPart === 'buttons' || $channelShipPart === 'all')
                    <button type="button" class="btn btn-sm" id="ch-ship-slab-btn"
                        title="Shipping cost by weight slab. OV L30, channel L30, and total INV, with ship dollars.">
                        <i class="fas fa-truck"></i> Ship
                    </button>
@endif

@if($channelShipPart === 'modals' || $channelShipPart === 'all')
    <div class="modal fade" id="chShipSlabModal" tabindex="-1" aria-labelledby="chShipSlabModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="chShipSlabModalLabel">
                        <i class="fas fa-truck me-1"></i> Ship
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Weight slabs match Shipping Master. Ship $ is each SKU’s Product Master ship rate.
                        Totals are that rate times OV L30, <span id="ch-ship-intro-al">A L30</span>, or INV units.
                    </p>
                    <div class="ch-ship-cols">
                        <div class="ch-ship-col">
                            <div class="fw-bold mb-1" style="font-size:12px;color:#334155;">OV L30 sales</div>
                            <div class="ch-ship-total" id="ch-ship-ov-total">—</div>
                            <div class="ch-ship-pie">
                                <div class="ch-ship-pie-canvas"><canvas id="ch-ship-pie-ov"></canvas></div>
                                <div class="ch-ship-pie-legend" id="ch-ship-leg-ov"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Weight slab</th>
                                            <th class="text-end">Ship</th>
                                            <th class="text-end">OV L30</th>
                                            <th class="text-end">Ship total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ch-ship-ov-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ch-ship-col">
                            <div class="fw-bold mb-1" style="font-size:12px;color:#334155;" id="ch-ship-al-title">A L30 sales</div>
                            <div class="ch-ship-total" id="ch-ship-al-total">—</div>
                            <div class="ch-ship-pie">
                                <div class="ch-ship-pie-canvas"><canvas id="ch-ship-pie-al"></canvas></div>
                                <div class="ch-ship-pie-legend" id="ch-ship-leg-al"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Weight slab</th>
                                            <th class="text-end">Ship</th>
                                            <th class="text-end" id="ch-ship-al-col">A L30</th>
                                            <th class="text-end">Ship total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ch-ship-al-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ch-ship-col">
                            <div class="fw-bold mb-1" style="font-size:12px;color:#334155;">Total INV</div>
                            <div class="ch-ship-total" id="ch-ship-inv-total">—</div>
                            <div class="ch-ship-pie">
                                <div class="ch-ship-pie-canvas"><canvas id="ch-ship-pie-inv"></canvas></div>
                                <div class="ch-ship-pie-legend" id="ch-ship-leg-inv"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Weight slab</th>
                                            <th class="text-end">Ship</th>
                                            <th class="text-end">INV</th>
                                            <th class="text-end">Ship total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ch-ship-inv-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if($channelShipPart === 'script' || $channelShipPart === 'all')
        const CH_SHIP_PIE_COLORS = ['#0f766e', '#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b', '#1d4ed8', '#a16207'];
        const chShipPieCharts = {};
        let chShipPieGen = 0;
        let chShipSlabMap = null;

        function chShipMoney(n) {
            const v = Number(n) || 0;
            const sign = v < 0 ? '-' : '';
            return sign + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        function chShipMoney2(n) {
            const v = Number(n) || 0;
            return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function chShipCount(n) {
            return Math.round(Number(n) || 0).toLocaleString('en-US');
        }
        function chShipAlLabel() {
            if (typeof chPromoSoldFieldLabel === 'function') return chPromoSoldFieldLabel();
            if (typeof chPromoCfg !== 'undefined' && chPromoCfg && chPromoCfg.soldFieldLabel) {
                return String(chPromoCfg.soldFieldLabel);
            }
            return 'A L30';
        }
        function chShipAlUnits(d) {
            if (typeof chPromoIsEbayChannel === 'function' && chPromoIsEbayChannel() && typeof chPromoEbaySaleQty === 'function') {
                return chPromoEbaySaleQty(d);
            }
            if (typeof chPromoReverbSoldQty === 'function' && typeof chPromoCfg !== 'undefined' && chPromoCfg && chPromoCfg.soldField) {
                return chPromoReverbSoldQty(d);
            }
            return Number(d && (d.A_L30 != null && d.A_L30 !== '' ? d.A_L30 : (d.al30 != null ? d.al30 : (d.a_l30 != null ? d.a_l30 : d.AL30)))) || 0;
        }
        function chShipActLb(d) {
            const lb = parseFloat(d && d.wt_act);
            if (isFinite(lb) && lb > 0) return Math.round(lb * 100) / 100;
            const kg = parseFloat(d && d.wt_act_kg);
            if (isFinite(kg) && kg > 0) return Math.round(kg * 2.2046226218 * 100) / 100;
            return 0;
        }
        function chShipKeyFromAct(lb) {
            if (!(lb > 0)) return 'lb_0';
            let decl = lb;
            if (lb < 1) {
                const oz = Math.round(lb * 16 * 100) / 100;
                const caps = [2, 4, 8, 12, 15.99];
                decl = 1;
                for (let i = 0; i < caps.length; i++) {
                    if (oz <= caps[i] + 1e-9) {
                        decl = caps[i] >= 15.99 ? 1 : Math.round((caps[i] / 16) * 10000) / 10000;
                        break;
                    }
                }
            } else if (lb <= 1 + 1e-9) {
                decl = 1;
            } else {
                const maxes = [2, 3, 4, 5, 10, 20, 25, 30, 40, 50, null];
                decl = Math.ceil(lb - 1e-9);
                for (let i = 0; i < maxes.length; i++) {
                    if (maxes[i] === null) break;
                    if (lb <= maxes[i] + 1e-9) { decl = maxes[i]; break; }
                }
            }
            if (Math.abs(decl - 1) < 1e-9) return 'oz_1599';
            if (decl > 0 && decl <= 0.25) return 'oz_4';
            if (decl <= 0.5) return 'oz_6';
            if (decl <= 0.75) return 'oz_12';
            if (decl > 1 && decl <= 2) return 'lb_101_2';
            if (decl <= 3) return 'lb_201_3';
            if (decl <= 4) return 'lb_301_4';
            if (decl <= 5) return 'lb_401_5';
            if (decl <= 10) return 'lb_501_10';
            if (decl <= 20) return 'lb_1001_20';
            if (decl <= 25) return 'lb_20_30';
            if (decl <= 30) return 'lb_2501_30';
            if (decl <= 40) return 'lb_30_40';
            if (decl <= 50) return 'lb_40_50';
            return 'lb_gt50';
        }
        function chShipSlabKey(d) {
            if (d && d.ship_slab) return String(d.ship_slab);
            const sku = (typeof chPromoSku === 'function' ? chPromoSku(d) : (d && (d.sku || d.SKU))) || '';
            const key = String(sku).trim().toUpperCase();
            const map = chShipSlabMap && chShipSlabMap.bySku;
            if (key && map && map[key]) return map[key];
            return chShipKeyFromAct(chShipActLb(d));
        }
        function chShipEachChild(fn) {
            const rows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length)
                ? allTableData
                : [];
            if (rows.length) {
                rows.forEach(function(d) {
                    if (typeof chPromoIsChildRow === 'function' && !chPromoIsChildRow(d)) return;
                    fn(d);
                });
                return;
            }
            if (typeof chPromoEachTableRow === 'function') chPromoEachTableRow(fn);
        }
        function chShipRateText(dollars, units, rates) {
            if (units > 0) return chShipMoney2(dollars / units);
            const keys = Object.keys(rates || {});
            if (keys.length === 1) return chShipMoney2(parseFloat(keys[0]));
            if (keys.length > 1) return 'mix';
            return '—';
        }
        function chShipBuckets() {
            const slabs = (chShipSlabMap && Array.isArray(chShipSlabMap.slabs) && chShipSlabMap.slabs.length)
                ? chShipSlabMap.slabs
                : [{ key: 'lb_0', label: '0 lb' }];
            const buckets = {};
            slabs.forEach(function(slab) {
                buckets[slab.key] = { key: slab.key, label: slab.label, ovUnits: 0, ovShip: 0, alUnits: 0, alShip: 0, invUnits: 0, invShip: 0, rates: {} };
            });
            chShipEachChild(function(d) {
                const key = buckets[chShipSlabKey(d)] ? chShipSlabKey(d) : 'lb_0';
                if (!buckets[key]) {
                    buckets[key] = { key: key, label: key, ovUnits: 0, ovShip: 0, alUnits: 0, alShip: 0, invUnits: 0, invShip: 0, rates: {} };
                }
                const ship = (typeof chPromoShipCost === 'function') ? chPromoShipCost(d) : (parseFloat(d.Ship_productmaster) || 0);
                const ov = (typeof chPromoOvL30 === 'function') ? chPromoOvL30(d) : (Number(d.ov_l30) || Number(d.L30) || 0);
                const al = chShipAlUnits(d);
                const inv = (typeof chPromoInv === 'function') ? chPromoInv(d) : (Number(d.INV) || 0);
                const b = buckets[key];
                b.ovUnits += ov;
                b.ovShip += ship * ov;
                b.alUnits += al;
                b.alShip += ship * al;
                b.invUnits += inv;
                b.invShip += ship * inv;
                if (ship > 0 || ov > 0 || al > 0 || inv > 0) {
                    const rateKey = ship.toFixed(2);
                    b.rates[rateKey] = (b.rates[rateKey] || 0) + 1;
                }
            });
            return slabs.map(function(slab) { return buckets[slab.key]; }).concat(
                Object.keys(buckets).filter(function(key) {
                    return !slabs.some(function(slab) { return slab.key === key; });
                }).map(function(key) { return buckets[key]; })
            );
        }
        function chShipSectionHtml(rows, unitsKey, shipKey) {
            let units = 0;
            let dollars = 0;
            const body = rows.map(function(row) {
                units += row[unitsKey];
                dollars += row[shipKey];
                const quiet = row[unitsKey] <= 0 && row[shipKey] <= 0;
                return '<tr' + (quiet ? ' class="text-muted"' : '') + '>'
                    + '<td>' + row.label + '</td>'
                    + '<td class="text-end">' + chShipRateText(row[shipKey], row[unitsKey], row.rates) + '</td>'
                    + '<td class="text-end">' + chShipCount(row[unitsKey]) + '</td>'
                    + '<td class="text-end">' + chShipMoney(row[shipKey]) + '</td>'
                    + '</tr>';
            });
            body.push('<tr class="fw-semibold"><td>Total</td><td class="text-end"></td>'
                + '<td class="text-end">' + chShipCount(units) + '</td>'
                + '<td class="text-end">' + chShipMoney(dollars) + '</td></tr>');
            return { html: body.join(''), units: units, dollars: dollars };
        }
        function chShipLegend(el, slices, counts, dollars) {
            const unitTotal = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const dollarTotal = slices.reduce(function(sum, s) { return sum + (dollars[s.key] || 0); }, 0);
            const html = slices.map(function(s) {
                const n = counts[s.key] || 0;
                const base = dollarTotal > 0 ? (dollars[s.key] || 0) / dollarTotal : (unitTotal > 0 ? n / unitTotal : 0);
                const pct = Math.round(base * 100);
                return '<div class="ch-ship-leg-row"><span class="ch-ship-swatch" style="background:' + s.color + '"></span>'
                    + '<span style="flex:1">' + s.label + '</span><strong>' + chShipCount(n) + '</strong>'
                    + '<span class="text-muted">' + pct + '%</span></div>';
            }).join('');
            $(el).html(html);
        }
        function chShipDrawPieNow(id, slices, values) {
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : chShipPieCharts[id];
            if (bound) bound.destroy();
            chShipPieCharts[id] = null;
            const raw = slices.map(function(s) { return values[s.key] || 0; });
            const total = raw.reduce(function(sum, n) { return sum + n; }, 0);
            chShipPieCharts[id] = new Chart(canvas.getContext('2d'), {
                type: 'pie',
                data: {
                    labels: slices.map(function(s) { return s.label; }),
                    datasets: [{
                        data: total > 0 ? raw : slices.map(function() { return 1; }),
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
        function chShipEnsureChart() {
            if (typeof Chart !== 'undefined') return Promise.resolve();
            if (typeof window.loadChartJs === 'function') return window.loadChartJs();
            if (window.__chShipChartPromise) return window.__chShipChartPromise;
            window.__chShipChartPromise = new Promise(function(resolve, reject) {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
                s.onload = function() { resolve(); };
                s.onerror = reject;
                document.head.appendChild(s);
            });
            return window.__chShipChartPromise;
        }
        function chShipDrawPies(jobs) {
            const gen = ++chShipPieGen;
            const draw = function() {
                if (gen !== chShipPieGen) return;
                jobs.forEach(function(job) { chShipDrawPieNow(job.id, job.slices, job.values); });
            };
            chShipEnsureChart().then(draw).catch(function() {});
        }
        function chShipPieValues(slices, counts, dollars) {
            let dollarTotal = 0;
            slices.forEach(function(s) { dollarTotal += dollars[s.key] || 0; });
            const values = {};
            slices.forEach(function(s) {
                values[s.key] = dollarTotal > 0 ? (dollars[s.key] || 0) : (counts[s.key] || 0);
            });
            return { values: values, dollars: dollars };
        }
        function chShipActiveSlices(rows, unitsKey, shipKey) {
            return rows.filter(function(row) { return row[unitsKey] > 0 || row[shipKey] > 0; }).map(function(row, i) {
                return { key: row.key, label: row.label, color: CH_SHIP_PIE_COLORS[i % CH_SHIP_PIE_COLORS.length] };
            });
        }
        function chShipRefresh() {
            const alLabel = chShipAlLabel();
            $('#ch-ship-al-title').text(alLabel + ' sales');
            $('#ch-ship-al-col').text(alLabel);
            $('#ch-ship-intro-al').text(alLabel);
            const rows = chShipBuckets();
            const ov = chShipSectionHtml(rows, 'ovUnits', 'ovShip');
            const al = chShipSectionHtml(rows, 'alUnits', 'alShip');
            const inv = chShipSectionHtml(rows, 'invUnits', 'invShip');
            $('#ch-ship-ov-tbody').html(ov.html);
            $('#ch-ship-al-tbody').html(al.html);
            $('#ch-ship-inv-tbody').html(inv.html);
            $('#ch-ship-ov-total').text(chShipCount(ov.units) + ' units · ' + chShipMoney(ov.dollars) + ' ship');
            $('#ch-ship-al-total').text(chShipCount(al.units) + ' units · ' + chShipMoney(al.dollars) + ' ship');
            $('#ch-ship-inv-total').text(chShipCount(inv.units) + ' units · ' + chShipMoney(inv.dollars) + ' ship');
            const ovSlices = chShipActiveSlices(rows, 'ovUnits', 'ovShip');
            const alSlices = chShipActiveSlices(rows, 'alUnits', 'alShip');
            const invSlices = chShipActiveSlices(rows, 'invUnits', 'invShip');
            const ovCounts = {};
            const ovDollars = {};
            const alCounts = {};
            const alDollars = {};
            const invCounts = {};
            const invDollars = {};
            rows.forEach(function(row) {
                ovCounts[row.key] = row.ovUnits;
                ovDollars[row.key] = row.ovShip;
                alCounts[row.key] = row.alUnits;
                alDollars[row.key] = row.alShip;
                invCounts[row.key] = row.invUnits;
                invDollars[row.key] = row.invShip;
            });
            const ovPie = chShipPieValues(ovSlices, ovCounts, ovDollars);
            const alPie = chShipPieValues(alSlices, alCounts, alDollars);
            const invPie = chShipPieValues(invSlices, invCounts, invDollars);
            chShipLegend('#ch-ship-leg-ov', ovSlices, ovCounts, ovPie.dollars);
            chShipLegend('#ch-ship-leg-al', alSlices, alCounts, alPie.dollars);
            chShipLegend('#ch-ship-leg-inv', invSlices, invCounts, invPie.dollars);
            const emptyPie = [{ key: 'none', label: 'None', color: '#e2e8f0' }];
            chShipDrawPies([
                { id: 'ch-ship-pie-ov', slices: ovSlices.length ? ovSlices : emptyPie, values: ovSlices.length ? ovPie.values : { none: 0 } },
                { id: 'ch-ship-pie-al', slices: alSlices.length ? alSlices : emptyPie, values: alSlices.length ? alPie.values : { none: 0 } },
                { id: 'ch-ship-pie-inv', slices: invSlices.length ? invSlices : emptyPie, values: invSlices.length ? invPie.values : { none: 0 } },
            ]);
        }
        function chShipLoadMap() {
            if (chShipSlabMap) return $.Deferred().resolve(chShipSlabMap).promise();
            const url = (typeof CH_PROMO_RULES_BASE === 'string' ? CH_PROMO_RULES_BASE : '') + '/ship-slabs';
            return $.getJSON(url).then(function(res) {
                chShipSlabMap = {
                    slabs: (res && Array.isArray(res.slabs)) ? res.slabs : [],
                    bySku: (res && res.by_sku) ? res.by_sku : {},
                };
                return chShipSlabMap;
            });
        }
        function bindChShipSlabUi() {
            $('#ch-ship-slab-btn').off('click.chship').on('click.chship', function(e) {
                e.preventDefault();
                const el = document.getElementById('chShipSlabModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $('#chShipSlabModal').off('shown.bs.modal.chship').on('shown.bs.modal.chship', function() {
                $('#ch-ship-ov-total, #ch-ship-al-total, #ch-ship-inv-total').text('Loading…');
                chShipLoadMap().always(function() { chShipRefresh(); });
            });
            $('#chShipSlabModal').off('hidden.bs.modal.chship').on('hidden.bs.modal.chship', function() {
                chShipPieGen++;
                Object.keys(chShipPieCharts).forEach(function(id) {
                    if (chShipPieCharts[id]) { chShipPieCharts[id].destroy(); chShipPieCharts[id] = null; }
                });
            });
        }
        $(function() { bindChShipSlabUi(); });
@endif
