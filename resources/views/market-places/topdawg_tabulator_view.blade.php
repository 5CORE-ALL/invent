@extends('layouts.vertical', ['title' => 'TopDawg Pricing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; white-space: nowrap;
            transform: rotate(180deg); height: 80px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        #summary-stats .badge.active-filter {
            box-shadow: 0 0 0 3px rgba(255,255,255,.85), 0 0 0 5px currentColor;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TopDawg Pricing',
        'sub_title' => 'TopDawg Pricing & Inventory Mapping',
    ])
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>TopDawg Pricing</h4>
                <p class="text-muted small mb-2">Price &amp; stock from <code>topdawg_products</code> (API). L30/L60 from order metrics. Run <code>php artisan topdawg:fetch</code> on server to refresh.</p>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>
                    <select id="td-stock-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">TD Stock</option>
                        <option value="zero">0 TD Stock</option>
                        <option value="more">More than 0</option>
                    </select>
                    <select id="nrl-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>
                    <select id="gpft-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30plus">30%+</option>
                    </select>
                    <a href="{{ route('all.marketplace.master') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-th-large"></i> All Marketplace Master
                    </a>
                    <button id="export-btn" class="btn btn-sm btn-info"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary ({{ $topdawgPercentage ?? 95 }}% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color:#000;font-weight:bold;" title="Average GPFT% on filtered rows">AVG GPFT: 0%</span>
                        <span class="badge bg-success fs-6 p-2" id="total-td-l30-badge" style="color:#000;font-weight:bold;" title="Sum of TD L30 on filtered rows">TD L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-badge" style="color:#fff;font-weight:bold;cursor:pointer;" title="SKUs with TD L30 = 0">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-badge" style="background:#28a745;color:#fff;font-weight:bold;cursor:pointer;" title="SKUs with TD L30 &gt; 0">&gt; 0 Sold: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-badge" style="color:#fff;font-weight:bold;cursor:pointer;" title="REQ + INV&gt;0 + TD Price=0">Missing L: 0</span>
                        <span class="badge fs-6 p-2" id="map-badge" style="background:#198754;color:#fff;font-weight:bold;cursor:pointer;" title="|INV − TD Stock| ≤ 3">Map: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="nmap-badge" style="color:#fff;font-weight:bold;cursor:pointer;" title="|INV − TD Stock| &gt; 3">N Map: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="topdawg-table-wrapper" style="height:calc(100vh - 240px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <div id="topdawg-pricing-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const TD_MAP_TOLERANCE = 3;
    let table = null;
    let zeroSoldFilter = false, moreSoldFilter = false;
    let missingFilter = false, mapFilter = false, nmapFilter = false;

    function applyUrlBadgeFilter() {
        const p = new URLSearchParams(window.location.search).get('badge');
        missingFilter = mapFilter = nmapFilter = zeroSoldFilter = moreSoldFilter = false;
        if (p === 'missing') missingFilter = true;
        else if (p === 'map') mapFilter = true;
        else if (p === 'nmap') nmapFilter = true;
        else if (p === 'zero_sold') zeroSoldFilter = true;
        else if (p === 'more_sold') moreSoldFilter = true;
    }

    function setActiveBadges() {
        $('#missing-badge').toggleClass('active-filter', missingFilter);
        $('#map-badge').toggleClass('active-filter', mapFilter);
        $('#nmap-badge').toggleClass('active-filter', nmapFilter);
        $('#zero-sold-badge').toggleClass('active-filter', zeroSoldFilter);
        $('#more-sold-badge').toggleClass('active-filter', moreSoldFilter);
    }

    function getSummaryRows() {
        if (!table) return [];
        const rows = table.getRows('active');
        const data = (rows && rows.length)
            ? rows.map(r => r.getData())
            : (table.getData() || []);
        return data.filter(r => !(r.Parent && String(r.Parent).toUpperCase().startsWith('PARENT')));
    }

    function updateSummary() {
        const data = getSummaryRows();
        let totalTdL30 = 0, totalGpft = 0;
        let zeroSold = 0, moreSold = 0, missing = 0, mapC = 0, nmapC = 0;

        data.forEach(row => {
            const tdL30 = parseInt(row['TD L30'], 10) || 0;
            totalTdL30 += tdL30;
            totalGpft += parseFloat(row['GPFT%']) || 0;
            tdL30 === 0 ? zeroSold++ : moreSold++;

            const inv = parseFloat(row.INV) || 0;
            const nrReq = row.nr_req || 'REQ';
            const isMissing = row.Missing === 'M';
            if (isMissing && nrReq === 'REQ' && inv > 0) missing++;
            const mapVal = row.MAP || '';
            if (nrReq === 'REQ' && inv > 0 && !isMissing) {
                if (mapVal === 'Map') mapC++;
                else if (mapVal.includes('N Map|')) nmapC++;
            }
        });

        $('#avg-gpft-badge').text('AVG GPFT: ' + (data.length ? Math.round(totalGpft / data.length) : 0) + '%');
        $('#total-td-l30-badge').text('TD L30: ' + totalTdL30.toLocaleString());
        $('#zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
        $('#more-sold-badge').text('> 0 Sold: ' + moreSold.toLocaleString());
        $('#missing-badge').text('Missing L: ' + missing.toLocaleString());
        $('#map-badge').text('Map: ' + mapC.toLocaleString());
        $('#nmap-badge').text('N Map: ' + nmapC.toLocaleString());
    }

    function applyFilters() {
        if (!table) return;
        table.clearFilter();

        const invF = $('#inventory-filter').val();
        if (invF === 'zero') table.addFilter('INV', '=', 0);
        if (invF === 'more') table.addFilter('INV', '>', 0);

        const tdF = $('#td-stock-filter').val();
        if (tdF === 'zero') table.addFilter('TD Stock', '=', 0);
        if (tdF === 'more') table.addFilter('TD Stock', '>', 0);

        const nrl = $('#nrl-filter').val();
        if (nrl !== 'all') table.addFilter('nr_req', '=', nrl);

        const gpft = $('#gpft-filter').val();
        if (gpft !== 'all') {
            table.addFilter(data => {
                const g = parseFloat(data['GPFT%']) || 0;
                if (gpft === 'negative') return g < 0;
                if (gpft === '0-10') return g >= 0 && g < 10;
                if (gpft === '10-20') return g >= 10 && g < 20;
                if (gpft === '20-30') return g >= 20 && g < 30;
                if (gpft === '30plus') return g >= 30;
                return true;
            });
        }

        if (zeroSoldFilter) table.addFilter(data => (parseInt(data['TD L30'], 10) || 0) === 0);
        if (moreSoldFilter) table.addFilter(data => (parseInt(data['TD L30'], 10) || 0) > 0);
        if (missingFilter) table.addFilter(data => data.Missing === 'M' && data.nr_req === 'REQ' && (parseFloat(data.INV) || 0) > 0);
        if (mapFilter) table.addFilter(data => data.MAP === 'Map' && data.nr_req === 'REQ' && (parseFloat(data.INV) || 0) > 0 && data.Missing !== 'M');
        if (nmapFilter) table.addFilter(data => (data.MAP || '').includes('N Map|') && data.nr_req === 'REQ' && (parseFloat(data.INV) || 0) > 0 && data.Missing !== 'M');

        setActiveBadges();
        updateSummary();
    }

    $(document).ready(function() {
        applyUrlBadgeFilter();

        table = new Tabulator('#topdawg-pricing-table', {
            ajaxURL: '/topdawg-data-json',
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [50, 100, 200, 500],
            initialSort: [{ column: 'TD L30', dir: 'desc' }],
            columns: [
                { title: 'Parent', field: 'Parent', frozen: true, width: 150, visible: false },
                { title: 'Image', field: 'image_path', width: 80, headerSort: false,
                    formatter: c => {
                        const v = c.getValue();
                        return v ? `<img src="${v}" alt="Product" style="width:50px;height:50px;object-fit:cover;">` : '';
                    }},
                { title: 'SKU', field: '(Child) sku', frozen: true, width: 250, headerFilter: 'input',
                    cssClass: 'text-primary fw-bold' },
                { title: 'INV', field: 'INV', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'Dil', field: 'Dil', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const row = c.getRow().getData();
                        const inv = parseFloat(row.INV) || 0;
                        const ovL30 = parseFloat(row.L30) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ovL30 / inv) * 100;
                        let color = '';
                        if (dil < 16.66) color = '#a00211';
                        else if (dil < 25) color = '#ffc107';
                        else if (dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }},
                { title: 'TD L30', field: 'TD L30', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'TD L60', field: 'TD L60', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'TD Stock', field: 'TD Stock', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => {
                        const inv = parseFloat(c.getRow().getData().INV) || 0;
                        const td = parseFloat(c.getValue()) || 0;
                        const diff = Math.abs(inv - td);
                        const color = diff > TD_MAP_TOLERANCE ? '#dc3545' : '#28a745';
                        return `<span style="color:${color};font-weight:600;">${td}</span>`;
                    }},
                { title: 'Missing L', field: 'Missing', hozAlign: 'center', width: 70,
                    formatter: c => c.getValue() === 'M'
                        ? '<span style="color:#dc3545;font-weight:bold;background:#ffe6e6;padding:2px 6px;border-radius:3px;">M</span>'
                        : '' },
                { title: 'MAP', field: 'MAP', hozAlign: 'center', width: 90,
                    formatter: c => {
                        const v = c.getValue() || '';
                        if (v === 'Map') {
                            return '<span style="color:#28a745;font-weight:bold;background:#d4edda;padding:2px 6px;border-radius:3px;">MAP</span>';
                        }
                        if (v.includes('N Map|')) {
                            const diff = v.split('|')[1];
                            return `<span style="color:#dc3545;font-weight:bold;background:#f8d7da;padding:2px 6px;border-radius:3px;">N MP (${diff})</span>`;
                        }
                        return '';
                    }},
                { title: 'NR/REQ', field: 'nr_req', hozAlign: 'center', width: 60, headerSort: false,
                    formatter: c => {
                        let value = c.getValue();
                        if (!value || String(value).trim() === '') value = 'REQ';
                        return `<select class="form-select form-select-sm nr-req-dropdown"
                            style="border:1px solid #ddd;text-align:center;cursor:pointer;padding:2px 4px;font-size:16px;width:50px;height:28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: (e) => e.stopPropagation() },
                { title: 'Prc', field: 'TD Price', hozAlign: 'center', width: 70, sorter: 'number',
                    formatter: c => {
                        const v = parseFloat(c.getValue()) || 0;
                        if (v === 0) {
                            return '<span style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left:4px;"></i></span>';
                        }
                        return `$${v.toFixed(2)}`;
                    }},
                { title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'PFT%', field: 'PFT %', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'ROI%', field: 'ROI%', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 50) color = '#a00211';
                        else if (percent < 100) color = '#ffc107';
                        else if (percent < 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'Profit', field: 'Profit', hozAlign: 'center', width: 70, sorter: 'number', visible: false,
                    formatter: c => {
                        const v = parseFloat(c.getValue()) || 0;
                        const color = v >= 0 ? '#28a745' : '#a00211';
                        return `<span style="color:${color};font-weight:600;">$${v.toFixed(2)}</span>`;
                    }},
                { title: 'LP', field: 'LP_productmaster', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => '$' + (parseFloat(c.getValue()) || 0).toFixed(2) },
                { title: 'Ship', field: 'Ship_productmaster', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => '$' + (parseFloat(c.getValue()) || 0).toFixed(2) },
                { title: 'TDID', field: 'TDID', width: 120, visible: false },
                { title: 'State', field: 'listing_state', width: 70, visible: false },
            ],
            ajaxResponse: function(url, params, response) {
                return Array.isArray(response) ? response : (response.data || []);
            },
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyFilters();
                updateSummary();
            }, 100);
        });
        table.on('renderComplete', function() {
            setTimeout(updateSummary, 100);
        });
        table.on('dataFiltered', updateSummary);

        $('#inventory-filter, #td-stock-filter, #nrl-filter, #gpft-filter').on('change', applyFilters);
        $('#sku-search').on('keyup', function() {
            table.setFilter('(Child) sku', 'like', this.value);
            updateSummary();
        });

        $('#zero-sold-badge').on('click', function() { zeroSoldFilter = !zeroSoldFilter; moreSoldFilter = false; applyFilters(); });
        $('#more-sold-badge').on('click', function() { moreSoldFilter = !moreSoldFilter; zeroSoldFilter = false; applyFilters(); });
        $('#missing-badge').on('click', function() { missingFilter = !missingFilter; mapFilter = nmapFilter = false; applyFilters(); });
        $('#map-badge').on('click', function() { mapFilter = !mapFilter; missingFilter = nmapFilter = false; applyFilters(); });
        $('#nmap-badge').on('click', function() { nmapFilter = !nmapFilter; missingFilter = mapFilter = false; applyFilters(); });

        $('#export-btn').on('click', () => table.download('csv', 'topdawg_pricing.csv'));
    });
</script>
@endsection
