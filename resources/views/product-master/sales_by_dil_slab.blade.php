@extends('layouts.vertical', ['title' => 'Sales by Dil Slab', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator .tabulator-header { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; }
        .tabulator .tabulator-header .tabulator-col { text-align: center; }
        .tabulator .tabulator-row .tabulator-cell { padding: 4px 8px; }
        .sds-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .sds-toolbar h4 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .sds-toolbar .form-control { width: auto; min-width: 170px; }
        .sds-img { width: 40px; height: 40px; object-fit: contain; border-radius: 4px; background: #f1f5f9; }

        .sds-badge {
            display: flex; align-items: flex-start; gap: 10px; margin: 0 0 14px;
            padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; flex-wrap: wrap;
        }
        .sds-canvas-wrap { width: 340px; height: 230px; flex: 0 0 340px; position: relative; overflow: hidden; }
        .sds-canvas-wrap canvas { width: 100% !important; height: 100% !important; }
        .sds-legend { flex: 1 1 520px; min-width: 0; overflow-x: auto; }
        .sds-row { display: flex; align-items: center; gap: 6px; font-size: 11px; line-height: 1.3; padding: 2px 0; min-width: 560px; }
        .sds-row.is-filterable { cursor: pointer; }
        .sds-row.is-filterable:hover { background: #eef2ff; border-radius: 4px; }
        .sds-row.is-active { background: #e0e7ff; border-radius: 4px; }
        .sds-swatch { width: 8px; height: 8px; border-radius: 2px; flex: 0 0 8px; }
        .sds-name { flex: 1 1 auto; min-width: 110px; font-weight: 600; color: #334155; }
        .sds-num { font-weight: 700; min-width: 66px; text-align: right; }
        .sds-cnt { min-width: 46px; text-align: right; color: #64748b; }
        .sds-pct { font-weight: 700; min-width: 58px; text-align: right; }
        .sds-dot { display: inline-block; width: 16px; height: 16px; flex: 0 0 16px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(15, 23, 42, .12); }
        .sds-head { color: #94a3b8; font-size: 10px; font-weight: 600; }
        .sds-total { border-top: 1px dashed #cbd5e1; margin-top: 2px; padding-top: 3px; }
        .sds-neg { color: #dc3545; }
        .sds-pos { color: #198754; }
        .sds-note { font-size: 11px; color: #64748b; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Sales by Dil Slab', 'sub_title' => 'Sales by Dil Slab'])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="sds-toolbar">
                        <h4>Sales by Dil Slab</h4>
                        <input type="search" id="sds-search" class="form-control form-control-sm" placeholder="Search parent / SKU">
                        <button type="button" id="sds-clear" class="btn btn-sm btn-outline-secondary" style="display:none;">Clear slab filter</button>
                        <button type="button" id="sds-refresh" class="btn btn-sm btn-outline-primary" title="Reload from the marketplace order tables">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <span class="sds-note ms-auto" id="sds-asof"></span>
                    </div>

                    <div class="sds-badge">
                        <div class="sds-canvas-wrap"><canvas id="sds-chart"></canvas></div>
                        <div class="sds-legend" id="sds-legend"><span class="text-muted small">Loading sales from all marketplace order tables… this can take a little while the first time.</span></div>
                    </div>
                    <div class="sds-note mb-2" id="sds-channels"></div>

                    <div id="sds-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            // Dil slabs (same as Movement Analysis) plus two buckets so every sold dollar is accounted for.
            const BANDS = [
                { key: '0', label: '0%', color: '#dc3545' },
                { key: '0.1-10', label: '0.1–10%', color: '#fd7e14' },
                { key: '10-25', label: '10–25%', color: '#ffc107' },
                { key: '25-50', label: '25–50%', color: '#28a745' },
                { key: '50-100', label: '50–100%', color: '#e83e8c' },
                { key: 'gt-100', label: '>100%', color: '#4e0dab' },
                { key: 'inv0', label: 'INV 0 (sold)', color: '#6c757d' },
            ];
            const UNMAPPED = { key: 'unmapped', label: 'Not in Product Master', color: '#adb5bd' };

            function esc(v) {
                return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }
            function num(v) { const n = parseFloat(v); return isFinite(n) ? n : 0; }
            // Always in K, e.g. $2,100K.
            function money(n) {
                const v = Math.round(Number(n) || 0);
                if (Math.abs(v) >= 1000) return '$' + Math.round(v / 1000).toLocaleString('en-US') + 'K';
                return '$' + v.toLocaleString('en-US');
            }
            // Per-unit prices stay in whole/cents dollars (the K format is only for totals).
            function unitMoney(n) {
                return '$' + num(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function signedMoney(n) {
                const v = num(n);
                return '<span class="' + (v < 0 ? 'sds-neg' : (v > 0 ? 'sds-pos' : '')) + '">'
                    + (v < 0 ? '-' : '') + unitMoney(Math.abs(v)) + '</span>';
            }
            function pctHtml(v) {
                if (v == null) return '<span class="text-muted">—</span>';
                return '<span class="' + (v < 0 ? 'sds-neg' : (v > 0 ? 'sds-pos' : '')) + '">' + v.toFixed(1) + '%</span>';
            }
            function slabKey(dil) {
                const n = Math.round(num(dil) * 100) / 100;
                if (n === 0) return '0';
                if (n > 100) return 'gt-100';
                if (n >= 50) return '50-100';
                if (n > 25) return '25-50';
                if (n > 10) return '10-25';
                return '0.1-10';
            }
            function bandOf(r) { return num(r.INV) > 0 ? slabKey(r.dil) : 'inv0'; }
            function bandColor(key) {
                const b = BANDS.filter(function (x) { return x.key === key; })[0];
                return b ? b.color : '#6c757d';
            }

            let rows = [];
            let unmapped = null;
            let activeBand = null;
            let chart = null;

            const table = new Tabulator('#sds-table', {
                layout: 'fitColumns',
                height: '60vh',
                placeholder: 'Loading…',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                initialSort: [{ column: 'l30_sales', dir: 'desc' }],
                columns: [
                    {
                        title: 'Image', field: 'image', width: 76, hozAlign: 'center', headerSort: false,
                        formatter: function (cell) {
                            const src = cell.getValue();
                            return src ? '<img class="sds-img" loading="lazy" src="' + esc(src) + '" alt="">' : '';
                        },
                    },
                    { title: 'Parent', field: 'parent', sorter: 'string', widthGrow: 2 },
                    { title: 'SKU', field: 'sku', sorter: 'string', widthGrow: 2 },
                    { title: 'INV', field: 'INV', hozAlign: 'right', sorter: 'number', width: 80, formatter: function (c) { return Math.round(num(c.getValue())).toLocaleString('en-US'); } },
                    {
                        title: 'Dil %', field: 'dil', hozAlign: 'right', sorter: 'number', width: 90,
                        formatter: function (cell) {
                            const d = cell.getData();
                            return '<span style="color:' + bandColor(bandOf(d)) + ';font-weight:700;">' + num(cell.getValue()).toFixed(2) + '%</span>';
                        },
                    },
                    { title: 'Today', field: 'today', hozAlign: 'right', sorter: 'number', width: 100, formatter: function (c) { return money(c.getValue()); } },
                    { title: 'Yesterday', field: 'yesterday', hozAlign: 'right', sorter: 'number', width: 100, formatter: function (c) { return money(c.getValue()); } },
                    { title: 'L30', field: 'l30_sales', hozAlign: 'right', sorter: 'number', width: 100, formatter: function (c) { return money(c.getValue()); } },
                    {
                        title: 'Std Prc', field: 'std_price', hozAlign: 'right', sorter: 'number', width: 90,
                        formatter: function (c) { return c.getValue() == null ? '' : unitMoney(c.getValue()); },
                    },
                    {
                        title: 'NPFT $', field: 'npft_amt', hozAlign: 'right', sorter: 'number', width: 90,
                        headerTooltip: 'Per unit: Std Prc x NPFT %',
                        formatter: function (c) { return c.getValue() == null ? '' : signedMoney(c.getValue()); },
                    },
                    { title: 'NPFT %', field: 'npft', hozAlign: 'right', sorter: 'number', width: 90, formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); } },
                    {
                        title: 'NROI $', field: 'nroi_amt', hozAlign: 'right', sorter: 'number', width: 90,
                        headerTooltip: 'Per unit: LP x NROI %',
                        formatter: function (c) { return c.getValue() == null ? '' : signedMoney(c.getValue()); },
                    },
                    { title: 'NROI %', field: 'nroi', hozAlign: 'right', sorter: 'number', width: 90, formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); } },
                    {
                        title: 'Amz Prc', field: 'amz_price', hozAlign: 'right', sorter: 'number', width: 90,
                        headerTooltip: 'Live Amazon price',
                        formatter: function (c) { return c.getValue() == null ? '' : unitMoney(c.getValue()); },
                    },
                    {
                        title: 'Amz PFT $', field: 'amz_pft', hozAlign: 'right', sorter: 'number', width: 100,
                        headerTooltip: 'Per unit: Amz Prc x 0.80 - LP - Ship - Amz Prc x Amazon Ads %',
                        formatter: function (c) { return c.getValue() == null ? '' : signedMoney(c.getValue()); },
                    },
                    {
                        title: 'Amz PFT %', field: 'amz_pft_pct', hozAlign: 'right', sorter: 'number', width: 100,
                        headerTooltip: 'Amz PFT $ / Amz Prc',
                        formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); },
                    },
                    {
                        title: 'Amz ROI %', field: 'amz_roi_pct', hozAlign: 'right', sorter: 'number', width: 100,
                        headerTooltip: 'Amz PFT $ / LP',
                        formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); },
                    },
                ],
            });

            function passesSearch(d) {
                const q = String(document.getElementById('sds-search').value || '').toLowerCase().trim();
                if (!q) return true;
                return (String(d.parent || '') + ' ' + String(d.sku || '')).toLowerCase().indexOf(q) !== -1;
            }

            // Per band: SKU count, Today / Yesterday / L30 sales, and NPFT / NROI % weighted by each SKU's L30 sales.
            function aggregate(list) {
                const acc = {};
                BANDS.forEach(function (b) { acc[b.key] = { n: 0, today: 0, yest: 0, l30: 0, pw: 0, pv: 0, rw: 0, rv: 0 }; });
                list.forEach(function (r) {
                    const a = acc[bandOf(r)];
                    a.n += 1;
                    a.today += num(r.today); a.yest += num(r.yesterday); a.l30 += num(r.l30_sales);
                    const w = num(r.l30_sales);
                    if (w > 0 && r.npft != null) { a.pw += w; a.pv += w * num(r.npft); }
                    if (w > 0 && r.nroi != null) { a.rw += w; a.rv += w * num(r.nroi); }
                });
                return acc;
            }
            function avg(v, w) { return w > 0 ? v / w : null; }

            function drawBadge() {
                const acc = aggregate(rows.filter(passesSearch));
                const tot = { n: 0, today: 0, yest: 0, l30: 0, pw: 0, pv: 0, rw: 0, rv: 0 };
                BANDS.forEach(function (b) { Object.keys(tot).forEach(function (k) { tot[k] += acc[b.key][k]; }); });

                function rowHtml(b, a, filterable) {
                    return '<div class="sds-row' + (filterable ? ' is-filterable' : '') + (activeBand === b.key ? ' is-active' : '') + '" data-band="' + b.key + '">'
                        + '<span class="sds-swatch" style="background:' + b.color + ';"></span>'
                        + '<span class="sds-name">' + esc(b.label) + '</span>'
                        + '<span class="sds-cnt">' + a.n.toLocaleString('en-US') + '</span>'
                        + '<span class="sds-num">' + money(a.today) + '</span>'
                        + '<span class="sds-num">' + money(a.yest) + '</span>'
                        + '<span class="sds-num">' + money(a.l30) + '</span>'
                        + '<span class="sds-pct">' + pctHtml(avg(a.pv, a.pw)) + '</span>'
                        + '<span class="sds-pct">' + pctHtml(avg(a.rv, a.rw)) + '</span>'
                        + '<span class="sds-dot" style="background:' + b.color + ';"></span></div>';
                }

                let html = '<div class="sds-row sds-head">'
                    + '<span class="sds-swatch" style="visibility:hidden;"></span>'
                    + '<span class="sds-name">Dil slab</span>'
                    + '<span class="sds-cnt">SKU</span><span class="sds-num">Today</span><span class="sds-num">Yesterday</span><span class="sds-num">L30</span>'
                    + '<span class="sds-pct">NPFT %</span><span class="sds-pct">NROI %</span>'
                    + '<span class="sds-dot" style="visibility:hidden;"></span></div>';
                BANDS.forEach(function (b) { html += rowHtml(b, acc[b.key], true); });
                if (unmapped && (unmapped.l30 > 0 || unmapped.today > 0 || unmapped.yesterday > 0)) {
                    html += '<div class="sds-row" title="Orders whose SKU is not in Product Master (not counted in the total row)">'
                        + '<span class="sds-swatch" style="background:' + UNMAPPED.color + ';"></span>'
                        + '<span class="sds-name">' + UNMAPPED.label + '</span>'
                        + '<span class="sds-cnt">' + num(unmapped.skus).toLocaleString('en-US') + '</span>'
                        + '<span class="sds-num">' + money(unmapped.today) + '</span>'
                        + '<span class="sds-num">' + money(unmapped.yesterday) + '</span>'
                        + '<span class="sds-num">' + money(unmapped.l30) + '</span>'
                        + '<span class="sds-pct">—</span><span class="sds-pct">—</span>'
                        + '<span class="sds-dot" style="background:' + UNMAPPED.color + ';"></span></div>';
                }
                html += '<div class="sds-row sds-total">'
                    + '<span class="sds-swatch" style="visibility:hidden;"></span>'
                    + '<span class="sds-name">Total</span>'
                    + '<span class="sds-cnt">' + tot.n.toLocaleString('en-US') + '</span>'
                    + '<span class="sds-num">' + money(tot.today) + '</span>'
                    + '<span class="sds-num">' + money(tot.yest) + '</span>'
                    + '<span class="sds-num">' + money(tot.l30) + '</span>'
                    + '<span class="sds-pct" title="Weighted by L30 sales">' + pctHtml(avg(tot.pv, tot.pw)) + '</span>'
                    + '<span class="sds-pct" title="Weighted by L30 sales">' + pctHtml(avg(tot.rv, tot.rw)) + '</span>'
                    + '<span class="sds-dot" style="visibility:hidden;"></span></div>';
                document.getElementById('sds-legend').innerHTML = html;

                const canvas = document.getElementById('sds-chart');
                if (!canvas || typeof Chart === 'undefined') return;
                if (chart) chart.destroy();
                chart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: BANDS.map(function (b) { return b.label; }),
                        datasets: [{
                            label: 'L30 sales',
                            data: BANDS.map(function (b) { return acc[b.key].l30; }),
                            backgroundColor: BANDS.map(function (b) { return b.color; }),
                            borderWidth: 0, borderRadius: 3, maxBarThickness: 30,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        layout: { padding: { top: 2, right: 2 } },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 45, minRotation: 45, autoSkip: false } },
                            y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { font: { size: 8 }, maxTicksLimit: 4, callback: function (v) { return money(v); } } },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function (ctx) {
                                const b = BANDS[ctx.dataIndex], a = acc[b.key];
                                return [
                                    ' Today ' + money(a.today) + ' · Yesterday ' + money(a.yest),
                                    ' L30 ' + money(a.l30) + ' · ' + a.n + ' SKU',
                                ];
                            } } },
                        },
                        onClick: function (_e, els) { setBand(els && els.length ? BANDS[els[0].index].key : null); },
                    },
                });
            }

            function applyFilters() {
                table.setFilter(function (d) {
                    if (!passesSearch(d)) return false;
                    if (activeBand && bandOf(d) !== activeBand) return false;
                    return true;
                });
                document.getElementById('sds-clear').style.display = activeBand ? '' : 'none';
                drawBadge();
            }
            function setBand(key) {
                activeBand = (key && activeBand === key) ? null : (key || null);
                applyFilters();
            }

            document.getElementById('sds-search').addEventListener('input', applyFilters);
            document.getElementById('sds-clear').addEventListener('click', function () { setBand(null); });
            document.getElementById('sds-legend').addEventListener('click', function (e) {
                const row = e.target.closest('.sds-row.is-filterable');
                if (row) setBand(row.getAttribute('data-band'));
            });

            function load(fresh) {
                const btn = document.getElementById('sds-refresh');
                btn.disabled = true;
                document.getElementById('sds-table').classList.add('opacity-50');
                fetch('/sales-by-dil-slab-data' + (fresh ? '?fresh=1' : ''), {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                }).then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                }).then(function (res) {
                    rows = res.rows || [];
                    unmapped = res.unmapped || null;
                    document.getElementById('sds-asof').textContent =
                        'As of ' + res.as_of + ' PT · Today ' + res.today + ' · Yesterday ' + res.yesterday + ' · L30 from ' + res.l30_from;
                    const ch = res.channels || {};
                    const names = Object.keys(ch).sort(function (a, b) { return ch[b].l30 - ch[a].l30; });
                    document.getElementById('sds-channels').innerHTML =
                        'Channels with orders: ' + (names.length ? names.map(esc).join(', ') : 'none')
                        + ((res.errors || []).length ? ' · <span class="sds-neg">could not read: ' + res.errors.map(esc).join(', ') + '</span>' : '')
                        + ' · Shopify not included.';
                    table.setData(rows).then(applyFilters);
                }).catch(function () {
                    document.getElementById('sds-legend').innerHTML = '<span class="text-danger small">Failed to load sales data.</span>';
                }).finally(function () {
                    btn.disabled = false;
                    document.getElementById('sds-table').classList.remove('opacity-50');
                });
            }
            document.getElementById('sds-refresh').addEventListener('click', function () { load(true); });
            load(false);
        })();
    </script>
@endsection
