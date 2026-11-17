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
        .sds-row { display: flex; align-items: center; gap: 6px; font-size: 12.1px; line-height: 1.3; padding: 2px 0; min-width: 1280px; }
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
        .sds-band-dot { position: absolute; top: 6px; right: 6px; width: 12px; height: 12px; border-radius: 50%; border: none; padding: 0; cursor: pointer; box-shadow: 0 0 0 1px rgba(255, 255, 255, .9); }
        .sds-band-dot:hover { transform: scale(1.3); }
        .sds-inv-hist-wrap { display: none; margin-top: 8px; padding: 6px 8px 4px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
        .sds-inv-hist-wrap.is-open { display: block; }
        .sds-inv-hist-canvas-wrap { height: 200px; }
        .sds-band-name { font-size: 11px; font-weight: 800; overflow-wrap: anywhere; padding-right: 14px; }
        .sds-band-cnt { font-size: 13px; font-weight: 700; margin-top: 2px; }
        .sds-band-val { font-size: 12px; font-weight: 700; display: flex; flex-wrap: wrap; column-gap: 4px; }
        /* Master Dil slab rule modal (same Dil slabs - Target NROI table as the Amazon Sprc Dil modal) */
        #sdsMasterModal .sds-m-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; background: #fff; }
        #sdsMasterModal .sds-m-title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-weight: 700; margin-bottom: 8px; }
        #sdsMasterModal .sds-m-seq { display: inline-flex; align-items: center; gap: 4px; background: #eef2ff; color: #4338ca; border-radius: 4px; padding: 1px 8px; font-size: 11px; font-weight: 800; }
        #sdsMasterModal table .form-control-sm { padding-top: 1px; padding-bottom: 1px; min-height: 24px; height: 26px; font-size: 12px; max-width: 110px; display: inline-block; text-align: right; font-weight: 600; }
        #sdsMasterModal .sds-m-count { font-weight: 700; text-align: center; white-space: nowrap; }
        /* History dot (same as the Amazon Sprc Dil modal): click opens the 30-day line at the top of the modal. */
        #sdsMasterModal .sds-m-hist-dot { display: inline-block; width: 8px; height: 8px; flex: 0 0 8px; border-radius: 50%; border: none; padding: 0; margin-left: 6px; vertical-align: middle; cursor: pointer; box-shadow: 0 0 0 1px rgba(15, 23, 42, .12); }
        #sdsMasterModal .sds-m-hist-dot:hover { transform: scale(1.35); }
        #sdsMasterModal .sds-m-hist-wrap { display: none; margin: 0 0 10px; padding: 6px 8px 4px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
        #sdsMasterModal .sds-m-hist-wrap.is-open { display: block; }
        #sdsMasterModal .sds-m-hist-canvas-wrap { height: 220px; }
        #sdsMasterModal .sds-m-val { font-weight: 700; text-align: center; white-space: nowrap; }
        #sdsMasterModal .sds-m-del { border: none; background: none; color: #dc3545; padding: 0 4px; line-height: 1; cursor: pointer; font-size: 16px; }
        #sdsMasterModal .sds-m-rules { margin: 10px 0 0; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; font-size: 12px; }
        #sdsMasterModal .sds-m-rules ol { margin: 4px 0 0; padding-left: 20px; }
        #sdsMasterModal .sds-m-rules li { margin-bottom: 3px; }
        #sdsMasterModal .sds-m-status { font-size: 12px; min-height: 18px; }
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
                        <select id="sds-slab" class="form-select form-select-sm" style="width:auto;min-width:170px;" title="Filter by Dil slab">
                            <option value="">All slabs</option>
                        </select>
                        <button type="button" id="sds-clear" class="btn btn-sm btn-outline-secondary" style="display:none;">Clear slab filter</button>
                        <button type="button" id="sds-master-btn" class="btn btn-sm btn-primary" title="Master Dil slab rule: the Dil slabs - Target NROI table (Seq 1). Any change applies automatically to all marketplaces.">
                            <i class="fas fa-layer-group"></i> Master Dil Slab Rule
                        </button>
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
                        {{-- History graph: opens when a band dot is clicked. --}}
                        <div class="sds-inv-hist-wrap" id="sds-inv-hist-wrap">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold" id="sds-inv-hist-title">Inv@Amz history</span>
                                <button type="button" class="btn-close" id="sds-inv-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                            </div>
                            <div class="sds-inv-hist-canvas-wrap"><canvas id="sds-inv-hist"></canvas></div>
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

    <div class="modal fade" id="sdsMasterModal" tabindex="-1" aria-labelledby="sdsMasterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="sdsMasterModalLabel"><i class="fas fa-layer-group me-1"></i> Master Dil Slab Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- History graph (Count / Inv Val@LP / Inv@Amz Prc dots): opens here, at the top of the modal. --}}
                    <div class="sds-m-hist-wrap" id="sds-m-hist-wrap">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" id="sds-m-hist-title">Slab history</span>
                            <button type="button" class="btn-close" id="sds-m-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                        </div>
                        <div class="sds-m-hist-canvas-wrap"><canvas id="sds-m-hist"></canvas></div>
                    </div>
                    <div class="small text-muted mb-2">
                        This is the <strong>Seq 1</strong> rule (Dil slabs &mdash; Target NROI) of the Amazon Sprc Dil modal. Edit the slabs, then click
                        <strong>Save &amp; Apply</strong> to apply them to <strong>all marketplaces</strong>. Only the slabs change: each page keeps its own CVR, Ageing, LMP cap,
                        Clearance and other rules.
                    </div>
                    <div class="sds-m-card">
                        <div class="sds-m-title">
                            <span class="sds-m-seq">SEQ 1</span>
                            <span><i class="fas fa-layer-group me-1"></i> Dil slabs &mdash; Target NROI</span>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 ms-auto" id="sds-m-add"><i class="fas fa-plus me-1"></i> Add slab</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" id="sds-m-table">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:110px;">From</th>
                                        <th class="text-center" style="width:110px;">To</th>
                                        <th class="text-center" style="width:90px;" title="Child SKUs with INV &gt; 0 whose Dil is in this slab">Count</th>
                                        <th class="text-center" style="width:120px;" title="Sum of INV x LP for the SKUs in this slab">Inv Val@LP</th>
                                        <th class="text-center" style="width:120px;" title="Sum of INV x live Amazon price for the SKUs in this slab">Inv@Amz Prc</th>
                                        <th class="text-end" style="width:140px;">Target NROI%</th>
                                        <th style="width:36px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="sds-m-tbody"><tr><td colspan="7" class="text-muted small text-center">Loading…</td></tr></tbody>
                            </table>
                        </div>
                        <div class="sds-m-rules">
                            <strong><i class="fas fa-list-ul me-1"></i> Rules</strong>
                            <ol>
                                <li>When Dil sits in a From&ndash;To range (INV &gt; 0): use that slab&rsquo;s Target NROI (first match; last slab includes the To value). S PRC is set so SNROI = Target NROI using Channel Master Ads%: (LP &times; (1 + NROI%/100) + Ship) / (0.80 &minus; Ads%/100).</li>
                                <li>When a price is calculated from a Dil slab match: it auto-applies to S PRC and Push Prc Sale.</li>
                                <li>When you change the first Target NROI%: later rows fill as first +5, +10, &hellip; (increasing down the table).</li>
                                <li>When INV &le; 0: Count and pies skip that SKU.</li>
                                <li>Changes are saved to every marketplace only when you click Save &amp; Apply (this Seq 1 rule only).</li>
                            </ol>
                        </div>
                    </div>
                    <div class="sds-m-status mt-2 text-muted" id="sds-m-status"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-success" id="sds-m-save" disabled>
                        <i class="fas fa-save me-1"></i> Save &amp; Apply to all marketplaces
                    </button>
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
                layout: 'fitData',
                height: '60vh',
                placeholder: 'Loading…',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 250, 500],
                columnDefaults: { headerHozAlign: 'center' },
                initialSort: [{ column: 'l30_sales', dir: 'desc' }],
                columns: [
                    {
                        title: 'Image', field: 'image', hozAlign: 'center', headerSort: false,
                        formatter: function (cell) {
                            const src = cell.getValue();
                            return src ? '<img class="sds-img" loading="lazy" src="' + esc(src) + '" alt="">' : '';
                        },
                    },
                    { title: 'Parent', field: 'parent', sorter: 'string', hozAlign: 'center' },
                    { title: 'SKU', field: 'sku', sorter: 'string', hozAlign: 'center' },
                    { title: 'INV', field: 'INV', hozAlign: 'center', sorter: 'number', formatter: function (c) { return Math.round(num(c.getValue())).toLocaleString('en-US'); } },
                    { title: 'L30 Qty', field: 'L30', hozAlign: 'center', sorter: 'number', headerTooltip: 'Units sold in the last 30 days', formatter: function (c) { return Math.round(num(c.getValue())).toLocaleString('en-US'); } },
                    {
                        title: 'Dil %', field: 'dil', hozAlign: 'center', sorter: 'number',
                        formatter: function (cell) {
                            const d = cell.getData();
                            return '<span style="color:' + bandColor(bandOf(d)) + ';font-weight:700;">' + num(cell.getValue()).toFixed(2) + '%</span>';
                        },
                    },
                    { title: 'TD', field: 'today', headerTooltip: 'Today', hozAlign: 'center', sorter: 'number', formatter: function (c) { return money(c.getValue()); } },
                    { title: 'Y', field: 'yesterday', headerTooltip: 'Yesterday', hozAlign: 'center', sorter: 'number', formatter: function (c) { return money(c.getValue()); } },
                    { title: 'L30', field: 'l30_sales', hozAlign: 'center', sorter: 'number', formatter: function (c) { return money(c.getValue()); } },
                    {
                        title: 'PJT', field: 'projected', hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Projected 30-day sales = last 7 days / 7 x 30',
                        formatter: function (c) {
                            const d = c.getData();
                            const p = num(c.getValue()), l = num(d.l30_sales);
                            const color = l > 0 ? (p >= l ? '#198754' : '#dc3545') : '#334155';
                            return '<span style="color:' + color + ';font-weight:600;">' + money(p) + '</span>';
                        },
                    },
                    {
                        title: 'Std Prc', field: 'std_price', hozAlign: 'center', sorter: 'number',
                        formatter: function (c) { return c.getValue() == null ? '' : unitMoney(c.getValue()); },
                    },
                    {
                        title: 'NPFT $', field: 'npft_amt', hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Per unit: Std Prc x NPFT %',
                        formatter: function (c) { return c.getValue() == null ? '' : signedMoney(c.getValue()); },
                    },
                    { title: 'NPFT %', field: 'npft', hozAlign: 'center', sorter: 'number', formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); } },
                    {
                        title: 'NROI $', field: 'nroi_amt', hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Per unit: LP x NROI %',
                        formatter: function (c) { return c.getValue() == null ? '' : signedMoney(c.getValue()); },
                    },
                    { title: 'NROI %', field: 'nroi', hozAlign: 'center', sorter: 'number', formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); } },
                    {
                        title: 'Amz Prc', field: 'amz_price', hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Live Amazon price',
                        formatter: function (c) { return c.getValue() == null ? '' : unitMoney(c.getValue()); },
                    },
                    {
                        title: 'Amz PFT $', field: 'amz_pft', hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Per unit: Amz Prc x 0.80 - LP - Ship - Amz Prc x Amazon Ads %',
                        formatter: function (c) { return c.getValue() == null ? '' : signedMoney(c.getValue()); },
                    },
                    {
                        title: 'Amz PFT %', field: 'amz_pft_pct', hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Amz PFT $ / Amz Prc',
                        formatter: function (c) { return c.getValue() == null ? '' : pctHtml(num(c.getValue())); },
                    },
                    {
                        title: 'Amz ROI %', field: 'amz_roi_pct', hozAlign: 'center', sorter: 'number',
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
                BANDS.forEach(function (b) { acc[b.key] = { n: 0, sv: 0, slp: 0, today: 0, yest: 0, l30: 0, proj: 0, pw: 0, pv: 0, rw: 0, rv: 0, ypw: 0, ypv: 0, yrw: 0, yrv: 0 }; });
                list.forEach(function (r) {
                    const a = acc[bandOf(r)];
                    a.n += 1;
                    // Stock value at the live Amazon price = INV x Amz Prc.
                    // Negative INV (oversold) counts as no stock, so it cannot make a slab's value negative.
                    a.sv += Math.max(0, num(r.INV)) * num(r.amz_price);
                    // Stock value at landed price (LP) = INV x LP.
                    a.slp += Math.max(0, num(r.INV)) * num(r.lp);
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
                const tot = { n: 0, sv: 0, slp: 0, today: 0, yest: 0, l30: 0, proj: 0, pw: 0, pv: 0, rw: 0, rv: 0, ypw: 0, ypv: 0, yrw: 0, yrv: 0 };
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
                        + '<span class="sds-num" title="INV x LP">' + money(a.slp) + '</span>'
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
                    + '<span class="sds-num" title="Stock value at LP = INV x LP">Stock LP $</span>'
                    + '<span class="sds-cnt">SKU</span><span class="sds-num">Today</span><span class="sds-num">Yesterday</span><span class="sds-pct" title="Dil % = Yesterday sales / stock value at Amz Prc (Stock $)">Y Dil %</span><span class="sds-pct">Y NPFT %</span><span class="sds-pct">Y NROI %</span><span class="sds-num">L30</span><span class="sds-num">Projected</span>'
                    + '<span class="sds-pct">NPFT %</span><span class="sds-pct">NROI %</span>'
                    + '<span class="sds-dot" style="visibility:hidden;"></span></div>';
                BANDS.forEach(function (b) { html += rowHtml(b, acc[b.key], true); });
                if (unmapped && (unmapped.l30 > 0 || unmapped.today > 0 || unmapped.yesterday > 0)) {
                    html += '<div class="sds-row" title="Orders whose SKU is not in Product Master (not counted in the total row)">'
                        + '<span class="sds-swatch" style="background:' + UNMAPPED.color + ';"></span>'
                        + '<span class="sds-name">' + UNMAPPED.label + '</span>'
                        + '<span class="sds-num">—</span><span class="sds-pct">—</span><span class="sds-num">—</span>'
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
                    + '<span class="sds-num">' + money(tot.slp) + '</span>'
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
                document.getElementById('sds-slab').value = activeBand || '';
                drawBadge();
            }
            function setBand(key) {
                activeBand = (key && activeBand === key) ? null : (key || null);
                applyFilters();
            }

            // Slab dropdown (kept in sync with the legend / chart click filter).
            (function initSlabSelect() {
                const sel = document.getElementById('sds-slab');
                BANDS.forEach(function (b) {
                    const o = document.createElement('option');
                    o.value = b.key;
                    o.textContent = b.label;
                    sel.appendChild(o);
                });
                sel.addEventListener('change', function () {
                    activeBand = sel.value || null;
                    applyFilters();
                });
            })();

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
                    if (typeof window.sdsSnapshotHistory === 'function') window.sdsSnapshotHistory();
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
            // True once the last two saved days both have an Inv@Amz value for the band (older days only have Inv@SP / Inv@LP).
            function ampHas(history, band) {
                if (!history || history.length < 2) return false;
                const f = band + '_amz';
                const a = history[history.length - 2][f], b = history[history.length - 1][f];
                return a != null && b != null && isFinite(Number(a)) && isFinite(Number(b));
            }
            let invHistChart = null;
            let invLiveSlices = [];
            let invLiveHistory = [];
            function drawInvHist(band) {
                const spec = invLiveSlices.filter(function (s) { return s.key === band; })[0];
                if (!spec) return;
                const field = band + '_amz';
                const color = spec.color || '#6f42c1';
                // Last 30 days; a day with no saved Inv@Amz is a gap, not a made-up 0. Today is always the live number.
                const byDate = {};
                invLiveHistory.forEach(function (r) { if (r && r.date) byDate[r.date] = r; });
                let today;
                try { today = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
                catch (e) { today = new Date().toISOString().slice(0, 10); }
                const p = today.split('-').map(Number);
                const end = new Date(Date.UTC(p[0], (p[1] || 1) - 1, p[2] || 1));
                const labels = [], values = [];
                for (let i = 29; i >= 0; i--) {
                    const d = new Date(end);
                    d.setUTCDate(d.getUTCDate() - i);
                    const key = d.toISOString().slice(0, 10);
                    labels.push(key.slice(5));
                    const r = byDate[key];
                    if (key === today) values.push(num(spec.inv_at_amz));
                    else values.push(r && r[field] != null ? Number(r[field]) : null);
                }
                document.getElementById('sds-inv-hist-title').textContent = 'Inv@Amz ' + spec.label + ' · last 30 days';
                document.getElementById('sds-inv-hist-wrap').classList.add('is-open');
                const canvas = document.getElementById('sds-inv-hist');
                if (!canvas || typeof Chart === 'undefined') return;
                if (invHistChart) { invHistChart.destroy(); invHistChart = null; }
                invHistChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values, borderColor: color, backgroundColor: color + '22', fill: true, tension: 0.3,
                            borderWidth: 1.5, pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: color, pointBorderColor: color, spanGaps: false,
                        }],
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, clip: false,
                        layout: { padding: { top: 16, right: 8, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function (ctx) { return ' ' + money(ctx.raw); } } },
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { font: { size: 9 }, callback: function (v) { return moneyK(v); } } },
                            x: { offset: true, ticks: { maxRotation: 90, minRotation: 90, autoSkip: false, font: { size: 8, weight: '600' } } },
                        },
                    },
                    plugins: [{
                        id: 'sdsInvHistLabels',
                        afterDraw: function (chart) {
                            const ds = chart.data.datasets[0], meta = chart.getDatasetMeta(0), c = chart.ctx;
                            meta.data.forEach(function (pt, i) {
                                const v = ds.data[i];
                                if (v == null || !pt) return;
                                c.save();
                                c.font = 'bold 10px Inter, system-ui, sans-serif';
                                c.fillStyle = '#111'; c.strokeStyle = 'rgba(255,255,255,0.95)'; c.lineWidth = 3; c.lineJoin = 'round';
                                c.textAlign = 'center'; c.textBaseline = 'bottom';
                                c.strokeText(moneyK(v), pt.x, pt.y - 5); c.fillText(moneyK(v), pt.x, pt.y - 5);
                                c.restore();
                            });
                        },
                    }],
                });
            }
            document.getElementById('sds-inv-bands').addEventListener('click', function (e) {
                const dot = e.target.closest('.sds-band-dot');
                if (dot) drawInvHist(dot.getAttribute('data-band'));
            });
            document.getElementById('sds-inv-hist-close').addEventListener('click', function () {
                document.getElementById('sds-inv-hist-wrap').classList.remove('is-open');
            });
            function drawInvBadges(res) {
                invLiveSlices = res.slices || [];
                invLiveHistory = res.history || [];
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
                            + '<button type="button" class="sds-band-dot" data-band="' + esc(s.key) + '" title="' + esc(s.label) + ' · Inv@Amz daily history" style="background:' + bandDotColor(hist, s.key, ampHas(hist, s.key) ? s.key + '_amz' : s.key + '_sp') + ';"></button>'
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

            // ---- Master Dil slab rule: the Seq 1 table of the Amazon Sprc Dil modal; every change is saved to all marketplaces ----
            (function masterDilSlab() {
                const MASTER_SAVE_URL = @json(route('sales.by.dil.slab.master.rule'));
                const MASTER_LOAD_URL = '/channel-promo-pricing/amazon/dil-groi';
                const COLORS = ['#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c', '#7c3aed', '#0ea5e9'];
                const DEFAULTS = [
                    { min: 0, max: 0, nroi: 50 }, { min: 0.1, max: 5, nroi: 50 }, { min: 5, max: 10, nroi: 55 },
                    { min: 10, max: 15, nroi: 60 }, { min: 15, max: 20, nroi: 65 }, { min: 20, max: 25, nroi: 70 },
                ];
                let slabs = [];
                let loaded = false;
                let saveTimer = null;
                let saving = false;
                let dirtyWhileSaving = false;
                const csrf = function () { const m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; };
                const r2 = function (n) { return Math.round(n * 100) / 100; };

                function norm(r) {
                    let min = parseFloat(r.min), max = parseFloat(r.max);
                    const t = parseFloat(r.nroi != null ? r.nroi : r.groi);
                    if (!isFinite(min) || !isFinite(max)) return null;
                    min = Math.max(0, min); max = Math.max(0, max);
                    if (max < min) { const x = min; min = max; max = x; }
                    return { min: r2(min), max: r2(max), nroi: r2(Math.max(0, isFinite(t) ? t : 0)) };
                }
                function normList(list) {
                    const out = [];
                    (Array.isArray(list) ? list : []).forEach(function (r) { const n = norm(r); if (n) out.push(n); });
                    out.sort(function (a, b) { return a.min - b.min || a.max - b.max; });
                    return out;
                }
                /** Same slab test as the server: min <= Dil < max, the last slab also includes max, min = max matches that Dil only. */
                function slabIndex(dil, list) {
                    const n = r2(num(dil));
                    const last = list.length - 1;
                    for (let i = 0; i < list.length; i++) {
                        const r = list[i];
                        const hit = r.max === r.min ? n === r.min : (n >= r.min && (i === last ? n <= r.max : n < r.max));
                        if (hit) return i;
                    }
                    return -1;
                }
                /** Per slab: SKU count (INV > 0), Inv Val@LP (INV x LP) and Inv@Amz Prc (INV x live Amazon price). */
                function slabStats(list) {
                    const s = list.map(function () { return { n: 0, lp: 0, amz: 0 }; });
                    rows.forEach(function (d) {
                        const inv = num(d.INV);
                        if (inv <= 0) return;
                        const i = slabIndex(d.dil, list);
                        if (i < 0) return;
                        s[i].n++;
                        s[i].lp += inv * num(d.lp);
                        s[i].amz += inv * num(d.amz_price);
                    });
                    return s;
                }
                function slabKeyOf(r) { return r.min + '-' + r.max; }
                function histDot(metric, i) {
                    const label = metric === 'count' ? 'Count' : (metric === 'lp' ? 'Inv Val@LP' : 'Inv@Amz Prc');
                    return '<button type="button" class="sds-m-hist-dot" data-metric="' + metric + '" data-idx="' + i + '" '
                        + 'style="background:' + COLORS[i % COLORS.length] + ';" title="' + label + ' daily history"></button>';
                }
                function render() {
                    const tb = document.getElementById('sds-m-tbody');
                    const c = slabStats(slabs);
                    const canDelete = slabs.length > 1;
                    tb.innerHTML = slabs.map(function (r, i) {
                        const st = c[i] || { n: 0, lp: 0, amz: 0 };
                        return '<tr data-idx="' + i + '">'
                            + '<td class="text-center"><input type="number" min="0" step="0.1" class="form-control form-control-sm sds-m-min" value="' + r.min + '"></td>'
                            + '<td class="text-center"><input type="number" min="0" step="0.1" class="form-control form-control-sm sds-m-max" value="' + r.max + '"></td>'
                            + '<td class="sds-m-count"><span class="sds-m-n">' + st.n + '</span>' + histDot('count', i) + '</td>'
                            + '<td class="sds-m-val"><span>' + money(st.lp) + '</span>' + histDot('lp', i) + '</td>'
                            + '<td class="sds-m-val"><span>' + money(st.amz) + '</span>' + histDot('amz', i) + '</td>'
                            + '<td class="text-end"><input type="number" min="0" step="0.1" class="form-control form-control-sm sds-m-nroi" value="' + r.nroi + '"'
                            + (i === 0 ? ' title="Changing this sets the following slabs to +5 each"' : '') + '></td>'
                            + '<td class="text-center">' + (canDelete ? '<button type="button" class="sds-m-del" data-idx="' + i + '" title="Remove slab">&times;</button>' : '') + '</td>'
                            + '</tr>';
                    }).join('');
                }
                function readTable() {
                    const list = [];
                    document.querySelectorAll('#sds-m-tbody tr').forEach(function (tr) {
                        const n = norm({
                            min: tr.querySelector('.sds-m-min').value,
                            max: tr.querySelector('.sds-m-max').value,
                            nroi: tr.querySelector('.sds-m-nroi').value,
                        });
                        if (n) list.push(n);
                    });
                    slabs = list.length ? normList(list) : DEFAULTS.map(function (r) { return Object.assign({}, r); });
                }
                function setStatus(html, cls) {
                    const el = document.getElementById('sds-m-status');
                    el.className = 'sds-m-status mt-2 ' + (cls || 'text-muted');
                    el.innerHTML = html;
                }
                function setSaveBtn(disabled, busy) {
                    const b = document.getElementById('sds-m-save');
                    b.disabled = disabled;
                    b.innerHTML = busy
                        ? '<i class="fas fa-spinner fa-spin me-1"></i> Applying…'
                        : '<i class="fas fa-save me-1"></i> Save &amp; Apply to all marketplaces';
                }
                // Edits are only marked as unsaved; nothing is sent until Save & Apply is clicked.
                function scheduleSave() {
                    if (!loaded) return;
                    setStatus('<i class="fas fa-pen"></i> Unsaved changes. Click Save &amp; Apply to apply them to all marketplaces.', 'text-warning');
                    setSaveBtn(false, false);
                }
                function save() {
                    if (saving || !loaded) return;
                    readTable();
                    render();
                    saving = true;
                    setSaveBtn(true, true);
                    setStatus('Applying to all marketplaces…');
                    const payload = slabs.map(function (r) { return { min: r.min, max: r.max, groi: r.nroi, nroi: r.nroi }; });
                    fetch(MASTER_SAVE_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify({ rules: payload, _token: csrf() }),
                    }).then(function (r) {
                        return r.json().then(function (j) { if (!r.ok || !j.success) throw new Error(j.message || ('HTTP ' + r.status)); return j; });
                    }).then(function (res) {
                        const n = (res.channels || []).length;
                        setStatus('<i class="fas fa-check-circle text-success"></i> Applied to all marketplaces ('
                            + n + ' pages' + ((res.updated || []).length ? ', ' + res.updated.length + ' changed' : ', already the same') + ').', 'text-success');
                        setSaveBtn(true, false);
                    }).catch(function (e) {
                        setStatus('<i class="fas fa-exclamation-triangle"></i> Could not apply: ' + esc(e.message), 'text-danger');
                        setSaveBtn(false, false);
                    }).finally(function () {
                        saving = false;
                    });
                }
                document.getElementById('sds-m-save').addEventListener('click', save);
                function load() {
                    loaded = false;
                    setSaveBtn(true, false);
                    setStatus('Loading the saved slabs…');
                    fetch(MASTER_LOAD_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(function (res) {
                            const list = normList((res && Array.isArray(res.rules)) ? res.rules : []);
                            slabs = list.length ? list : DEFAULTS.map(function (r) { return Object.assign({}, r); });
                            render();
                            loaded = true;
                            setStatus('Loaded the Dil slabs. Edit them, then click Save & Apply to update all marketplaces.');
                        })
                        .catch(function (e) {
                            slabs = DEFAULTS.map(function (r) { return Object.assign({}, r); });
                            render();
                            setStatus('<i class="fas fa-exclamation-triangle"></i> Could not load the saved slabs (' + esc(e.message) + '). Editing is disabled until it loads.', 'text-danger');
                        });
                }

                document.getElementById('sds-master-btn').addEventListener('click', function () {
                    const el = document.getElementById('sdsMasterModal');
                    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
                    bootstrap.Modal.getOrCreateInstance(el).show();
                    load();
                });
                const tbody = document.getElementById('sds-m-tbody');
                tbody.addEventListener('change', function (e) {
                    if (!loaded || !e.target.matches('input')) return;
                    const first = tbody.querySelector('tr .sds-m-nroi');
                    if (e.target === first && e.target.classList.contains('sds-m-nroi')) {
                        // First Target NROI changed: the following slabs fill as first +5, +10, ... (same as the Amazon modal).
                        const v = parseFloat(first.value);
                        if (isFinite(v)) {
                            tbody.querySelectorAll('tr .sds-m-nroi').forEach(function (inp, i) { if (i > 0) inp.value = r2(Math.max(0, v + i * 5)); });
                        }
                    }
                    readTable();
                    render();
                    scheduleSave();
                });
                tbody.addEventListener('click', function (e) {
                    const del = e.target.closest('.sds-m-del');
                    if (!del || !loaded) return;
                    readTable();
                    if (slabs.length <= 1) return;
                    slabs.splice(parseInt(del.getAttribute('data-idx'), 10), 1);
                    render();
                    scheduleSave();
                });
                document.getElementById('sds-m-add').addEventListener('click', function () {
                    if (!loaded) return;
                    readTable();
                    let nextMin = 0.1, lastNroi = 50;
                    slabs.forEach(function (r) { if (r.max > nextMin) nextMin = r.max; lastNroi = r.nroi; });
                    slabs.push({ min: r2(nextMin), max: r2(nextMin + 5), nroi: r2(Math.max(0, lastNroi + 5)) });
                    slabs = normList(slabs);
                    render();
                    scheduleSave();
                });
                // ---- History graph (same as the Amazon Sprc Dil modal): dot -> 30-day line at the top of the modal ----
                const HIST_URL = @json(route('sales.by.dil.slab.history'));
                const HIST_SAVE_URL = @json(route('sales.by.dil.slab.history.save'));
                const METRIC_LABEL = { count: 'Count', lp: 'Inv Val@LP', amz: 'Inv@Amz Prc' };
                let histChart = null;

                function todayPT() {
                    try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
                    catch (e) { return new Date().toISOString().slice(0, 10); }
                }
                function padDays(list, days) {
                    const byDate = {};
                    (list || []).forEach(function (r) { if (r && r.date) byDate[r.date] = r; });
                    const p = todayPT().split('-').map(Number);
                    const end = new Date(Date.UTC(p[0], (p[1] || 1) - 1, p[2] || 1));
                    const out = [];
                    for (let i = days - 1; i >= 0; i--) {
                        const d = new Date(end);
                        d.setUTCDate(d.getUTCDate() - i);
                        const key = d.toISOString().slice(0, 10);
                        out.push(Object.assign({ date: key, label: key.slice(5) }, byDate[key] || {}));
                    }
                    return out;
                }
                function histLabelsPlugin(metric) {
                    return {
                        id: 'sdsHistLabels',
                        afterDraw: function (chart) {
                            const ds = chart.data.datasets[0], meta = chart.getDatasetMeta(0), c = chart.ctx;
                            if (!ds || !meta || !meta.data) return;
                            meta.data.forEach(function (pt, i) {
                                const v = ds.data[i];
                                if (v == null || !pt) return;
                                c.save();
                                c.font = 'bold 10px Inter, system-ui, sans-serif';
                                c.fillStyle = '#111'; c.strokeStyle = 'rgba(255,255,255,0.95)'; c.lineWidth = 3; c.lineJoin = 'round';
                                c.textAlign = 'center'; c.textBaseline = 'bottom';
                                const t = metric === 'count' ? String(Math.round(v)) : moneyK(v);
                                c.strokeText(t, pt.x, pt.y - 5); c.fillText(t, pt.x, pt.y - 5);
                                c.restore();
                            });
                        },
                    };
                }
                function drawHist(metric, idx, saved) {
                    const slab = slabs[idx];
                    if (!slab) return;
                    const field = metric + '_' + slabKeyOf(slab);
                    const live = slabStats(slabs)[idx] || { n: 0, lp: 0, amz: 0 };
                    const liveVal = metric === 'count' ? live.n : live[metric];
                    const color = COLORS[idx % COLORS.length];
                    const plot = padDays(saved, 30);
                    const today = todayPT();
                    // A day with no saved number is a gap in the line, not a made-up 0. Today is always the live number.
                    const values = plot.map(function (r) {
                        if (r.date === today) return liveVal;
                        return r[field] === undefined || r[field] === null ? null : Number(r[field]);
                    });
                    document.getElementById('sds-m-hist-title').textContent =
                        METRIC_LABEL[metric] + ' · Dil ' + slab.min + '–' + slab.max + '% · last 30 days';
                    const wrap = document.getElementById('sds-m-hist-wrap');
                    wrap.classList.add('is-open');
                    const body = wrap.closest('.modal-body');
                    if (body) body.scrollTop = 0;
                    const canvas = document.getElementById('sds-m-hist');
                    if (!canvas || typeof Chart === 'undefined') return;
                    if (histChart) { histChart.destroy(); histChart = null; }
                    histChart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: plot.map(function (r) { return r.label; }),
                            datasets: [{
                                data: values, borderColor: color, backgroundColor: color + '22', fill: true, tension: 0.3,
                                borderWidth: 1.5, pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: color, pointBorderColor: color, spanGaps: false,
                            }],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false, clip: false,
                            layout: { padding: { top: 16, right: 8, bottom: 2 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: function (ctx) {
                                    return ' ' + METRIC_LABEL[metric] + ': ' + (metric === 'count' ? Math.round(num(ctx.raw)) : money(ctx.raw));
                                } } },
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { font: { size: 9 }, precision: 0, callback: function (v) { return metric === 'count' ? v : moneyK(v); } } },
                                x: { offset: true, ticks: { maxRotation: 90, minRotation: 90, autoSkip: false, font: { size: 8, weight: '600' } } },
                            },
                        },
                        plugins: [histLabelsPlugin(metric)],
                    });
                }
                function openHist(metric, idx) {
                    fetch(HIST_URL + '?days=30', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(function (res) { drawHist(metric, idx, (res && res.success && Array.isArray(res.data)) ? res.data : []); })
                        .catch(function () { drawHist(metric, idx, []); });
                }
                document.getElementById('sds-m-tbody').addEventListener('click', function (e) {
                    const dot = e.target.closest('.sds-m-hist-dot');
                    if (!dot) return;
                    openHist(dot.getAttribute('data-metric'), parseInt(dot.getAttribute('data-idx'), 10));
                });
                document.getElementById('sds-m-hist-close').addEventListener('click', function () {
                    document.getElementById('sds-m-hist-wrap').classList.remove('is-open');
                });
                document.getElementById('sdsMasterModal').addEventListener('hidden.bs.modal', function () {
                    document.getElementById('sds-m-hist-wrap').classList.remove('is-open');
                    if (histChart) { histChart.destroy(); histChart = null; }
                });

                // Save today's per-slab Count / Inv Val@LP / Inv@Amz Prc once the sales rows have loaded, so the dots have history.
                let snapped = false;
                window.sdsSnapshotHistory = function () {
                    if (snapped || !rows.length) return;
                    snapped = true;
                    fetch(MASTER_LOAD_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(function (res) {
                            const list = normList((res && Array.isArray(res.rules)) ? res.rules : []);
                            const use = list.length ? list : DEFAULTS.map(function (r) { return Object.assign({}, r); });
                            const stats = slabStats(use);
                            const values = {};
                            use.forEach(function (r, i) {
                                const k = slabKeyOf(r);
                                values['count_' + k] = stats[i].n;
                                values['lp_' + k] = stats[i].lp;
                                values['amz_' + k] = stats[i].amz;
                            });
                            return fetch(HIST_SAVE_URL, {
                                method: 'POST', credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                                body: JSON.stringify({ values: values, _token: csrf() }),
                            });
                        })
                        .catch(function () { snapped = false; });
                };

                // Counts follow the sales data once it has loaded.
                document.getElementById('sdsMasterModal').addEventListener('shown.bs.modal', function () { if (slabs.length) render(); });
            })();
        })();
    </script>
@endsection
