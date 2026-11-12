@extends('layouts.vertical', ['title' => 'eBay 2 KW Ads Data', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .kw-badge { display:inline-block; padding:4px 10px; border-radius:6px; font-weight:600; font-size:13px; margin-right:6px; }
        .tabulator .tabulator-row .tabulator-cell { white-space: nowrap !important; }
        .kw-yes { color:#198754; font-weight:700; }
        .kw-no { color:#dc3545; font-weight:700; }
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

            <div class="sku-only d-flex flex-wrap align-items-center gap-2 mb-3">
                <span id="kw-badge-total" class="kw-badge bg-secondary text-white">SKUs: …</span>
                <span id="kw-badge-with" class="kw-badge bg-success text-white">With KW campaign: …</span>
                <span id="kw-badge-without" class="kw-badge bg-danger text-white">No KW campaign: …</span>
                <span id="kw-badge-running" class="kw-badge bg-primary text-white">Running: …</span>
                <span id="kw-badge-paused" class="kw-badge bg-warning text-dark">Paused: …</span>
                <span id="kw-badge-spend" class="kw-badge bg-info text-dark">KW Spend L30: …</span>

                <select id="kw-status" class="form-select form-select-sm ms-auto" style="width:150px;" title="Filter by campaign Status">
                    <option value="all" selected>Status: All</option>
                    <option value="RUNNING">Status: Running</option>
                    <option value="PAUSED">Status: Paused</option>
                    <option value="NONE">Status: No campaign</option>
                </select>
                <select id="kw-filter" class="form-select form-select-sm" style="width:210px;">
                    <option value="with" selected>Only SKUs with KW campaign</option>
                    <option value="without">Only SKUs without KW data</option>
                    <option value="all">All SKUs</option>
                </select>
                <input id="kw-search" type="search" class="form-control form-control-sm" style="width:200px;" placeholder="Search SKU / campaign">
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 p-2 border rounded bg-light">
                <button id="kw-pull-btn" type="button" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-cloud-arrow-down me-1"></i> Pull Priority Reports from eBay API
                </button>
                <span id="kw-pull-state" class="small text-muted">Checking…</span>
                <span id="kw-coverage" class="small ms-auto"></span>
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
        document.getElementById('kw-badge-with').textContent = 'With KW campaign: ' + kw.length;
        document.getElementById('kw-badge-without').textContent = 'No KW campaign: ' + (kids.length - kw.length);
        document.getElementById('kw-badge-running').textContent = 'Running: ' + kw.filter(r => st(r) === 'RUNNING').length;
        document.getElementById('kw-badge-paused').textContent = 'Paused: ' + kw.filter(r => st(r) === 'PAUSED').length;
        document.getElementById('kw-badge-spend').textContent = 'KW Spend L30: ' + money(kw.reduce((s, r) => s + num(r.kw_spend_L30), 0));
        document.getElementById('kw-empty').classList.toggle('d-none', kw.length > 0);
    }

    function applyFilter() {
        const mode = document.getElementById('kw-filter').value;
        const q = document.getElementById('kw-search').value.trim().toLowerCase();
        const status = document.getElementById('kw-status').value;
        const rows = allRows.filter(r => {
            if (status !== 'all') {
                const s = String(r.kw_campaignStatus || '').toUpperCase();
                if (status === 'NONE' ? hasKw(r) : s !== status) return false;
            }
            if (mode === 'with' && !hasKw(r)) return false;
            if (mode === 'without' && hasKw(r)) return false;
            if (q && !(String(r[SKU] || '').toLowerCase().includes(q) || String(r.kw_campaign_id || '').toLowerCase().includes(q))) return false;
            return true;
        });
        table.setData(rows);
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
        checkPull();
    });

    // ---- Pull Priority Reports from the eBay Marketing API ----
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const pullBtn = document.getElementById('kw-pull-btn');
    const pullState = document.getElementById('kw-pull-state');
    let pollTimer = null;
    let wasRunning = false;

    function renderCoverage(cov) {
        const el = document.getElementById('kw-coverage');
        el.innerHTML = (cov || []).map(c => {
            const ok = c.campaigns > 0;
            return '<span class="me-3" title="' + (c.last_updated || 'never') + '">' +
                '<b>' + c.range + '</b>: ' +
                '<span class="' + (ok ? 'kw-yes' : 'kw-no') + '">' + c.campaigns + '</span> campaigns' +
                (ok ? ' (' + c.with_clicks + ' w/ clicks)' : '') + '</span>';
        }).join('');
    }

    function checkPull() {
        return fetch('{{ route('ebay2.kw.ads.pull.status') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json())
            .then(s => {
                renderCoverage(s.coverage);
                pullBtn.disabled = !!s.running;
                pullState.textContent = s.running
                    ? 'Pulling from eBay… (reports are polled every minute, this can take a while)'
                    : 'Last sync: ' + (s.last_sync || 'never');
                if (wasRunning && !s.running) { loadData(); if (campTable) loadCampaigns(); }   // pull just finished
                wasRunning = !!s.running;
                if (s.running && !pollTimer) pollTimer = setInterval(checkPull, 15000);
                if (!s.running && pollTimer) { clearInterval(pollTimer); pollTimer = null; }
            })
            .catch(() => { pullState.textContent = 'Could not check pull status.'; });
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
            .then(res => { pullState.textContent = res.message || ''; return checkPull(); })
            .catch(err => { pullBtn.disabled = false; pullState.textContent = 'Pull failed: ' + err.message; });
    });

    document.getElementById('kw-status').addEventListener('change', function () {
        // "No campaign" only makes sense when SKUs without KW data are listed
        if (this.value === 'NONE') document.getElementById('kw-filter').value = 'all';
        else if (this.value !== 'all') document.getElementById('kw-filter').value = 'with';
        applyFilter();
    });
    document.getElementById('kw-filter').addEventListener('change', applyFilter);
    document.getElementById('kw-search').addEventListener('input', applyFilter);

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
