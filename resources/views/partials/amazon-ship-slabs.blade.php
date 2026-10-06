{{-- Ship: weight-slab shipping cost for OV L30, A L30, and total INV. Parts: css, buttons, modals, script. --}}
@php $amazonShipPart = $amazonShipPart ?? 'all'; @endphp

@if($amazonShipPart === 'css' || $amazonShipPart === 'all')
        #amz-ship-slab-btn {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
        #amz-ship-slab-btn:hover,
        #amz-ship-slab-btn:focus {
            background: #115e59;
            border-color: #115e59;
            color: #fff;
        }
        #amzShipSlabModal .modal-dialog {
            width: 96vw;
            max-width: 1560px;
            margin: 1vh auto;
        }
        #amzShipSlabModal .amz-ship-cols {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            align-items: start;
        }
        #amzShipSlabModal .amz-ship-col {
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            background: #fff;
        }
        #amzShipSlabModal .amz-ship-pie {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        #amzShipSlabModal .amz-ship-pie-canvas {
            position: relative;
            width: 128px;
            height: 128px;
            flex: 0 0 128px;
        }
        #amzShipSlabModal .amz-ship-pie-canvas canvas {
            display: block;
            width: 128px !important;
            height: 128px !important;
        }
        #amzShipSlabModal .amz-ship-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
            max-height: 140px;
            overflow: auto;
            font-size: 11px;
        }
        #amzShipSlabModal .amz-ship-leg-row { display: flex; gap: 6px; align-items: center; margin-bottom: 2px; }
        #amzShipSlabModal .amz-ship-swatch { width: 8px; height: 8px; border-radius: 50%; flex: 0 0 8px; }
        #amzShipSlabModal .amz-ship-col .table { font-size: 11px; margin-bottom: 0; }
        #amzShipSlabModal .amz-ship-col .table th,
        #amzShipSlabModal .amz-ship-col .table td { padding: 3px 4px; }
        #amzShipSlabModal .amz-ship-total { font-size: 13px; font-weight: 700; color: #166534; }
        @media (max-width: 1100px) {
            #amzShipSlabModal .amz-ship-cols { grid-template-columns: 1fr; }
        }
@endif

@if($amazonShipPart === 'buttons' || $amazonShipPart === 'all')
                    <button type="button" class="btn btn-sm" id="amz-ship-slab-btn"
                        title="Shipping cost by weight slab. OV L30, A L30, and total INV, with ship dollars.">
                        <i class="fas fa-truck"></i> Ship
                    </button>
@endif

