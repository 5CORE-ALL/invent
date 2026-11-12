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
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
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

            <div id="kw-empty" class="alert alert-warning d-none">
                No KW ads data found. Source table: <code>ebay_2_priority_reports</code>
                (RUNNING/PAUSED campaigns, report ranges L30 / L7 / L1 / yesterday / day-before).
            </div>

            <div id="kw-table"></div>
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
        const kids = allRows.filter(r => !isParent(r));
        const kw = kids.filter(hasKw);
        const st = r => String(r.kw_campaignStatus || '').toUpperCase();
        document.getElementById('kw-badge-total').textContent = 'SKUs: ' + kids.length;
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
            if (isParent(r)) return false;
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
                if (wasRunning && !s.running) { loadData(); }   // pull just finished
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
});
</script>
@endsection
