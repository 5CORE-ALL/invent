@extends('layouts.vertical', ['title' => 'Movement Analysis', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Header styling */
        .tabulator .tabulator-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            padding: 12px 8px;
        }
        .tabulator-row {
            background-color: #ffffff !important; /* default white for all rows */
        }
        /* Cell styling */
        .tabulator .tabulator-cell {
            text-align: center;
            padding: 12px 8px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Row hover effect */
        .tabulator-row:hover {
            background-color: rgba(0,0,0,.075) !important;
        }
        
        /* Parent row styling */
        .parent-row {
            background-color: #DFF0FF !important;
            font-weight: 600;
        }
        
        /* Pagination styling */
        .tabulator-footer {
            background-color: #f8f9fa;
            border-top: 2px solid #dee2e6;
        }

        .ma-dil-pies {
            display: flex;
            gap: 10px;
            margin: 0 0 14px;
            flex-wrap: wrap;
        }
        .ma-dil-pie-wrap {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            flex: 1 1 320px;
            min-width: 280px;
            max-width: 560px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        .ma-dil-pie-canvas-wrap {
            width: 140px;
            height: 140px;
            flex: 0 0 140px;
        }
        .ma-dil-pie-legend {
            flex: 1 1 auto;
            min-width: 0;
            max-height: 240px;
            overflow-y: auto;
        }
        .ma-dil-pie-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            line-height: 1.3;
            padding: 1px 0;
        }
        .ma-dil-pie-row.is-filterable { cursor: pointer; }
        .ma-dil-pie-row.is-filterable:hover { background: #eef2ff; border-radius: 4px; }
        .ma-dil-pie-row.is-active { background: #e0e7ff; border-radius: 4px; }
        .ma-dil-pie-swatch {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex: 0 0 8px;
        }
        .ma-dil-pie-name { flex: 1 1 auto; font-weight: 600; color: #334155; }
        .ma-dil-pie-count { font-weight: 700; min-width: 24px; text-align: right; }
        .ma-dil-pie-pct { color: #64748b; min-width: 24px; text-align: right; }
        .ma-dil-hist-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            padding: 0;
            cursor: pointer;
            flex: 0 0 8px;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        .ma-dil-hist-dot:hover { transform: scale(1.35); }
        .ma-dil-hist-wrap {
            display: none;
            margin: 0 0 14px;
            padding: 6px 8px 4px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            max-width: 560px;
        }
        .ma-dil-hist-wrap.is-open { display: block; }
        .ma-dil-hist-canvas-wrap { height: 160px; }
        .ma-dil-pie-canvas-wrap canvas,
        .ma-dil-hist-canvas-wrap canvas {
            width: 100% !important;
            height: 100% !important;
        }
    </style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Movement Analysis', 'sub_title' => 'Movement Analysis'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Movement Analysis</h4>
                </div>

                <div class="row mb-4 d-flex align-items-center justify-content-between">
                    <div class="col-md-4">
                        <div class="btn-group time-navigation-group" role="group">
                            <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-2" title="Previous parent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-2" style="display: none;" title="Pause">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-2" title="Play">
                                <i class="fas fa-play"></i>
                            </button>
                            <button id="play-forward" class="btn btn-light rounded-circle shadow-sm" title="Next parent">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="search-input" class="form-label fw-semibold">Search</label>
                        <input type="text" id="search-input" class="form-control" placeholder="Search suppliers...">
                    </div>
                </div>

                <div class="ma-dil-pies">
                    <div class="ma-dil-pie-wrap">
                        <div class="ma-dil-pie-canvas-wrap">
                            <canvas id="ma-dil-pie"></canvas>
                        </div>
                        <div class="ma-dil-pie-legend" id="ma-dil-legend"></div>
                    </div>
                </div>
                <div class="ma-dil-hist-wrap" id="ma-dil-hist-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small fw-semibold" id="ma-dil-hist-title">Dil history</span>
                        <button type="button" class="btn-close" id="ma-dil-hist-close" aria-label="Close history" style="font-size:10px;"></button>
                    </div>
                    <div class="ma-dil-hist-canvas-wrap">
                        <canvas id="ma-dil-hist"></canvas>
                    </div>
                </div>

                <div id="movement-tabulator"></div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Data Modal -->
<div class="modal fade" id="monthlyModal" tabindex="-1" aria-labelledby="monthlyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="monthlyModalLabel">Monthly Data for SKU: <span id="modalSku"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="monthlyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="graph-tab" data-bs-toggle="tab" data-bs-target="#graph" type="button" role="tab" aria-controls="graph" aria-selected="true">Graph</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" type="button" role="tab" aria-controls="data" aria-selected="false"><i class="fas fa-calendar"></i> Data</button>
                    </li>
                </ul>
                <div class="tab-content" id="monthlyTabContent">
                    <div class="tab-pane fade show active" id="graph" role="tabpanel" aria-labelledby="graph-tab">
                        <canvas id="monthlyChart" width="400" height="200"></canvas>
                    </div>
                    <div class="tab-pane fade" id="data" role="tabpanel" aria-labelledby="data-tab">
                        <div id="monthlyDataContainer" class="row">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const groupedSkuData = @json($groupedDataJson);
    let table;
    let monthlyChart;
    let maDilPieChart = null;
    let maDilHistChart = null;
    let maDilLiveCounts = {};
    let maDilActiveBand = null;
    let maSnapshotHistory = true;
    const MA_DIL_HIST_KEY = 'movement_analysis_dil_hist';
    const MA_DIL_SLAB_COLORS = [
        '#6f42c1', '#3b82f6', '#14b8a6', '#22c55e', '#84cc16',
        '#eab308', '#f59e0b', '#ea580c', '#dc3545', '#e83e8c',
        '#7c3aed',
    ];
    const MA_DIL_SLABS = [
        { key: '0-5', label: '0–5%', min: 0, max: 5 },
        { key: '5-10', label: '5–10%', min: 5, max: 10 },
        { key: '10-15', label: '10–15%', min: 10, max: 15 },
        { key: '15-20', label: '15–20%', min: 15, max: 20 },
        { key: '20-25', label: '20–25%', min: 20, max: 25 },
        { key: '25-30', label: '25–30%', min: 25, max: 30 },
        { key: '30-35', label: '30–35%', min: 30, max: 35 },
        { key: '35-40', label: '35–40%', min: 35, max: 40 },
        { key: '40-45', label: '40–45%', min: 40, max: 45 },
        { key: '45-50', label: '45–50%', min: 45, max: 50 },
        { key: '50+', label: '≥50%', min: 50, max: Infinity },
    ].map(function(s, i) {
        return Object.assign({}, s, { color: MA_DIL_SLAB_COLORS[i % MA_DIL_SLAB_COLORS.length] });
    });

    function maIsParentRow(row) {
        return String((row && row.sku) || '').toUpperCase().startsWith('PARENT ');
    }
    function maRowDil(row) {
        if (!row || maIsParentRow(row)) return 0;
        const stored = parseFloat(row.dil);
        if (isFinite(stored)) return stored;
        const inv = parseFloat(row.INV) || 0;
        const l30 = parseFloat(row.L30) || 0;
        return inv > 0 ? (l30 / inv) * 100 : 0;
    }
    function maDilColorStyle(dil) {
        if (!(dil > 0)) return 'color:#6c757d;font-weight:700;';
        if (dil < 25) return 'color:#a00211;font-weight:700;';
        if (dil < 50) return 'color:#28a745;font-weight:700;';
        return 'color:#e83e8c;font-weight:700;';
    }
    function maDilSlabFor(dil) {
        for (let i = 0; i < MA_DIL_SLABS.length; i++) {
            const s = MA_DIL_SLABS[i];
            const last = i === MA_DIL_SLABS.length - 1;
            if (dil >= s.min && (last ? dil >= s.min : dil < s.max)) return s;
        }
        return MA_DIL_SLABS[0];
    }
    function maTodayKey() {
        try {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
        } catch (e) {
            return new Date().toISOString().slice(0, 10);
        }
    }
    function maSnapDilHistory(counts) {
        try {
            const today = maTodayKey();
            let hist = {};
            try { hist = JSON.parse(localStorage.getItem(MA_DIL_HIST_KEY) || '{}') || {}; } catch (e) { hist = {}; }
            hist[today] = counts;
            const keys = Object.keys(hist).sort();
            while (keys.length > 90) delete hist[keys.shift()];
            localStorage.setItem(MA_DIL_HIST_KEY, JSON.stringify(hist));
        } catch (e) { /* ignore */ }
    }
    function maLocalDilHistory() {
        try {
            const hist = JSON.parse(localStorage.getItem(MA_DIL_HIST_KEY) || '{}') || {};
            return Object.keys(hist).sort().map(function(date) {
                return Object.assign({ date: date, label: date.slice(5) }, hist[date] || {});
            });
        } catch (e) {
            return [];
        }
    }
    function maCollectDilCounts(rows) {
        const counts = {};
        MA_DIL_SLABS.forEach(function(s) { counts[s.key] = 0; });
        (rows || []).forEach(function(row) {
            if (maIsParentRow(row)) return;
            const slab = maDilSlabFor(maRowDil(row));
            counts[slab.key] = (counts[slab.key] || 0) + 1;
        });
        return counts;
    }
    function maHistDotHtml(key, color, label) {
        return '<button type="button" class="ma-dil-hist-dot" data-band="' + String(key).replace(/"/g, '&quot;') + '" '
            + 'style="background:' + color + ';" title="' + String(label).replace(/"/g, '&quot;') + ' daily history"></button>';
    }
    function maDilLegendHtml(counts) {
        const total = MA_DIL_SLABS.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
        return '<div class="ma-dil-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
            + '<span class="ma-dil-pie-swatch" style="visibility:hidden;"></span>'
            + '<span class="ma-dil-pie-name">Dil</span>'
            + '<span class="ma-dil-pie-count">count</span>'
            + '<span class="ma-dil-pie-pct">of total</span>'
            + '<span class="ma-dil-hist-dot" style="visibility:hidden;"></span>'
            + '</div>'
            + MA_DIL_SLABS.map(function(s) {
                const n = counts[s.key] || 0;
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                const active = maDilActiveBand === s.key ? ' is-active' : '';
                return '<div class="ma-dil-pie-row is-filterable' + active + '" data-band="' + s.key + '">'
                    + '<span class="ma-dil-pie-swatch" style="background:' + s.color + ';"></span>'
                    + '<span class="ma-dil-pie-name">' + s.label + '</span>'
                    + '<span class="ma-dil-pie-count">' + n + '</span>'
                    + '<span class="ma-dil-pie-pct" title="' + pct + ' of total">' + pct + '</span>'
                    + maHistDotHtml(s.key, s.color, s.label)
                    + '</div>';
            }).join('');
    }
    function maDrawDilPie(counts) {
        const canvas = document.getElementById('ma-dil-pie');
        if (!canvas || typeof Chart === 'undefined') return;
        const total = MA_DIL_SLABS.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
        if (maDilPieChart) {
            maDilPieChart.destroy();
            maDilPieChart = null;
        }
        maDilPieChart = new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: MA_DIL_SLABS.map(function(s) { return s.label; }),
                datasets: [{
                    data: MA_DIL_SLABS.map(function(s) { return counts[s.key] || 0; }),
                    backgroundColor: MA_DIL_SLABS.map(function(s) { return s.color; }),
                    borderColor: '#fff',
                    borderWidth: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const n = Number(ctx.raw) || 0;
                                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                                return ' ' + n + '  ·  ' + pct + ' of total';
                            },
                        },
                    },
                },
                onClick: function(_evt, els) {
                    if (!els || !els.length) {
                        maSetDilBandFilter(null);
                        return;
                    }
                    const idx = els[0].index;
                    const slab = MA_DIL_SLABS[idx];
                    if (slab) maSetDilBandFilter(slab.key);
                },
            },
        });
    }
    function maRenderDilPie(rows, snapshot) {
        const counts = maCollectDilCounts(rows);
        maDilLiveCounts = counts;
        if (snapshot) maSnapDilHistory(counts);
        const legend = document.getElementById('ma-dil-legend');
        if (legend) legend.innerHTML = maDilLegendHtml(counts);
        maDrawDilPie(counts);
    }
    function maApplyStackedFilters() {
        if (!table) return;
        const keyword = String($('#search-input').val() || '').toLowerCase().trim();
        const filters = [];
        if (keyword) {
            filters.push([
                { field: 'parent', type: 'like', value: keyword },
                { field: 'sku', type: 'like', value: keyword },
            ]);
        }
        if (maDilActiveBand) {
            filters.push(function(data) {
                if (maIsParentRow(data)) return false;
                return maDilSlabFor(maRowDil(data)).key === maDilActiveBand;
            });
        }
        if (!filters.length) {
            table.clearFilter();
            return;
        }
        table.setFilter(filters);
    }
    function maSetDilBandFilter(band) {
        maDilActiveBand = (band && maDilActiveBand === band) ? null : (band || null);
        maApplyStackedFilters();
        const legend = document.getElementById('ma-dil-legend');
        if (legend) legend.innerHTML = maDilLegendHtml(maDilLiveCounts);
    }
    function maDrawDilHist(band) {
        const spec = MA_DIL_SLABS.find(function(s) { return s.key === band; })
            || { key: band, label: band, color: '#6f42c1' };
        $('#ma-dil-hist-title').text('Dil ' + spec.label + ' count');
        $('#ma-dil-hist-wrap').addClass('is-open');
        const rows = maLocalDilHistory().slice();
        const today = maTodayKey();
        const rec = Object.assign({ date: today, label: today.slice(5) }, maDilLiveCounts);
        const last = rows[rows.length - 1];
        if (last && last.date === today) Object.assign(last, rec);
        else rows.push(rec);
        const canvas = document.getElementById('ma-dil-hist');
        if (!canvas || typeof Chart === 'undefined') return;
        if (maDilHistChart) {
            maDilHistChart.destroy();
            maDilHistChart = null;
        }
        maDilHistChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: rows.map(function(r) { return r.label || r.date; }),
                datasets: [{
                    data: rows.map(function(r) { return Number(r[band]) || 0; }),
                    borderColor: spec.color,
                    backgroundColor: spec.color + '22',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 1.5,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: spec.color,
                    pointBorderColor: spec.color,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { font: { size: 9 }, precision: 0 } },
                    x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } },
                },
            },
        });
    }
    function decorateMovementRows(rows) {
        return (rows || []).map(function(row) {
            const copy = Object.assign({}, row);
            copy.dil = maRowDil(copy);
            return copy;
        });
    }

    function buildTabulator(data) {
        const rows = decorateMovementRows(data);
        if (table) {
            try { table.destroy(); } catch (e) { /* ignore */ }
            table = null;
        }
        table = new Tabulator("#movement-tabulator", {
            height: "500px",
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 50,
            data: rows,
            columns: [
                // {title: "#", formatter: "rownum", width: 60},
                {title: "Parent", field: "parent"},
                {title: "SKU", field: "sku"},
                {title: "INV", field: "INV", hozAlign: "right"},
                // {title: "Total Month", field: "total_months"},
                {title: "Avg M", field: "monthly_average"},
                {title: "MOQ", field: "moq", headerTooltip: "Minimum Order Quantity"},
                {title: "MSL", field: "msl"},
                {
                    title: "TOTAL INV AMT", 
                    field: "lp", 
                    hozAlign: "right",
                    formatter: function(cell) {
                        let inv = cell.getRow().getData().INV || 0;
                        let lp = cell.getValue() || 0;
                        return (inv * lp).toFixed(2);
                    }
                },
                {title: "OV L30", field: "L30", hozAlign: "right"},
                {
                    title: "Dil",
                    field: "dil",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    headerTooltip: "OV L30 ÷ INV. Red <25% · Green 25–50% · Pink 50%+.",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (maIsParentRow(row)) return '';
                        const dil = maRowDil(row);
                        return '<span style="' + maDilColorStyle(dil) + '">' + Math.round(dil) + '%</span>';
                    }
                },
                ...["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"].map(m => ({title: m.toUpperCase(), field: `months.${m}`})),
                {title: "Total", field: "total"},
                {
                    title: "Monthly Total", 
                    field: "monthly_average",
                    hozAlign: "right",
                    formatter: function(cell) {
                        let monthly = cell.getValue() || 0;
                        let lp = cell.getRow().getData().lp || 0;
                        return (monthly )
                        return (monthly * lp).toFixed(0);
                    }
                },
                {
                    title: "TOTAL MSL AMT", 
                    field: "msl",
                    hozAlign: "right",
                    formatter: function(cell) {
                        let msl = cell.getValue() || 0;
                        let lp = cell.getRow().getData().lp || 0;
                        return (msl * lp).toFixed(2);
                    }
                },
                {
                    title: "S-MSL", field: "s_msl", editor: "input",
                    cellEdited: function(cell) {
                        const data = cell.getRow().getData();
                        $.post('/update-smsl', {
                            sku: data.sku,
                            parent: data.parent,
                            column: 's_msl',
                            value: cell.getValue(),
                            _token: '{{ csrf_token() }}'
                        });
                    }
                },
                {
                    title: "Details", 
                    formatter: function(cell, formatterParams, onRendered) {
                        return '<button class="btn btn-sm btn-primary">View Monthly</button>';
                    }, 
                    cellClick: function(e, cell) {
                        openModal(cell.getRow().getData());
                    },
                    width: 120
                }
            ],
            rowFormatter: function(row) {
                if ((row.getData().sku || '').toUpperCase().startsWith('PARENT ')) {
                    row.getElement().classList.add("parent-row");
                }
            },
            tableBuilt: function() {
                maApplyStackedFilters();
            },
        });
        maRenderDilPie(rows, maSnapshotHistory);
        maSnapshotHistory = false;
    }

    function openModal(data) {
        const months = data.months || {};
        const container = $('#monthlyDataContainer');
        container.empty();
        $('#modalSku').text(data.sku || 'N/A');
        const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const values = labels.map(month => months[month] || 0);
        
        // Create cards for each month
        labels.forEach((month, index) => {
            const value = values[index];
            const card = `
                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title text-primary">${month}</h6>
                            <h4 class="card-text font-weight-bold">${value}</h4>
                        </div>
                    </div>
                </div>
            `;
            container.append(card);
        });
        
        // Destroy previous chart if exists
        if (monthlyChart) {
            monthlyChart.destroy();
        }
        
        // Create line chart
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monthly Values',
                    data: values,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        $('#monthlyModal').modal('show');
    }

    function fetchMovementData() {
        maSnapshotHistory = true;
        $.get('/movement-analysis-data-view', function(res) {
            let tableData = res.data ?? res;
            buildTabulator(tableData);
        });
    }

    $(document).ready(function() {
        fetchMovementData();

        $('#search-input').on('input', function () {
            maApplyStackedFilters();
        });

        $(document).on('click', '#ma-dil-legend .ma-dil-hist-dot', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const band = $(this).data('band');
            if (band) maDrawDilHist(String(band));
        });
        $(document).on('click', '#ma-dil-legend .ma-dil-pie-row.is-filterable', function (e) {
            if ($(e.target).closest('.ma-dil-hist-dot').length) return;
            maSetDilBandFilter(String($(this).data('band') || ''));
        });
        $('#ma-dil-hist-close').on('click', function () {
            $('#ma-dil-hist-wrap').removeClass('is-open');
        });

        // Playback controls (if needed)
        const parentKeys = Object.keys(groupedSkuData);
        let currentIndex = 0;
        let isPlaying = false;

        function renderGroup(parentKey) {
            maSnapshotHistory = false;
            let rows = groupedSkuData[parentKey] || [];
            buildTabulator(rows);
        }

        $('#play-auto').click(() => {
            isPlaying = true;
            currentIndex = 0;
            renderGroup(parentKeys[currentIndex]);
            $('#play-pause').show();
            $('#play-auto').hide();
        });

        $('#play-forward').click(() => {
            if (!isPlaying) return;
            currentIndex = (currentIndex + 1) % parentKeys.length;
            renderGroup(parentKeys[currentIndex]);
        });

        $('#play-backward').click(() => {
            if (!isPlaying) return;
            currentIndex = (currentIndex - 1 + parentKeys.length) % parentKeys.length;
            renderGroup(parentKeys[currentIndex]);
        });

        $('#play-pause').click(() => {
            isPlaying = false;
            fetchMovementData();
            $('#play-auto').show();
            $('#play-pause').hide();
        });
    });
</script>
@endsection