@if($amazonShipPart === 'modals' || $amazonShipPart === 'all')
    <div class="modal fade" id="amzShipSlabModal" tabindex="-1" aria-labelledby="amzShipSlabModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6" id="amzShipSlabModalLabel">
                        <i class="fas fa-truck me-1"></i> Ship
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2">
                        Weight slabs match Shipping Master. Ship $ is each SKU’s Product Master ship rate.
                        Totals are that rate times OV L30, A L30, or INV units.
                    </p>
                    <div class="amz-ship-cols">
                        <div class="amz-ship-col">
                            <div class="fw-bold mb-1" style="font-size:12px;color:#334155;">OV L30 sales</div>
                            <div class="amz-ship-total" id="amz-ship-ov-total">—</div>
                            <div class="amz-ship-pie">
                                <div class="amz-ship-pie-canvas"><canvas id="amz-ship-pie-ov"></canvas></div>
                                <div class="amz-ship-pie-legend" id="amz-ship-leg-ov"></div>
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
                                    <tbody id="amz-ship-ov-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="amz-ship-col">
                            <div class="fw-bold mb-1" style="font-size:12px;color:#334155;">A L30 sales</div>
                            <div class="amz-ship-total" id="amz-ship-al-total">—</div>
                            <div class="amz-ship-pie">
                                <div class="amz-ship-pie-canvas"><canvas id="amz-ship-pie-al"></canvas></div>
                                <div class="amz-ship-pie-legend" id="amz-ship-leg-al"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Weight slab</th>
                                            <th class="text-end">Ship</th>
                                            <th class="text-end">A L30</th>
                                            <th class="text-end">Ship total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="amz-ship-al-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="amz-ship-col">
                            <div class="fw-bold mb-1" style="font-size:12px;color:#334155;">Total INV</div>
                            <div class="amz-ship-total" id="amz-ship-inv-total">—</div>
                            <div class="amz-ship-pie">
                                <div class="amz-ship-pie-canvas"><canvas id="amz-ship-pie-inv"></canvas></div>
                                <div class="amz-ship-pie-legend" id="amz-ship-leg-inv"></div>
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
                                    <tbody id="amz-ship-inv-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if($amazonShipPart === 'script' || $amazonShipPart === 'all')
        const AMZ_SHIP_SLABS = @json($shipSlabs ?? []);
        const AMZ_SHIP_PIE_COLORS = ['#0f766e', '#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#0ea5e9', '#64748b', '#1d4ed8', '#a16207'];
        const amzShipPieCharts = {};
        let amzShipPieGen = 0;

        function amzShipMoney(n) {
            const v = Number(n) || 0;
            const sign = v < 0 ? '-' : '';
            return sign + '$' + Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
        function amzShipMoney2(n) {
            const v = Number(n) || 0;
            return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function amzShipCount(n) {
            return Math.round(Number(n) || 0).toLocaleString('en-US');
        }
        function amzShipEachChild(fn) {
            const rows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData)) ? allTableData : [];
            rows.forEach(function(d) {
                if (typeof amzPefIsChildRow === 'function' && amzPefIsChildRow(d)) fn(d);
            });
        }
        function amzShipRateText(dollars, units, rates) {
            if (units > 0) return amzShipMoney2(dollars / units);
            const keys = Object.keys(rates || {});
            if (keys.length === 1) return amzShipMoney2(parseFloat(keys[0]));
            if (keys.length > 1) return 'mix';
            return '—';
        }
        function amzShipBuckets() {
            const slabs = (Array.isArray(AMZ_SHIP_SLABS) && AMZ_SHIP_SLABS.length)
                ? AMZ_SHIP_SLABS
                : [{ key: 'lb_0', label: '0 lb' }];
            const buckets = {};
            slabs.forEach(function(slab) {
                buckets[slab.key] = { key: slab.key, label: slab.label, ovUnits: 0, ovShip: 0, alUnits: 0, alShip: 0, invUnits: 0, invShip: 0, rates: {} };
            });
            amzShipEachChild(function(d) {
                const key = buckets[d.ship_slab] ? d.ship_slab : 'lb_0';
                if (!buckets[key]) {
                    buckets[key] = { key: key, label: key, ovUnits: 0, ovShip: 0, alUnits: 0, alShip: 0, invUnits: 0, invShip: 0, rates: {} };
                }
                const ship = parseFloat(d.Ship_productmaster) || 0;
                const ov = (typeof amzPefOvL30 === 'function') ? amzPefOvL30(d) : (Number(d.ov_l30) || 0);
                const al = (typeof amzPefAL30 === 'function') ? amzPefAL30(d) : (Number(d.A_L30) || 0);
                const inv = (typeof amzPefInv === 'function') ? amzPefInv(d) : (Number(d.INV) || 0);
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
        function amzShipSectionHtml(rows, unitsKey, shipKey) {
            let units = 0;
            let dollars = 0;
            const body = rows.map(function(row) {
                units += row[unitsKey];
                dollars += row[shipKey];
                const quiet = row[unitsKey] <= 0 && row[shipKey] <= 0;
                return '<tr' + (quiet ? ' class="text-muted"' : '') + '>'
                    + '<td>' + row.label + '</td>'
                    + '<td class="text-end">' + amzShipRateText(row[shipKey], row[unitsKey], row.rates) + '</td>'
                    + '<td class="text-end">' + amzShipCount(row[unitsKey]) + '</td>'
                    + '<td class="text-end">' + amzShipMoney(row[shipKey]) + '</td>'
                    + '</tr>';
            });
            body.push('<tr class="fw-semibold"><td>Total</td><td class="text-end"></td>'
                + '<td class="text-end">' + amzShipCount(units) + '</td>'
                + '<td class="text-end">' + amzShipMoney(dollars) + '</td></tr>');
            return { html: body.join(''), units: units, dollars: dollars };
        }
        function amzShipLegend(el, slices, counts, dollars) {
            const unitTotal = slices.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
            const dollarTotal = slices.reduce(function(sum, s) { return sum + (dollars[s.key] || 0); }, 0);
            const html = slices.map(function(s) {
                const n = counts[s.key] || 0;
                const base = dollarTotal > 0 ? (dollars[s.key] || 0) / dollarTotal : (unitTotal > 0 ? n / unitTotal : 0);
                const pct = Math.round(base * 100);
                return '<div class="amz-ship-leg-row"><span class="amz-ship-swatch" style="background:' + s.color + '"></span>'
                    + '<span style="flex:1">' + s.label + '</span><strong>' + amzShipCount(n) + '</strong>'
                    + '<span class="text-muted">' + pct + '%</span></div>';
            }).join('');
            $(el).html(html);
        }
        function amzShipDrawPieNow(id, slices, values) {
            const canvas = document.getElementById(id);
            if (!canvas || typeof Chart === 'undefined') return;
            const bound = (typeof Chart.getChart === 'function') ? Chart.getChart(canvas) : amzShipPieCharts[id];
            if (bound) bound.destroy();
            amzShipPieCharts[id] = null;
            const raw = slices.map(function(s) { return values[s.key] || 0; });
            const total = raw.reduce(function(sum, n) { return sum + n; }, 0);
            amzShipPieCharts[id] = new Chart(canvas.getContext('2d'), {
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
        function amzShipDrawPies(jobs) {
            const gen = ++amzShipPieGen;
            const draw = function() {
                if (gen !== amzShipPieGen) return;
                jobs.forEach(function(job) { amzShipDrawPieNow(job.id, job.slices, job.values); });
            };
            if (typeof window.loadChartJs === 'function') {
                window.loadChartJs().then(draw).catch(function() {});
                return;
            }
            draw();
        }
        function amzShipPieValues(slices, counts, dollars) {
            let dollarTotal = 0;
            slices.forEach(function(s) { dollarTotal += dollars[s.key] || 0; });
            const values = {};
            slices.forEach(function(s) {
                values[s.key] = dollarTotal > 0 ? (dollars[s.key] || 0) : (counts[s.key] || 0);
            });
            return { values: values, dollars: dollars };
        }
        function amzShipActiveSlices(rows, unitsKey, shipKey) {
            return rows.filter(function(row) { return row[unitsKey] > 0 || row[shipKey] > 0; }).map(function(row, i) {
                return { key: row.key, label: row.label, color: AMZ_SHIP_PIE_COLORS[i % AMZ_SHIP_PIE_COLORS.length] };
            });
        }
        function amzShipRefresh() {
            const rows = amzShipBuckets();
            const ov = amzShipSectionHtml(rows, 'ovUnits', 'ovShip');
            const al = amzShipSectionHtml(rows, 'alUnits', 'alShip');
            const inv = amzShipSectionHtml(rows, 'invUnits', 'invShip');
            $('#amz-ship-ov-tbody').html(ov.html);
            $('#amz-ship-al-tbody').html(al.html);
            $('#amz-ship-inv-tbody').html(inv.html);
            $('#amz-ship-ov-total').text(amzShipCount(ov.units) + ' units · ' + amzShipMoney(ov.dollars) + ' ship');
            $('#amz-ship-al-total').text(amzShipCount(al.units) + ' units · ' + amzShipMoney(al.dollars) + ' ship');
            $('#amz-ship-inv-total').text(amzShipCount(inv.units) + ' units · ' + amzShipMoney(inv.dollars) + ' ship');
            const ovSlices = amzShipActiveSlices(rows, 'ovUnits', 'ovShip');
            const alSlices = amzShipActiveSlices(rows, 'alUnits', 'alShip');
            const invSlices = amzShipActiveSlices(rows, 'invUnits', 'invShip');
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
            const ovPie = amzShipPieValues(ovSlices, ovCounts, ovDollars);
            const alPie = amzShipPieValues(alSlices, alCounts, alDollars);
            const invPie = amzShipPieValues(invSlices, invCounts, invDollars);
            amzShipLegend('#amz-ship-leg-ov', ovSlices, ovCounts, ovPie.dollars);
            amzShipLegend('#amz-ship-leg-al', alSlices, alCounts, alPie.dollars);
            amzShipLegend('#amz-ship-leg-inv', invSlices, invCounts, invPie.dollars);
            const emptyPie = [{ key: 'none', label: 'None', color: '#e2e8f0' }];
            amzShipDrawPies([
                { id: 'amz-ship-pie-ov', slices: ovSlices.length ? ovSlices : emptyPie, values: ovSlices.length ? ovPie.values : { none: 0 } },
                { id: 'amz-ship-pie-al', slices: alSlices.length ? alSlices : emptyPie, values: alSlices.length ? alPie.values : { none: 0 } },
                { id: 'amz-ship-pie-inv', slices: invSlices.length ? invSlices : emptyPie, values: invSlices.length ? invPie.values : { none: 0 } },
            ]);
        }
        function bindAmzShipSlabUi() {
            $('#amz-ship-slab-btn').off('click.amzship').on('click.amzship', function(e) {
                e.preventDefault();
                const el = document.getElementById('amzShipSlabModal');
                if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
            });
            $('#amzShipSlabModal').off('shown.bs.modal.amzship').on('shown.bs.modal.amzship', function() {
                amzShipRefresh();
            });
            $('#amzShipSlabModal').off('hidden.bs.modal.amzship').on('hidden.bs.modal.amzship', function() {
                amzShipPieGen++;
                Object.keys(amzShipPieCharts).forEach(function(id) {
                    if (amzShipPieCharts[id]) { amzShipPieCharts[id].destroy(); amzShipPieCharts[id] = null; }
                });
            });
        }
        $(function() { bindAmzShipSlabUi(); });
@endif
