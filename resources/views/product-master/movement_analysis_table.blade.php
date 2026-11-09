@extends('layouts.vertical', ['title' => 'Movement Analysis', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator .tabulator-header { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; }
        .tabulator .tabulator-header .tabulator-col { text-align: center; }
        .tabulator .tabulator-row .tabulator-cell { padding: 4px 8px; }
        .mat-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
        .mat-toolbar h4 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .mat-toolbar .form-control, .mat-toolbar .form-select { width: auto; min-width: 150px; }
        .mat-img { width: 40px; height: 40px; object-fit: contain; border-radius: 4px; background: #f1f5f9; }

        /* DIL badges (bar chart + legend) — same look as Movement Analysis Count */
        .mat-dil-pies { display: flex; gap: 10px; margin: 0 0 14px; flex-wrap: wrap; }
        .mat-dil-wrap {
            display: flex; align-items: flex-start; gap: 10px;
            flex: 1 1 320px; min-width: 340px; max-width: 560px;
            padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;
        }
        .mat-dil-canvas-wrap { width: 180px; height: 140px; flex: 0 0 180px; overflow: hidden; position: relative; }
        .mat-dil-canvas-wrap canvas { width: 100% !important; height: 100% !important; }
        .mat-dil-legend { flex: 1 1 auto; min-width: 0; max-height: 210px; overflow-y: auto; }
        .mat-dil-row { display: flex; align-items: center; gap: 6px; font-size: 11px; line-height: 1.3; padding: 1px 0; }
        .mat-dil-row.is-filterable { cursor: pointer; }
        .mat-dil-row.is-filterable:hover { background: #eef2ff; border-radius: 4px; }
        .mat-dil-row.is-active { background: #e0e7ff; border-radius: 4px; }
        .mat-dil-swatch { width: 8px; height: 8px; border-radius: 2px; flex: 0 0 8px; }
        .mat-dil-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        .mat-dil-val { font-weight: 700; min-width: 48px; text-align: right; }
        .mat-dil-pct { color: #64748b; min-width: 36px; text-align: right; }
        .mat-dil-dot {
            display: inline-block; width: 16px; height: 16px; flex: 0 0 16px; border-radius: 50%;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Movement Analysis', 'sub_title' => 'Movement Analysis'])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="mat-toolbar">
                        <h4>Movement Analysis</h4>
                        <input type="search" id="mat-search" class="form-control form-control-sm" placeholder="Search parent / SKU">
                        <select id="mat-inv-filter" class="form-select form-select-sm">
                            <option value="all">INV: All</option>
                            <option value="gt0">INV &gt; 0</option>
                            <option value="0">INV = 0</option>
                        </select>
                        <button type="button" id="mat-clear-band" class="btn btn-sm btn-outline-secondary" style="display:none;">Clear Dil filter</button>
                        <span class="text-muted small ms-auto" id="mat-count"></span>
                    </div>

                    <div class="mat-dil-pies">
                        <div class="mat-dil-wrap">
                            <div class="mat-dil-canvas-wrap"><canvas id="mat-dil-count-chart"></canvas></div>
                            <div class="mat-dil-legend" id="mat-dil-count-legend"></div>
                        </div>
                        <div class="mat-dil-wrap">
                            <div class="mat-dil-canvas-wrap"><canvas id="mat-dil-amz-chart"></canvas></div>
                            <div class="mat-dil-legend" id="mat-dil-amz-legend"></div>
                        </div>
                        <div class="mat-dil-wrap">
                            <div class="mat-dil-canvas-wrap"><canvas id="mat-dil-std-chart"></canvas></div>
                            <div class="mat-dil-legend" id="mat-dil-std-legend"></div>
                        </div>
                        <div class="mat-dil-wrap">
                            <div class="mat-dil-canvas-wrap"><canvas id="mat-dil-lp-chart"></canvas></div>
                            <div class="mat-dil-legend" id="mat-dil-lp-legend"></div>
                        </div>
                    </div>

                    <div id="movement-analysis-table"></div>
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
            const SLABS = [
                { key: '0', label: '0%', color: '#dc3545' },
                { key: '0.1-10', label: '0.1–10%', color: '#fd7e14' },
                { key: '10-25', label: '10–25%', color: '#ffc107' },
                { key: '25-50', label: '25–50%', color: '#28a745' },
                { key: '50-100', label: '50–100%', color: '#e83e8c' },
                { key: 'gt-100', label: '>100%', color: '#4e0dab' },
            ];
            // One entry per badge: how to value a SKU row, and how to format it.
            const METRICS = [
                { id: 'count', title: 'Dil', head: 'SKU', money: false, valueOf: function () { return 1; } },
                { id: 'amz', title: 'Amz $', head: '$', money: true, valueOf: function (r) { return num(r.amz_value) > 0 ? num(r.amz_value) : num(r.INV) * num(r.amz_price); } },
                { id: 'std', title: 'Std Prc $', head: '$', money: true, valueOf: function (r) { return num(r.INV) * num(r.std_price); } },
                { id: 'lp', title: 'LP $', head: '$', money: true, valueOf: function (r) { return num(r.INV) * num(r.lp); } },
            ];

            function esc(v) {
                return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }
            function num(v) { const n = parseFloat(v); return isFinite(n) ? n : 0; }
            function money(n) {
                const v = Math.round(Number(n) || 0), abs = Math.abs(v);
                // Always in K (e.g. 2.1M shows as $2,100K).
                if (abs >= 1000) return '$' + Math.round(v / 1000).toLocaleString('en-US') + 'K';
                return '$' + v.toLocaleString('en-US');
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
            function slabColor(dil) {
                const k = slabKey(dil);
                return SLABS.filter(function (s) { return s.key === k; })[0].color;
            }
            function isParent(r) {
                if (!r) return false;
                if (r.is_parent === true || r.is_parent === 1 || r.is_parent === '1') return true;
                return String(r.sku || '').toUpperCase().indexOf('PARENT') !== -1;
            }
            // Shares that always add up to 100 (largest remainder).
            function shares(values) {
                const total = SLABS.reduce(function (s, x) { return s + (values[x.key] || 0); }, 0);
                const out = {};
                SLABS.forEach(function (s) { out[s.key] = 0; });
                if (total <= 0) return out;
                const parts = SLABS.map(function (s) {
                    const exact = ((values[s.key] || 0) / total) * 100;
                    return { key: s.key, floor: Math.floor(exact), frac: exact - Math.floor(exact) };
                });
                let need = 100 - parts.reduce(function (s, p) { return s + p.floor; }, 0);
                parts.slice().sort(function (a, b) { return b.frac - a.frac; }).forEach(function (p) {
                    if (need > 0) { p.floor += 1; need -= 1; }
                });
                parts.forEach(function (p) { out[p.key] = p.floor; });
                return out;
            }

            let allRows = [];
            let activeBand = null;
            const charts = {};

            // Filters coming from the toolbar (search + INV). The Dil band is applied on top for the table only,
            // so the badges still show every band while one is selected.
            function passesToolbar(d) {
                const q = String(document.getElementById('mat-search').value || '').toLowerCase().trim();
                const inv = document.getElementById('mat-inv-filter').value;
                if (inv === 'gt0' && num(d.INV) <= 0) return false;
                if (inv === '0' && num(d.INV) > 0) return false;
                if (q && (String(d.parent || '') + ' ' + String(d.sku || '')).toLowerCase().indexOf(q) === -1) return false;
                return true;
            }
            function rowBand(d) { return num(d.INV) > 0 ? slabKey(d.dil) : null; }

            function drawBadges() {
                const rows = allRows.filter(passesToolbar).filter(function (r) { return num(r.INV) > 0; });
                METRICS.forEach(function (m) {
                    const values = {};
                    SLABS.forEach(function (s) { values[s.key] = 0; });
                    rows.forEach(function (r) { values[slabKey(r.dil)] += m.valueOf(r); });
                    const fmt = m.money ? money : function (n) { return String(Math.round(n)); };
                    const pct = shares(values);
                    const total = SLABS.reduce(function (s, x) { return s + values[x.key]; }, 0);

                    document.getElementById('mat-dil-' + m.id + '-legend').innerHTML =
                        '<div class="mat-dil-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
                        + '<span class="mat-dil-swatch" style="visibility:hidden;"></span>'
                        + '<span class="mat-dil-name">' + esc(m.title) + '</span>'
                        + '<span class="mat-dil-val">' + m.head + '</span>'
                        + '<span class="mat-dil-pct">of total</span>'
                        + '<span class="mat-dil-dot" style="visibility:hidden;"></span></div>'
                        + SLABS.map(function (s) {
                            return '<div class="mat-dil-row is-filterable' + (activeBand === s.key ? ' is-active' : '') + '" data-band="' + s.key + '">'
                                + '<span class="mat-dil-swatch" style="background:' + s.color + ';"></span>'
                                + '<span class="mat-dil-name">' + s.label + '</span>'
                                + '<span class="mat-dil-val">' + fmt(values[s.key]) + '</span>'
                                + '<span class="mat-dil-pct">' + pct[s.key] + '%</span>'
                                + '<span class="mat-dil-dot" style="background:' + s.color + ';"></span></div>';
                        }).join('')
                        + '<div class="mat-dil-row" style="border-top:1px dashed #cbd5e1;margin-top:2px;padding-top:3px;">'
                        + '<span class="mat-dil-swatch" style="visibility:hidden;"></span>'
                        + '<span class="mat-dil-name">Total</span>'
                        + '<span class="mat-dil-val">' + fmt(total) + '</span>'
                        + '<span class="mat-dil-pct">100%</span>'
                        + '<span class="mat-dil-dot" style="visibility:hidden;"></span></div>';

                    const canvas = document.getElementById('mat-dil-' + m.id + '-chart');
                    if (!canvas || typeof Chart === 'undefined') return;
                    if (charts[m.id]) charts[m.id].destroy();
                    charts[m.id] = new Chart(canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: SLABS.map(function (s) { return s.label; }),
                            datasets: [{
                                data: SLABS.map(function (s) { return values[s.key]; }),
                                backgroundColor: SLABS.map(function (s) { return s.color; }),
                                borderWidth: 0, borderRadius: 3, maxBarThickness: 28,
                                barPercentage: 0.85, categoryPercentage: 0.8,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            layout: { padding: { top: 2, right: 2 } },
                            scales: {
                                x: { grid: { display: false }, ticks: { font: { size: 8 }, maxRotation: 45, minRotation: 45, autoSkip: false } },
                                y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { font: { size: 8 }, maxTicksLimit: 4, callback: function (v) { return fmt(v); } } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: function (ctx) {
                                    const s = SLABS[ctx.dataIndex];
                                    return ' ' + fmt(Number(ctx.raw) || 0) + (m.money ? '' : ' SKU') + '  ·  ' + pct[s.key] + '% of total';
                                } } },
                            },
                            onClick: function (_e, els) { setBand(els && els.length ? SLABS[els[0].index].key : null); },
                        },
                    });
                });
            }

            const table = new Tabulator('#movement-analysis-table', {
                layout: 'fitColumns',
                height: '75vh',
                placeholder: 'Loading…',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                initialSort: [{ column: 'parent', dir: 'asc' }],
                columns: [
                    {
                        title: 'Image', field: 'image', width: 80, hozAlign: 'center', headerSort: false,
                        formatter: function (cell) {
                            const src = cell.getValue();
                            return src ? '<img class="mat-img" loading="lazy" src="' + esc(src) + '" alt="">' : '';
                        },
                    },
                    { title: 'Parent', field: 'parent', sorter: 'string', widthGrow: 2 },
                    { title: 'SKU', field: 'sku', sorter: 'string', widthGrow: 2 },
                    {
                        title: 'INV', field: 'INV', hozAlign: 'right', sorter: 'number', width: 100,
                        formatter: function (cell) { return Math.round(num(cell.getValue())).toLocaleString('en-US'); },
                    },
                    {
                        title: 'L30', field: 'L30', hozAlign: 'right', sorter: 'number', width: 100,
                        formatter: function (cell) { return Math.round(num(cell.getValue())).toLocaleString('en-US'); },
                    },
                    {
                        title: 'Dil %', field: 'dil', hozAlign: 'right', sorter: 'number', width: 110,
                        formatter: function (cell) {
                            const v = num(cell.getValue());
                            return '<span style="color:' + slabColor(v) + ';font-weight:700;">' + v.toFixed(2) + '%</span>';
                        },
                    },
                ],
            });

            // Table + badges both follow the toolbar; clicking a Dil band narrows the table only.
            function applyFilters() {
                table.setFilter(function (d) {
                    if (!passesToolbar(d)) return false;
                    if (activeBand && rowBand(d) !== activeBand) return false;
                    return true;
                });
                drawBadges();
                document.getElementById('mat-clear-band').style.display = activeBand ? '' : 'none';
                document.getElementById('mat-count').textContent = table.getDataCount('active') + ' of ' + allRows.length + ' SKUs';
            }
            function setBand(key) {
                activeBand = (key && activeBand === key) ? null : (key || null);
                applyFilters();
            }

            document.getElementById('mat-search').addEventListener('input', applyFilters);
            document.getElementById('mat-inv-filter').addEventListener('change', applyFilters);
            document.getElementById('mat-clear-band').addEventListener('click', function () { setBand(null); });
            document.querySelector('.mat-dil-pies').addEventListener('click', function (e) {
                const row = e.target.closest('.mat-dil-row.is-filterable');
                if (row) setBand(row.getAttribute('data-band'));
            });

            fetch('/movement-analysis-data-view', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            }).then(function (r) { return r.json(); }).then(function (rows) {
                allRows = (Array.isArray(rows) ? rows : []).filter(function (r) { return !isParent(r); });
                table.setData(allRows).then(applyFilters);
            }).catch(function () {
                table.alert('Failed to load data', 'error');
            });
        })();
    </script>
@endsection
