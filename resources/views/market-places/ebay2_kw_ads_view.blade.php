@extends('layouts.vertical', ['title' => 'eBay 2 KW Ads Data', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .kw-badge { display:inline-block; padding:4px 10px; border-radius:6px; font-weight:600; font-size:13px; margin-right:6px; }
        .tabulator .tabulator-row .tabulator-cell { white-space: nowrap !important; }
        .kw-yes { color:#198754; font-weight:700; }
        .kw-no { color:#dc3545; font-weight:700; }

        /* ── Summary cards (same look as /amazon-ads/all): yellow card + title + equal-size white tiles ── */
        .ekc-cards { display:grid; grid-template-columns:repeat(12, minmax(0, 1fr)); gap:.5rem; align-items:start; margin-bottom:.75rem; }
        .ekc-card { grid-column:span 4; min-width:0; border-radius:12px; padding:.55rem .6rem .65rem; background:#fef9c3; border:1px solid #facc15; box-shadow:0 1px 2px rgba(15,23,42,.06); }
        .ekc-card-title { font-size:.68rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; line-height:1; margin:0 0 .35rem; color:#854d0e; }
        .ekc-tiles { display:grid; gap:.4rem; align-items:stretch; grid-template-columns:repeat(3, minmax(0, 1fr)); }
        .ekc-tile { display:inline-flex; flex-wrap:wrap; align-items:center; justify-content:center; text-align:center; column-gap:4px;
            width:100%; min-width:0; min-height:36px; box-sizing:border-box; white-space:normal; overflow-wrap:anywhere;
            font-size:clamp(.68rem, .62vw + .28rem, .78rem); font-weight:700; line-height:1.25; padding:.3rem .4rem; border-radius:.375rem;
            background:#fff; color:#111827; border:1px solid #e5e7eb; }
        .ekc-tile > .ekc-val { font-size:1.08em; font-weight:800; white-space:nowrap; }
        .ekc-tile > .ekc-pct { white-space:nowrap; font-size:.75rem; font-weight:700; opacity:.95; }
        .ekc-tile.ekc-better { background:#22c55e; color:#052e16; border-color:#16a34a; }
        .ekc-tile.ekc-worse { background:#ef4444; color:#fff; border-color:#dc2626; }
        .ekc-tile.ekc-campaign { background:#4c7ed8; color:#fff; border-color:#3b6cc4; }
        .ekc-tile.ekc-active { background:#16a34a; color:#fff; border-color:#15803d; }
        button.ekc-tile.ekc-flag { cursor:pointer; background:#f0fdf4; color:#166534; border-color:#86efac; }
        button.ekc-tile.ekc-flag:hover { background:#dcfce7; }
        button.ekc-tile.ekc-flag.is-active { background:#16a34a; color:#fff; border-color:#15803d; box-shadow:0 0 0 2px #86efac; }
        button.ekc-tile.ekc-flag .fa-flag { color:#16a34a; }
        button.ekc-tile.ekc-flag.is-active .fa-flag { color:#fff; }
        @media (max-width:1399.98px) { .ekc-card { grid-column:span 6; } }
        @media (max-width:991.98px) { .ekc-card { grid-column:span 12; } }
    </style>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'eBay 2 KW Ads Data', 'sub_title' => 'eBay 2'])

    <div class="card">
        <div class="card-body">
            <div class="btn-group mb-3" role="group" aria-label="View">
                <button type="button" id="view-sku-btn" class="btn btn-sm btn-primary">SKUs</button>
                <button type="button" id="view-camp-btn" class="btn btn-sm btn-outline-primary">Campaigns</button>
            </div>

            @php
                $ekcCards = [
                    ['tag' => 'yesterday', 'title' => 'Yesterday', 'items' => [
                        ['y_bgt', 'Y BGT', 'Daily budget of the campaigns shown (campaignBudgetAmount).'],
                        ['y_util', 'Y UTIL', 'Yesterday utilization = yesterday spend ÷ daily budget.'],
                        ['y_bid', 'Y AVG BID', 'Yesterday avg bid = yesterday spend ÷ clicks (avg CPC).'],
                        ['y_cvr', 'Y CVR%', 'Yesterday CVR% = yesterday sold ÷ clicks.'],
                        ['y_acos', 'Y ACOS%', 'Yesterday ACOS% = yesterday spend ÷ yesterday ads sales.'],
                        ['y_tacos', 'Y TACOS%', 'Yesterday TACOS% (Total ACOS) = yesterday ad spend ÷ yesterday TOTAL eBay 2 sales (all orders, not only ad sales).'],
                        ['y_clicks', 'Y CLICKS', 'Yesterday clicks (L1 report).'],
                        ['y_sold', 'Y SOLD', 'Yesterday units sold through ads (L1 report).'],
                        ['y_sales', 'Y SALES', 'Yesterday ads sales (L1 report).'],
                        ['y_spend', 'Y SPEND', 'Yesterday ad fees (L1 report).'],
                    ]],
                    ['tag' => 'l30', 'title' => 'L30', 'items' => [
                        ['l30_bid', 'AVG BID', 'L30 avg bid = 30-day spend ÷ clicks (avg CPC).'],
                        ['l30_cvr', 'CVR%', 'Ads CVR = ads sold ÷ ads clicks (L30).'],
                        ['l30_acos', 'ACOS%', 'L30 ACOS% = 30-day spend ÷ 30-day ads sales.'],
                        ['l30_clicks', 'CLICKS', 'Clicks (L30).'],
                        ['l30_sold', 'SOLD', 'Sold through ads (L30).'],
                        ['l30_sales', 'SALES', 'Ads sales (L30).'],
                        ['l30_spend', 'SPEND', 'Ad fees (L30).'],
                        ['l30_bgt_total', 'BGT × 30', 'Daily budget × 30.'],
                    ]],
                    ['tag' => 'projected', 'title' => 'Projected · L7 ÷ 7 × 30', 'items' => [
                        ['p_bid', 'AVG BID', 'L7 avg bid = 7-day spend ÷ clicks (avg CPC).'],
                        ['p_cvr', 'CVR%', 'L7 CVR% = 7-day sold ÷ clicks.'],
                        ['p_acos', 'ACOS%', 'Projected ACOS% = projected spend ÷ projected ads sales.'],
                        ['p_tacos', 'TACOS%', 'Projected TACOS% = projected spend ÷ projected TOTAL eBay 2 sales (L7 pace, all orders, not only ad sales).'],
                        ['p_clicks', 'CLICKS', 'Projected clicks = L7 clicks ÷ 7 × 30.'],
                        ['p_sold', 'SOLD', 'Projected sold = L7 sold ÷ 7 × 30.'],
                        ['p_sales', 'SALES', 'Projected ads sales = L7 ads sales ÷ 7 × 30.'],
                        ['p_spend', 'SPEND', 'Projected spend = L7 spend ÷ 7 × 30.'],
                        ['p_bgt_total', 'BGT × 30', 'Daily budget × 30.'],
                    ]],
                ];
            @endphp
            <div class="sku-only ekc-cards" id="ekcCards">
                @foreach ($ekcCards as $card)
                    <div class="ekc-card ekc-card--{{ $card['tag'] }}">
                        <div class="ekc-card-title">{{ $card['title'] }}</div>
                        <div class="ekc-tiles">
                            @foreach ($card['items'] as [$key, $text, $tip])
                                <span class="ekc-tile" data-kpi="{{ $key }}" title="{{ $tip }}">{{ $text }}:<span class="ekc-val">—</span></span>
                            @endforeach
                            @if ($card['tag'] === 'l30')
                                <button type="button" id="ekcFlagBtn" class="ekc-tile ekc-flag" aria-pressed="false" title="Green flag: campaigns whose L30 ACOS% is below the SKU's GPFT% (campaigns with L30 spend only; PARENT rows have no GPFT% so they are not counted). Count covers the campaigns matching the current filters. Click to show only those rows, click again to clear."><i class="fas fa-flag"></i>ACOS%:<span class="ekc-val" id="ekcFlagCount">0</span></button>
                            @endif
                            @if ($card['tag'] === 'yesterday')
                                <span class="ekc-tile ekc-campaign" title="Distinct KW campaigns matching the current filters.">CAMPAIGN:<span class="ekc-val" id="ekcCampaignCount">0</span></span>
                                <span class="ekc-tile ekc-active" title="RUNNING campaigns out of the campaigns matching the current filters, and the share that is active.">ACTIVE:<span class="ekc-val" id="ekcActiveCount">—</span><span class="ekc-pct" id="ekcActivePct"></span></span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="sku-only d-flex flex-wrap align-items-center gap-2 mb-3">
                <span id="kw-badge-total" class="kw-badge bg-secondary text-white">SKUs: …</span>
                <span id="kw-badge-running" class="kw-badge bg-primary text-white">Running: …</span>
                <span id="kw-badge-paused" class="kw-badge bg-warning text-dark">Paused: …</span>

                <select id="kw-status" class="form-select form-select-sm ms-auto" style="width:150px;" title="Filter by campaign Status">
                    <option value="RUNNING" selected>Status: Active</option>
                    <option value="PAUSED">Status: Paused</option>
                    <option value="NONE">Status: No campaign</option>
                    <option value="all">Status: All</option>
                </select>
                <input id="kw-search" type="search" class="form-control form-control-sm" style="width:200px;" placeholder="Search SKU / campaign">
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 border rounded bg-light">
                <button id="kw-pull-btn" type="button" class="btn btn-sm btn-primary" title="Pull Priority Reports from eBay API" aria-label="Pull Priority Reports from eBay API">
                    <i class="fa-solid fa-cloud-arrow-down"></i>
                </button>
            </div>

            <div id="kw-empty" class="sku-only alert alert-warning d-none">
                No KW ads data found. Source table: <code>ebay_2_priority_reports</code>
                (RUNNING/PAUSED campaigns, report ranges L30 / L7 / L1 / yesterday / day-before).
            </div>

            <div id="kw-table" class="sku-only"></div>

            <div id="camp-view" class="d-none">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Clicks (L30)</div><div id="camp-clicks" class="fs-3 fw-bold">0</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Sales (L30)</div><div id="camp-sales" class="fs-3 fw-bold">$0.00</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">Ad fees (L30)</div><div id="camp-fees" class="fs-3 fw-bold">$0.00</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-3"><div class="text-muted small">ROAS</div><div id="camp-roas" class="fs-3 fw-bold">0.00</div></div></div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <select id="camp-status" class="form-select form-select-sm" style="width:150px;">
                        <option value="all">Status: All</option>
                        <option value="RUNNING" selected>Status: Active</option>
                        <option value="PAUSED">Status: Paused</option>
                        <option value="ENDED">Status: Ended</option>
                    </select>
                    <select id="camp-type" class="form-select form-select-sm" style="width:210px;">
                        <option value="COST_PER_CLICK" selected>Type: Priority</option>
                        <option value="COST_PER_SALE">Type: Standard</option>
                        <option value="all">Type: All</option>
                    </select>
                    <input id="camp-search" type="search" class="form-control form-control-sm" style="width:240px;" placeholder="Search campaign">
                    <span id="camp-count" class="small text-muted ms-auto"></span>
                </div>
                <div id="camp-table"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const SKU = '(Child) sku';
    let allRows = [];

    const num = v => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const money = v => '$' + num(v).toFixed(2);
    const hasKw = r => !!(r.kw_campaign_id && String(r.kw_campaign_id) !== '');
    const isParent = r => String(r[SKU] || '').toUpperCase().startsWith('PARENT');

    const table = new Tabulator('#kw-table', {
        height: '70vh',
        layout: 'fitDataFill',
        placeholder: 'Loading…',
        columns: [
            { title: 'SKU', field: SKU, frozen: true, width: 180, headerFilter: 'input' },
            { title: 'INV', field: 'INV', hozAlign: 'center', sorter: 'number', width: 70 },
            { title: 'E L30', field: 'eBay L30', hozAlign: 'center', sorter: 'number', width: 70 },
            { title: 'Campaign', field: 'kw_campaign_name', width: 230, headerFilter: 'input' },
            { title: 'KW?', field: 'kw_campaign_id', hozAlign: 'center', width: 70,
              formatter: c => hasKw(c.getRow().getData()) ? '<span class="kw-yes">Yes</span>' : '<span class="kw-no">No</span>' },
            { title: 'Status', field: 'kw_campaignStatus', hozAlign: 'center', width: 95 },
            { title: 'Budget', field: 'kw_campaignBudgetAmount', hozAlign: 'right', sorter: 'number', width: 90, formatter: c => money(c.getValue()) },
            { title: 'Spend L30', field: 'kw_spend_L30', hozAlign: 'right', sorter: 'number', width: 100, formatter: c => money(c.getValue()) },
            { title: 'Clicks L30', field: 'kw_clicks', hozAlign: 'center', sorter: 'number', width: 95 },
            { title: 'Ad Sold', field: 'kw_ad_sold', hozAlign: 'center', sorter: 'number', width: 85 },
            { title: 'ACOS %', field: 'kw_acos', hozAlign: 'center', sorter: 'number', width: 85, formatter: c => num(c.getValue()).toFixed(2) + '%' },
            { title: 'CVR %', field: 'kw_cvr', hozAlign: 'center', sorter: 'number', width: 85, formatter: c => num(c.getValue()).toFixed(2) + '%' },
            { title: 'Impr L30', field: 'kw_impressions', hozAlign: 'center', sorter: 'number', width: 90, formatter: c => num(c.getValue()).toLocaleString() },
            { title: 'CTR %', field: 'kw_ctr', hozAlign: 'center', sorter: 'number', width: 80, formatter: c => num(c.getValue()).toFixed(2) + '%' },
            { title: 'CPC L30', field: 'kw_cpc_l30', hozAlign: 'right', sorter: 'number', width: 85, formatter: c => money(c.getValue()) },
            { title: 'Sales $ L30', field: 'kw_sales_amount', hozAlign: 'right', sorter: 'number', width: 100, formatter: c => money(c.getValue()) },
            { title: 'ROAS', field: 'kw_roas', hozAlign: 'center', sorter: 'number', width: 75, formatter: c => num(c.getValue()).toFixed(2) },
            { title: 'Avg Cost/Sale', field: 'kw_avg_cost_per_sale', hozAlign: 'right', sorter: 'number', width: 110, formatter: c => money(c.getValue()) },
            { title: 'Spend L7', field: 'kw_l7_spend', hozAlign: 'right', sorter: 'number', width: 90, formatter: c => money(c.getValue()) },
            { title: 'CPC L7', field: 'kw_l7_cpc', hozAlign: 'right', sorter: 'number', width: 85, formatter: c => money(c.getValue()) },
            { title: 'Spend L1', field: 'kw_l1_spend', hozAlign: 'right', sorter: 'number', width: 90, formatter: c => money(c.getValue()) },
            { title: 'CPC L1', field: 'kw_l1_cpc', hozAlign: 'right', sorter: 'number', width: 85, formatter: c => money(c.getValue()) },
            { title: 'Campaign ID', field: 'kw_campaign_id', width: 130 },
        ],
    });

    function updateBadges() {
        const kids = allRows;   // parents included: eBay 2 KW campaigns are parent-level ("PARENT X KW")
        const kw = kids.filter(hasKw);
        const st = r => String(r.kw_campaignStatus || '').toUpperCase();
        document.getElementById('kw-badge-total').textContent = 'SKUs + parents: ' + kids.length;
        document.getElementById('kw-badge-running').textContent = 'Running: ' + kw.filter(r => st(r) === 'RUNNING').length;
        document.getElementById('kw-badge-paused').textContent = 'Paused: ' + kw.filter(r => st(r) === 'PAUSED').length;
        document.getElementById('kw-empty').classList.toggle('d-none', kw.length > 0);
    }

    function applyFilter() {
        const q = document.getElementById('kw-search').value.trim().toLowerCase();
        const status = document.getElementById('kw-status').value;
        const rows = allRows.filter(r => {
            if (status !== 'all') {
                const s = String(r.kw_campaignStatus || '').toUpperCase();
                if (status === 'NONE' ? hasKw(r) : s !== status) return false;
            }
            if (q && !(String(r[SKU] || '').toLowerCase().includes(q) || String(r.kw_campaign_id || '').toLowerCase().includes(q) || String(r.kw_campaign_name || '').toLowerCase().includes(q))) return false;
            return true;
        });
        paintKpiCards(rows);
        table.setData(ekcFlagOn ? rows.filter(isFlagged) : rows);
        table.setSort([{ column: 'kw_spend_L30', dir: 'desc' }]);
    }

    function loadData() {
        return fetch('/ebay2-data-json', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(res => {
                allRows = Array.isArray(res) ? res : (res && Array.isArray(res.data) ? res.data : []);
                updateBadges();
                applyFilter();
            })
            .catch(err => { table.alert('Failed to load data: ' + err.message, 'error'); });
    }

    table.on('tableBuilt', function () {
        loadData();
        loadTotalSales();
        checkPull();
    });

    // ---- Pull Priority Reports from the eBay Marketing API ----
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const pullBtn = document.getElementById('kw-pull-btn');
    const pullIcon = pullBtn.querySelector('i');
    // No visible status text: feedback goes to the button tooltip and a spinning icon while a pull runs.
    const setPullInfo = (msg, spinning) => {
        pullBtn.title = 'Pull Priority Reports from eBay API' + (msg ? ' - ' + msg : '');
        pullIcon.className = spinning ? 'fa-solid fa-spinner fa-spin' : 'fa-solid fa-cloud-arrow-down';
    };
    let pollTimer = null;
    let wasRunning = false;

    function checkPull() {
        return fetch('{{ route('ebay2.kw.ads.pull.status') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(s => {
                pullBtn.disabled = !!s.running;
                setPullInfo(s.running ? 'running on eBay, can take a while' : 'last sync: ' + (s.last_sync || 'never'), !!s.running);
                if (wasRunning && !s.running) { loadData(); loadTotalSales(); if (campTable) loadCampaigns(); }   // pull just finished
                wasRunning = !!s.running;
                if (s.running && !pollTimer) pollTimer = setInterval(checkPull, 15000);
                if (!s.running && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            })
            .catch(() => { setPullInfo('could not check pull status', false); });
    }

    pullBtn.addEventListener('click', function () {
        if (!confirm('Pull all Promoted Listings priority reports (L90/L60/L30/L15/L7/L1 + yesterday) from the eBay 2 API?')) return;
        pullBtn.disabled = true;
        fetch('{{ route('ebay2.kw.ads.pull') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(res => { setPullInfo(res.message || '', !!res.running); return checkPull(); })
            .catch(err => { pullBtn.disabled = false; setPullInfo('pull failed: ' + err.message, false); });
    });

    document.getElementById('kw-status').addEventListener('change', applyFilter);
    document.getElementById('kw-search').addEventListener('input', applyFilter);

    // ---- Summary cards (Yesterday / L30 / Projected): same layout and formulas as /amazon-ads/all ----
    // Windows: Yesterday = L1 report, L30 = L30 report, Projected = L7 report ÷ 7 × 30. Campaigns = the KW campaigns of the rows
    // currently shown (deduped by campaign id). UTIL = spend ÷ (BGT × days), AVG BID = spend ÷ clicks, CVR = sold ÷ clicks,
    // ACOS = spend ÷ ads sales, TACOS = spend ÷ total eBay 2 sales for the same window.
    let ekcFlagOn = false;
    let totalSales = { l1: null, l7: null, l30: null };
    const KPI_KIND = {
        bgt:       { fmt: v => '$' + Math.round(v).toLocaleString('en-US'), lower: false },
        bgt_total: { fmt: v => '$' + Math.round(v).toLocaleString('en-US'), lower: false },
        util:      { fmt: v => v.toFixed(1) + '%', lower: false },
        bid:       { fmt: v => '$' + v.toFixed(2), lower: true },
        cvr:       { fmt: v => v.toFixed(2) + '%', lower: false },
        acos:      { fmt: v => Math.round(v) + '%', lower: true },
        tacos:     { fmt: v => Math.round(v) + '%', lower: true },
        clicks:    { fmt: v => Math.round(v).toLocaleString('en-US'), lower: false },
        sold:      { fmt: v => Math.round(v).toLocaleString('en-US'), lower: false },
        sales:     { fmt: v => '$' + Math.round(v).toLocaleString('en-US'), lower: false },
        spend:     { fmt: v => '$' + Math.round(v).toLocaleString('en-US'), lower: false },
    };
    const KPI_FIELDS = {
        l1:  { spend: 'kw_l1_spend', clicks: 'kw_l1_clicks', sold: 'kw_l1_sold', sales: 'kw_l1_sales' },
        l7:  { spend: 'kw_l7_spend', clicks: 'kw_l7_clicks', sold: 'kw_l7_sold', sales: 'kw_l7_sales' },
        l30: { spend: 'kw_spend_L30', clicks: 'kw_clicks', sold: 'kw_ad_sold', sales: 'kw_sales_amount' },
    };
    const isFlagged = r => hasKw(r) && num(r.kw_spend_L30) > 0 && num(r.kw_acos) < num(r['GPFT%']);

    function kpiCampaigns(rows) {
        const seen = new Map();
        rows.forEach(r => { if (hasKw(r)) seen.set(String(r.kw_campaign_id), r); });
        return Array.from(seen.values());
    }

    function kpiWindow(camps, key, days) {
        const f = KPI_FIELDS[key];
        const sum = field => camps.reduce((s, r) => s + num(r[field]), 0);
        const bud = sum('kw_campaignBudgetAmount'), spend = sum(f.spend), clicks = sum(f.clicks), sold = sum(f.sold), sales = sum(f.sales);
        const ts = totalSales[key];
        return {
            bgt: bud,
            util: bud > 0 ? spend / (bud * days) * 100 : null,
            bid: clicks > 0 ? spend / clicks : null,
            cvr: clicks > 0 ? sold / clicks * 100 : null,
            acos: sales > 0 ? spend / sales * 100 : (spend > 0 ? 100 : null),
            tacos: (ts !== null && ts > 0) ? spend / ts * 100 : null,
            clicks, sold, sales, spend,
        };
    }

    function paintKpiCards(rows) {
        const camps = kpiCampaigns(rows);
        const y = kpiWindow(camps, 'l1', 1);
        const l30 = kpiWindow(camps, 'l30', 30);
        const p = kpiWindow(camps, 'l7', 7);
        const proj = v => v === null ? null : v / 7 * 30;
        const pv = {
            bgt: p.bgt, util: p.util, bid: p.bid, cvr: p.cvr, acos: p.acos, tacos: p.tacos,
            clicks: proj(p.clicks), sold: proj(p.sold), sales: proj(p.sales), spend: proj(p.spend),
            bgt_total: p.bgt * 30,
        };
        l30.bgt_total = l30.bgt * 30;

        const vals = {}, cmp = {}, base = {};
        Object.keys(y).forEach(k => { vals['y_' + k] = y[k]; });
        Object.keys(l30).forEach(k => { vals['l30_' + k] = l30[k]; });
        Object.keys(pv).forEach(k => { vals['p_' + k] = pv[k]; });
        // Yesterday and Projected vs L30: better = green, otherwise red (Yesterday volume tiles vs the L30 per-day average).
        Object.keys(vals).forEach(key => {
            if (key.indexOf('l30_') === 0) return;
            const kind = key.replace(/^(y|p)_/, '');
            let ref = l30[kind];
            if (key.charAt(0) === 'y' && /^(clicks|sold|sales|spend)$/.test(kind) && ref !== null && ref !== undefined) ref = ref / 30;
            const cur = vals[key];
            if (cur === null || cur === undefined || ref === null || ref === undefined || !camps.length) return;
            cmp[key] = (KPI_KIND[kind].lower ? cur < ref - 1e-9 : cur > ref + 1e-9) ? 'better' : 'worse';
            base[key] = ref;
        });

        document.querySelectorAll('#ekcCards .ekc-tile[data-kpi]').forEach(el => {
            const key = el.getAttribute('data-kpi');
            const kind = key.replace(/^(y|p|l30)_/, '');
            const v = vals[key];
            const valEl = el.querySelector('.ekc-val');
            const show = camps.length && v !== null && v !== undefined && KPI_KIND[kind];
            if (valEl) valEl.textContent = show ? KPI_KIND[kind].fmt(v) : '—';
            el.classList.remove('ekc-better', 'ekc-worse');
            if (el.dataset.tip === undefined) el.dataset.tip = el.getAttribute('title') || '';
            let tip = el.dataset.tip;
            if (cmp[key]) {
                el.classList.add(cmp[key] === 'better' ? 'ekc-better' : 'ekc-worse');
                const refLbl = (key.charAt(0) === 'y' && /_(clicks|sold|sales|spend)$/.test(key)) ? 'L30 daily avg' : 'L30';
                tip += ' · ' + (cmp[key] === 'better' ? 'Better' : 'Worse') + ' than ' + refLbl + ' (' + KPI_KIND[kind].fmt(base[key]) + ').';
            }
            el.setAttribute('title', tip);
        });

        const running = camps.filter(r => String(r.kw_campaignStatus || '').toUpperCase() === 'RUNNING').length;
        document.getElementById('ekcCampaignCount').textContent = camps.length.toLocaleString('en-US');
        document.getElementById('ekcActiveCount').textContent = camps.length ? running.toLocaleString('en-US') + ' / ' + camps.length.toLocaleString('en-US') : '—';
        document.getElementById('ekcActivePct').textContent = camps.length ? Math.round(running / camps.length * 100) + '%' : '';
        document.getElementById('ekcFlagCount').textContent = camps.filter(isFlagged).length.toLocaleString('en-US');
    }

    function loadTotalSales() {
        return fetch('{{ route('ebay2.kw.ads.total.sales') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(t => { totalSales = { l1: num(t.l1), l7: num(t.l7), l30: num(t.l30) }; applyFilter(); })
            .catch(() => {});   // TACOS tiles just stay "—"
    }

    document.getElementById('ekcFlagBtn').addEventListener('click', function () {
        ekcFlagOn = !ekcFlagOn;
        this.classList.toggle('is-active', ekcFlagOn);
        this.setAttribute('aria-pressed', ekcFlagOn ? 'true' : 'false');
        applyFilter();
    });

    // ---- Campaigns view (one row per campaign, like Seller Hub > Advertising) ----
    let campRows = [];
    let campTable = null;
    const $ = id => document.getElementById(id);

    function buildCampTable() {
        campTable = new Tabulator('#camp-table', {
            height: '65vh',
            layout: 'fitDataFill',
            placeholder: 'Loading…',
            columns: [
                { title: 'Campaign', field: 'campaign_name', width: 320, headerFilter: 'input' },
                { title: 'Campaign type', field: 'campaign_type', width: 190 },
                { title: 'Status', field: 'status', hozAlign: 'center', width: 100,
                  formatter: c => { const v = String(c.getValue() || ''); return v === 'RUNNING' ? 'Active' : v.charAt(0) + v.slice(1).toLowerCase(); } },
                { title: 'Target daily budget', field: 'daily_budget', hozAlign: 'right', sorter: 'number', width: 150, formatter: c => money(c.getValue()) },
                { title: 'Clicks', field: 'clicks', hozAlign: 'center', sorter: 'number', width: 85 },
                { title: 'Impr', field: 'impressions', hozAlign: 'center', sorter: 'number', width: 90, formatter: c => num(c.getValue()).toLocaleString() },
                { title: 'Sold', field: 'sold', hozAlign: 'center', sorter: 'number', width: 80 },
                { title: 'Sales', field: 'sales', hozAlign: 'right', sorter: 'number', width: 100, formatter: c => money(c.getValue()) },
                { title: 'Ad fees', field: 'ad_fees', hozAlign: 'right', sorter: 'number', width: 100, formatter: c => money(c.getValue()) },
                { title: 'ROAS', field: 'roas', hozAlign: 'center', sorter: 'number', width: 80, formatter: c => num(c.getValue()).toFixed(2) },
                { title: 'CTR %', field: 'ctr', hozAlign: 'center', sorter: 'number', width: 80, formatter: c => num(c.getValue()).toFixed(2) + '%' },
                { title: 'CVR %', field: 'cvr', hozAlign: 'center', sorter: 'number', width: 80, formatter: c => num(c.getValue()).toFixed(2) + '%' },
                { title: 'Campaign ID', field: 'campaign_id', width: 130 },
            ],
        });
    }

    function applyCampFilter() {
        const st = $('camp-status').value;
        const q = $('camp-search').value.trim().toLowerCase();
        const ty = $('camp-type').value;
        const rows = campRows.filter(r => (st === 'all' || r.status === st) && (ty === 'all' || r.funding_model === ty) &&
            (!q || String(r.campaign_name).toLowerCase().includes(q) || r.campaign_id.includes(q)));
        const clicks = rows.reduce((s, r) => s + r.clicks, 0);
        const sales = rows.reduce((s, r) => s + r.sales, 0);
        const fees = rows.reduce((s, r) => s + r.ad_fees, 0);
        $('camp-clicks').textContent = clicks.toLocaleString();
        $('camp-sales').textContent = money(sales);
        $('camp-fees').textContent = money(fees);
        $('camp-roas').textContent = (fees > 0 ? sales / fees : 0).toFixed(2);
        const unknown = campRows.filter(r => !r.funding_model).length;
        $('camp-count').textContent = rows.length + ' campaigns' + (unknown ? ' — ' + unknown + ' have no type yet: run  php artisan app:ebay2-campaign-reports --meta-only' : '');
        campTable.setData(rows);
        campTable.setSort([{ column: 'ad_fees', dir: 'desc' }]);
    }

    function loadCampaigns() {
        return fetch('{{ route('ebay2.kw.ads.campaigns') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(res => { campRows = res.data || []; applyCampFilter(); })
            .catch(err => { campTable.alert('Failed to load campaigns: ' + err.message, 'error'); });
    }

    function showView(which) {
        const camp = which === 'camp';
        document.querySelectorAll('.sku-only').forEach(el => el.classList.toggle('d-none', camp));
        $('camp-view').classList.toggle('d-none', !camp);
        $('view-sku-btn').className = 'btn btn-sm ' + (camp ? 'btn-outline-primary' : 'btn-primary');
        $('view-camp-btn').className = 'btn btn-sm ' + (camp ? 'btn-primary' : 'btn-outline-primary');
        if (camp) {
            if (!campTable) { buildCampTable(); campTable.on('tableBuilt', loadCampaigns); }
            else { campTable.redraw(true); }
        } else {
            table.redraw(true);
            updateBadges();   // restores kw-empty visibility
        }
    }
    $('view-sku-btn').addEventListener('click', () => showView('sku'));
    $('view-camp-btn').addEventListener('click', () => showView('camp'));
    $('camp-status').addEventListener('change', applyCampFilter);
    $('camp-type').addEventListener('change', applyCampFilter);
    $('camp-search').addEventListener('input', applyCampFilter);
});
</script>
@endsection
