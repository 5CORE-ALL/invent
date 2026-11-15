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
        .sds-row { display: flex; align-items: center; gap: 6px; font-size: 12.1px; line-height: 1.3; padding: 2px 0; min-width: 1180px; }
        .sds-row.is-filterable { cursor: pointer; }
        .sds-row.is-filterable:hover { background: #eef2ff; border-radius: 4px; }
        .sds-row.is-active { background: #e0e7ff; border-radius: 4px; }
        .sds-swatch { width: 8px; height: 8px; border-radius: 2px; flex: 0 0 8px; }
        .sds-name { flex: 1 1 auto; min-width: 121px; font-weight: 600; color: #334155; }
        .sds-num { font-weight: 700; min-width: 92px; text-align: right; }
        .sds-cnt { min-width: 51px; text-align: right; color: #64748b; }
        .sds-pct { font-weight: 700; min-width: 64px; text-align: right; }
        .sds-dot { display: inline-block; width: 16px; height: 16px; flex: 0 0 16px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(15, 23, 42, .12); }
        .sds-head { color: #94a3b8; font-size: 11px; font-weight: 600; }
        .sds-total { border-top: 1px dashed #cbd5e1; margin-top: 2px; padding-top: 3px; }
        .sds-neg { color: #dc3545; }
        .sds-pos { color: #198754; }
        .sds-note { font-size: 11px; color: #64748b; }

        /* Inventory badges (same look as All Marketplace Master) */
        .sds-inv { background: #fef9c3; border: 1px solid #facc15; border-radius: 10px; padding: 8px 10px; margin: 0 0 14px; }
        .sds-inv-badges { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; margin-bottom: 8px; }
        .sds-inv-badges .sds-ib {
            height: 36px; display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            border-radius: 6px; font-weight: 700; font-size: 0.95rem; white-space: nowrap;
            background: #d1d5db; color: #111827;
        }
        .sds-ib-dot { width: 7px; height: 7px; border-radius: 50%; background: #fff; box-shadow: 0 0 0 1px rgba(15, 23, 42, .25); flex: 0 0 7px; }
        .sds-inv-panel {
            display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 10px; align-items: center;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px;
        }
        .sds-inv-chart { width: 220px; height: 120px; overflow: hidden; background: #fff; border-radius: 8px; }
        .sds-inv-sub { font-size: 0.72rem; font-weight: 800; color: #0f172a; margin: 0 0 0.3rem; }
        .sds-bands { display: grid; gap: 6px; min-width: 0; }
        .sds-band { position: relative; min-width: 0; border-radius: 8px; padding: 6px 8px; line-height: 1.25; }
        .sds-band-dot { position: absolute; top: 6px; right: 6px; width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(255, 255, 255, .9); }
        .sds-band-name { font-size: 11px; font-weight: 800; overflow-wrap: anywhere; padding-right: 14px; }
        .sds-band-cnt { font-size: 13px; font-weight: 700; margin-top: 2px; }
        .sds-band-val { font-size: 12px; font-weight: 700; display: flex; flex-wrap: wrap; column-gap: 4px; }
        @media (max-width: 991px) { .sds-inv-badges { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sds-inv-panel { grid-template-columns: 1fr; } }
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

                    <div class="sds-inv">
                        <div class="sds-inv-badges">
                            <span class="sds-ib" id="sds-ib-inventory" title="Shopify INV × Amazon live price"><span class="sds-ib-dot"></span>inv: <span class="v">0</span></span>
                            <span class="sds-ib" id="sds-ib-inv_at_sp" title="Shopify INV × Standard Price"><span class="sds-ib-dot"></span>Inv@SP: <span class="v">0</span></span>
                            <span class="sds-ib" id="sds-ib-inv_at_lp" title="Shopify INV × LP"><span class="sds-ib-dot"></span>Inv@LP: <span class="v">0</span></span>
                            <span class="sds-ib" id="sds-ib-tat" title="TAT = inv ÷ L30 Sales (months of stock)"><span class="sds-ib-dot"></span>TAT: <span class="v">0</span></span>
                            <span class="sds-ib" id="sds-ib-avail" title="Available % — child SKUs with INV > 0"><span class="sds-ib-dot"></span>Avail: <span class="v">0%</span></span>
                        </div>
                        <div class="sds-inv-panel">
                            <div class="sds-inv-chart"><canvas id="sds-inv-chart" width="220" height="120" style="width:220px;height:120px;display:block;"></canvas></div>
                            <div>
                                <div class="sds-inv-sub" title="Shopify INV × live Amazon price, by DIL band">Inv@Amz</div>
                                <div id="sds-inv-bands"><span class="text-muted small">Loading…</span></div>
                            </div>
                        </div>
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
            // Compact K format (chart axis / inventory badges only), e.g. $2,100K.
            function moneyK(n) {
                const v = Math.round(Number(n) || 0);
                if (Math.abs(v) >= 1000) return '$' + Math.round(v / 1000).toLocaleString('en-US') + 'K';
                return '$' + v.toLocaleString('en-US');
            }
            // Normal full figure, e.g. $2,100,345.
            function money(n) {
                return '$' + Math.round(Number(n) || 0).toLocaleString('en-US');
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
                        title: 'Projected', field: 'projected', hozAlign: 'right', sorter: 'number', width: 105,
                        headerTooltip: 'Projected 30-day sales = last 7 days / 7 x 30',
                        formatter: function (c) {
                            const d = c.getData();
                            const p = num(c.getValue()), l = num(d.l30_sales);
                            const color = l > 0 ? (p >= l ? '#198754' : '#dc3545') : '#334155';
                            return '<span style="color:' + color + ';font-weight:600;">' + money(p) + '</span>';
                        },
                    },
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
                BANDS.forEach(function (b) { acc[b.key] = { n: 0, sv: 0, today: 0, yest: 0, l30: 0, proj: 0, pw: 0, pv: 0, rw: 0, rv: 0, ypw: 0, ypv: 0, yrw: 0, yrv: 0 }; });
                list.forEach(function (r) {
                    const a = acc[bandOf(r)];
                    a.n += 1;
                    // Stock value at the live Amazon price = INV x Amz Prc.
                    // Negative INV (oversold) counts as no stock, so it cannot make a slab's value negative.
                    a.sv += Math.max(0, num(r.INV)) * num(r.amz_price);
                    a.today += num(r.today); a.yest += num(r.yesterday); a.l30 += num(r.l30_sales); a.proj += num(r.projected);
                    const w = num(r.l30_sales);
                    if (w > 0 && r.npft != null) { a.pw += w; a.pv += w * num(r.npft); }
                    if (w > 0 && r.nroi != null) { a.rw += w; a.rv += w * num(r.nroi); }
                    // Yesterday NPFT % / NROI %: same per-SKU %, weighted by that SKU's yesterday sales.
                    const yw = num(r.yesterday);
                    if (yw > 0 && r.npft != null) { a.ypw += yw; a.ypv += yw * num(r.npft); }
                    if (yw > 0 && r.nroi != null) { a.yrw += yw; a.yrv += yw * num(r.nroi); }
                });
                return acc;
            }
            function avg(v, w) { return w > 0 ? v / w : null; }

            function drawBadge() {
                const acc = aggregate(rows.filter(passesSearch));
                const tot = { n: 0, sv: 0, today: 0, yest: 0, l30: 0, proj: 0, pw: 0, pv: 0, rw: 0, rv: 0, ypw: 0, ypv: 0, yrw: 0, yrv: 0 };
                BANDS.forEach(function (b) { Object.keys(tot).forEach(function (k) { tot[k] += acc[b.key][k]; }); });
                // Dil % of a slab = sales in that slab / the slab's inventory value at the Amazon price.
                function dilPct(sales, sv) { return sv > 0 ? (sales / sv * 100).toFixed(2) + '%' : '—'; }
                function svPct(v) { return tot.sv > 0 ? (v / tot.sv * 100).toFixed(1) + '%' : '—'; }

                function rowHtml(b, a, filterable) {
                    return '<div class="sds-row' + (filterable ? ' is-filterable' : '') + (activeBand === b.key ? ' is-active' : '') + '" data-band="' + b.key + '">'
                        + '<span class="sds-swatch" style="background:' + b.color + ';"></span>'
                        + '<span class="sds-name">' + esc(b.label) + '</span>'
                        + '<span class="sds-num" title="INV x Amz Prc">' + money(a.sv) + '</span>'
                        + '<span class="sds-pct" title="Share of total stock value at Amz Prc">' + svPct(a.sv) + '</span>'
                        + '<span class="sds-cnt">' + a.n.toLocaleString('en-US') + '</span>'
                        + '<span class="sds-num">' + money(a.today) + '</span>'
                        + '<span class="sds-num">' + money(a.yest) + '</span>'
                        + '<span class="sds-pct" title="Yesterday sales / stock value at Amz Prc">' + dilPct(a.yest, a.sv) + '</span>'
                        + '<span class="sds-pct">' + pctHtml(avg(a.ypv, a.ypw)) + '</span>'
                        + '<span class="sds-pct">' + pctHtml(avg(a.yrv, a.yrw)) + '</span>'
                        + '<span class="sds-num">' + money(a.l30) + '</span>'
                        + '<span class="sds-num">' + money(a.proj) + '</span>'
                        + '<span class="sds-pct">' + pctHtml(avg(a.pv, a.pw)) + '</span>'
                        + '<span class="sds-pct">' + pctHtml(avg(a.rv, a.rw)) + '</span>'
                        + '<span class="sds-dot" style="background:' + b.color + ';"></span></div>';
                }

                let html = '<div class="sds-row sds-head">'
                    + '<span class="sds-swatch" style="visibility:hidden;"></span>'
                    + '<span class="sds-name">Dil slab</span>'
                    + '<span class="sds-num" title="Stock value = INV x Amz Prc">Stock $</span><span class="sds-pct" title="% of total stock value at Amz Prc">Stock %</span>'
                    + '<span class="sds-cnt">SKU</span><span class="sds-num">Today</span><span class="sds-num">Yesterday</span><span class="sds-pct" title="Dil % = Yesterday sales / stock value at Amz Prc (Stock $)">Y Dil %</span><span class="sds-pct">Y NPFT %</span><span class="sds-pct">Y NROI %</span><span class="sds-num">L30</span><span class="sds-num">Projected</span>'
                    + '<span class="sds-pct">NPFT %</span><span class="sds-pct">NROI %</span>'
                    + '<span class="sds-dot" style="visibility:hidden;"></span></div>';
                BANDS.forEach(function (b) { html += rowHtml(b, acc[b.key], true); });
                if (unmapped && (unmapped.l30 > 0 || unmapped.today > 0 || unmapped.yesterday > 0)) {
                    html += '<div class="sds-row" title="Orders whose SKU is not in Product Master (not counted in the total row)">'
                        + '<span class="sds-swatch" style="background:' + UNMAPPED.color + ';"></span>'
                        + '<span class="sds-name">' + UNMAPPED.label + '</span>'
                        + '<span class="sds-num">—</span><span class="sds-pct">—</span>'
                        + '<span class="sds-cnt">' + num(unmapped.skus).toLocaleString('en-US') + '</span>'
                        + '<span class="sds-num">' + money(unmapped.today) + '</span>'
                        + '<span class="sds-num">' + money(unmapped.yesterday) + '</span>'
                        + '<span class="sds-pct">—</span>'
                        + '<span class="sds-pct">—</span><span class="sds-pct">—</span>'
                        + '<span class="sds-num">' + money(unmapped.l30) + '</span>'
                        + '<span class="sds-num">' + money(unmapped.projected) + '</span>'
                        + '<span class="sds-pct">—</span><span class="sds-pct">—</span>'
                        + '<span class="sds-dot" style="background:' + UNMAPPED.color + ';"></span></div>';
                }
                html += '<div class="sds-row sds-total">'
                    + '<span class="sds-swatch" style="visibility:hidden;"></span>'
                    + '<span class="sds-name">Total</span>'
                    + '<span class="sds-num">' + money(tot.sv) + '</span>'
                    + '<span class="sds-pct">' + (tot.sv > 0 ? '100.0%' : '—') + '</span>'
                    + '<span class="sds-cnt">' + tot.n.toLocaleString('en-US') + '</span>'
                    + '<span class="sds-num">' + money(tot.today) + '</span>'
                    + '<span class="sds-num">' + money(tot.yest) + '</span>'
                    + '<span class="sds-pct" title="Yesterday sales / total stock value at Amz Prc">' + dilPct(tot.yest, tot.sv) + '</span>'
                    + '<span class="sds-pct" title="Weighted by yesterday sales">' + pctHtml(avg(tot.ypv, tot.ypw)) + '</span>'
                    + '<span class="sds-pct" title="Weighted by yesterday sales">' + pctHtml(avg(tot.yrv, tot.yrw)) + '</span>'
                    + '<span class="sds-num">' + money(tot.l30) + '</span>'
                    + '<span class="sds-num">' + money(tot.proj) + '</span>'
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
                            label: 'Y Dil %',
                            // Y Dil % = yesterday sales / slab stock value at Amz Prc.
                            data: BANDS.map(function (b) { const a = acc[b.key]; return a.sv > 0 ? a.yest / a.sv * 100 : 0; }),
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
                            y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { font: { size: 8 }, maxTicksLimit: 4, callback: function (v) { return Number(v).toFixed(1) + '%'; } } },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function (ctx) {
                                const b = BANDS[ctx.dataIndex], a = acc[b.key];
                                return [
                                    ' Y Dil ' + dilPct(a.yest, a.sv) + ' · ' + a.n + ' SKU',
                                    ' Yesterday ' + money(a.yest) + ' · Stock ' + money(a.sv),
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
                        + ' · Shopify B2B and B2C included (Shopify direct orders are not).';
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

            // ---- Inventory badges (inv / Inv@SP / Inv@LP / TAT / Avail + Inv@SP DIL bands) ----
            let invChart = null;
            const SHORT = { zero: '0 Sold', red1: 'Red <10', red2: 'Red 10-25', green: 'Green', pink: 'Pink' };
            const TONES = {
                zero: 'background:#e5e7eb;color:#374151;',
                red1: 'background:#fecaca;color:#7f1d1d;',
                red2: 'background:#fee2e2;color:#991b1b;',
                green: 'background:#dcfce7;color:#14532d;',
                pink: 'background:#fce7f3;color:#9d174d;',
            };
            const BADGE_STYLE = {
                up: ['#22c55e', '#052e16'],
                down: ['#ef4444', '#ffffff'],
                flat: ['#facc15', '#713f12'],
                none: ['#d1d5db', '#111827'],
            };
            function pairClass(pair, lowerIsBetter) {
                if (!pair || pair[0] == null || pair[1] == null) return 'none';
                const a = Number(pair[0]), b = Number(pair[1]);
                if (!isFinite(a) || !isFinite(b)) return 'none';
                if (Math.abs(b - a) <= 0.01) return 'flat';
                return (lowerIsBetter ? b < a : b > a) ? 'up' : 'down';
            }
            function setBadge(id, text, cls) {
                const el = document.getElementById(id);
                if (!el) return;
                el.querySelector('.v').textContent = text;
                const st = BADGE_STYLE[cls] || BADGE_STYLE.none;
                el.style.backgroundColor = st[0];
                el.style.color = st[1];
            }
            function bandDotColor(history, band, field) {
                if (!history || history.length < 2) return '#6c757d';
                const prev = Number(history[history.length - 2][field]);
                const cur = Number(history[history.length - 1][field]);
                if (!isFinite(prev) || !isFinite(cur) || Math.abs(cur - prev) < 0.5) return '#6c757d';
                const lowerIsBetter = band === 'zero' || band === 'red1' || band === 'red2';
                return (lowerIsBetter ? cur < prev : cur > prev) ? '#28a745' : '#dc3545';
            }
            function drawInvBadges(res) {
                const dots = res.dots || {};
                setBadge('sds-ib-inventory', moneyK(res.inv).replace('$', ''), pairClass(dots.inventory, false));
                setBadge('sds-ib-inv_at_sp', moneyK(res.inv_at_sp).replace('$', ''), pairClass(dots.inv_at_sp, false));
                setBadge('sds-ib-inv_at_lp', moneyK(res.inv_at_lp).replace('$', ''), pairClass(dots.inv_at_lp, false));
                const tat = num(res.tat);
                setBadge('sds-ib-tat', tat > 0 ? tat.toFixed(2) : '0', pairClass(dots.tat, true));
                setBadge('sds-ib-avail', Math.round(num(res.avail_pct)) + '%', pairClass(dots.avail, false));

                const slices = res.slices || [];
                const hist = res.history || [];
                // Band values are Shopify INV x the live Amazon price (Inv@Amz).
                const total = slices.reduce(function (s, x) { return s + num(x.inv_at_amz); }, 0);
                document.getElementById('sds-inv-bands').innerHTML =
                    '<div class="sds-bands" style="grid-template-columns:repeat(' + Math.max(1, slices.length) + ',minmax(0,1fr));">'
                    + slices.map(function (s) {
                        const n = num(s.inv_at_amz);
                        const pct = total > 0 ? Math.round(n / total * 100) : 0;
                        return '<div class="sds-band" style="' + (TONES[s.key] || TONES.red1) + '">'
                            + '<span class="sds-band-dot" style="background:' + bandDotColor(hist, s.key, s.key + '_sp') + ';"></span>'
                            + '<div class="sds-band-name">' + esc(s.label) + '</div>'
                            + '<div class="sds-band-cnt">' + num(s.count) + '</div>'
                            + '<div class="sds-band-val"><span style="white-space:nowrap;">' + moneyK(n) + '</span>'
                            + '<span style="white-space:nowrap;opacity:.8;">' + pct + '%</span></div></div>';
                    }).join('') + '</div>';

                const canvas = document.getElementById('sds-inv-chart');
                if (!canvas || typeof Chart === 'undefined' || !slices.length) return;
                if (invChart) invChart.destroy();
                invChart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: slices.map(function (s) { return SHORT[s.key] || s.label; }),
                        datasets: [{
                            data: slices.map(function (s) { return num(s.inv_at_amz); }),
                            backgroundColor: slices.map(function (s) { return s.color; }),
                            borderWidth: 0, borderRadius: 3, maxBarThickness: 36, barPercentage: 0.8, categoryPercentage: 0.7,
                        }],
                    },
                    options: {
                        responsive: false, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function (ctx) {
                                const s = slices[ctx.dataIndex] || {};
                                const n = num(ctx.raw);
                                return ' ' + num(s.count) + ' SKUs  ·  ' + moneyK(n) + '  ·  ' + (total > 0 ? Math.round(n / total * 100) : 0) + '%';
                            } } },
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#334155' } },
                            y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { font: { size: 9 }, color: '#64748b', maxTicksLimit: 4, callback: function (v) { return moneyK(v); } } },
                        },
                    },
                });
            }
            fetch('/sales-by-dil-slab-badges', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(drawInvBadges)
                .catch(function () {
                    document.getElementById('sds-inv-bands').innerHTML = '<span class="text-danger small">Failed to load inventory badges.</span>';
                });
        })();
    </script>
@endsection
