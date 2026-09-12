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
            max-height: 180px;
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
        .ma-dil-pie-count { font-weight: 700; min-width: 48px; text-align: right; }
        .ma-dil-pie-pct { color: #64748b; min-width: 36px; text-align: right; }
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
        #maDilHistModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #maDilHistModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #maDilHistModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        .ma-dil-hist-body {
            height: 28vh;
            min-height: 200px;
            display: flex;
            align-items: stretch;
        }
        .ma-dil-hist-canvas-wrap {
            flex: 1;
            min-width: 0;
            position: relative;
        }
        .ma-dil-hist-ref {
            width: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
            padding: 6px 8px;
            border-left: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0 4px 4px 0;
            text-align: center;
        }
        .ma-dil-hist-ref .ma-dil-hist-ref-label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        .ma-dil-hist-ref .ma-dil-hist-ref-val {
            font-size: 13px;
            font-weight: 700;
        }
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
                    <div class="ma-dil-pie-wrap">
                        <div class="ma-dil-pie-canvas-wrap">
                            <canvas id="ma-dil-amz-pie"></canvas>
                        </div>
                        <div class="ma-dil-pie-legend" id="ma-dil-amz-legend"></div>
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

<div class="modal fade p-0" id="maDilHistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow:hidden;">
            <div class="modal-header bg-info text-white py-1 px-3">
                <h6 class="modal-title mb-0" style="font-size:13px;">
                    <i class="fas fa-chart-area me-1"></i>
                    <span id="ma-dil-hist-title">Dil history</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2">
                <div class="ma-dil-hist-body">
                    <div class="ma-dil-hist-canvas-wrap">
                        <canvas id="ma-dil-hist"></canvas>
                    </div>
                    <div class="ma-dil-hist-ref">
                        <div>
                            <div class="ma-dil-hist-ref-label" style="color:#dc3545;">Highest</div>
                            <div class="ma-dil-hist-ref-val" id="ma-dil-hist-highest" style="color:#dc3545;">-</div>
                        </div>
                        <div style="border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                            <div class="ma-dil-hist-ref-label" style="color:#6c757d;">Median</div>
                            <div class="ma-dil-hist-ref-val" id="ma-dil-hist-median" style="color:#6c757d;">-</div>
                        </div>
                        <div>
                            <div class="ma-dil-hist-ref-label" style="color:#198754;">Lowest</div>
                            <div class="ma-dil-hist-ref-val" id="ma-dil-hist-lowest" style="color:#198754;">-</div>
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
    let maDilAmzPieChart = null;
    let maDilHistChart = null;
    let maDilLiveCounts = {};
    let maDilLiveAmzValues = {};
    let maDilActiveBand = null;
    let maSnapshotHistory = true;
    const MA_DIL_HIST_KEY = 'movement_analysis_dil_hist';
    const MA_DIL_AMZ_HIST_KEY = 'movement_analysis_dil_amz_hist';
    const MA_DIL_SLABS = [
        { key: '0', label: '0%', min: 0, max: 0.1, color: '#dc3545' },
        { key: '0.1-25', label: '0–25%', min: 0.1, max: 25, color: '#ffc107' },
        { key: '25-50', label: '25–50%', min: 25, max: 50, color: '#28a745' },
        { key: '50-100', label: '50–100%', min: 50, max: 100, color: '#e83e8c' },
        { key: 'gt-100', label: '>100%', min: 100, max: Infinity, color: '#4e0dab' },
    ];

    function maIsParentRow(row) {
        return String((row && row.sku) || '').toUpperCase().startsWith('PARENT ');
    }
    function maRowInv(row) {
        return parseFloat(row && row.INV) || 0;
    }
    function maRowAmzValue(row) {
        const stored = parseFloat(row && row.amz_value);
        if (isFinite(stored) && stored > 0) return stored;
        const inv = maRowInv(row);
        const price = parseFloat(row && row.amz_price) || 0;
        return inv > 0 ? inv * price : 0;
    }
    function maMoneyCompact(n) {
        const v = Math.round(Number(n) || 0);
        const abs = Math.abs(v);
        if (abs >= 1000000) return '$' + (v / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (abs >= 1000) return '$' + Math.round(v / 1000) + 'K';
        return '$' + v.toLocaleString('en-US');
    }
    function maRowDil(row) {
        if (!row || maIsParentRow(row)) return 0;
        const inv = maRowInv(row);
        if (inv <= 0) return 0;
        const stored = parseFloat(row.dil);
        if (isFinite(stored)) return stored;
        const l30 = parseFloat(row.L30) || 0;
        return (l30 / inv) * 100;
    }
    function maDilColorStyle(dil) {
        if (!(dil > 0)) return 'color:#dc3545;font-weight:700;';
        if (dil <= 25) return 'color:#d39e00;font-weight:700;';
        if (dil < 50) return 'color:#28a745;font-weight:700;';
        if (dil <= 100) return 'color:#e83e8c;font-weight:700;';
        return 'color:#4e0dab;font-weight:700;';
    }
    function maDilSlabFor(dil) {
        const n = Number(dil) || 0;
        if (n > 100) return MA_DIL_SLABS[4];
        if (n >= 50) return MA_DIL_SLABS[3];
        if (n > 25) return MA_DIL_SLABS[2];
        if (n >= 0.1) return MA_DIL_SLABS[1];
        return MA_DIL_SLABS[0];
    }
    function maTodayKey() {
        try {
            return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date());
        } catch (e) {
            return new Date().toISOString().slice(0, 10);
        }
    }
    function maSnapDilHistory(storeKey, counts) {
        try {
            const today = maTodayKey();
            let hist = {};
            try { hist = JSON.parse(localStorage.getItem(storeKey) || '{}') || {}; } catch (e) { hist = {}; }
            hist[today] = counts;
            const keys = Object.keys(hist).sort();
            while (keys.length > 90) delete hist[keys.shift()];
            localStorage.setItem(storeKey, JSON.stringify(hist));
        } catch (e) { /* ignore */ }
    }
    function maLocalDilHistory(storeKey) {
        try {
            const hist = JSON.parse(localStorage.getItem(storeKey || MA_DIL_HIST_KEY) || '{}') || {};
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
            if (maRowInv(row) <= 0) return;
            const slab = maDilSlabFor(maRowDil(row));
            counts[slab.key] = (counts[slab.key] || 0) + 1;
        });
        return counts;
    }
    function maCollectDilAmzValues(rows) {
        const values = {};
        MA_DIL_SLABS.forEach(function(s) { values[s.key] = 0; });
        (rows || []).forEach(function(row) {
            if (maIsParentRow(row)) return;
            if (maRowInv(row) <= 0) return;
            const slab = maDilSlabFor(maRowDil(row));
            values[slab.key] = (values[slab.key] || 0) + maRowAmzValue(row);
        });
        return values;
    }
    function maHistDotHtml(key, color, label, chart) {
        return '<button type="button" class="ma-dil-hist-dot" data-chart="' + String(chart || 'count').replace(/"/g, '&quot;') + '" '
            + 'data-band="' + String(key).replace(/"/g, '&quot;') + '" '
            + 'style="background:' + color + ';" title="' + String(label).replace(/"/g, '&quot;') + ' daily history"></button>';
    }
    function maDilLegendHtml(title, counts, chart, money) {
        const total = MA_DIL_SLABS.reduce(function(sum, s) { return sum + (counts[s.key] || 0); }, 0);
        const fmt = money
            ? function(n) { return maMoneyCompact(n); }
            : function(n) { return String(Math.round(Number(n) || 0)); };
        return '<div class="ma-dil-pie-row" style="color:#94a3b8;font-size:10px;font-weight:600;">'
            + '<span class="ma-dil-pie-swatch" style="visibility:hidden;"></span>'
            + '<span class="ma-dil-pie-name">' + title + '</span>'
            + '<span class="ma-dil-pie-count">' + (money ? '$' : 'count') + '</span>'
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
                    + '<span class="ma-dil-pie-count">' + fmt(n) + '</span>'
                    + '<span class="ma-dil-pie-pct" title="' + pct + '% of total">' + pct + '%</span>'
                    + maHistDotHtml(s.key, s.color, s.label, chart)
                    + '</div>';
            }).join('')
            + '<div class="ma-dil-pie-row" style="border-top:1px dashed #cbd5e1;margin-top:2px;padding-top:3px;">'
            + '<span class="ma-dil-pie-swatch" style="visibility:hidden;"></span>'
            + '<span class="ma-dil-pie-name">Total</span>'
            + '<span class="ma-dil-pie-count">' + fmt(total) + '</span>'
            + '<span class="ma-dil-pie-pct">100%</span>'
            + '<span class="ma-dil-hist-dot" style="visibility:hidden;"></span>'
            + '</div>';
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
                                return ' ' + n + '  ·  ' + pct + '% of total';
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
    function maDrawDilAmzPie(values) {
        const canvas = document.getElementById('ma-dil-amz-pie');
        if (!canvas || typeof Chart === 'undefined') return;
        const total = MA_DIL_SLABS.reduce(function(sum, s) { return sum + (values[s.key] || 0); }, 0);
        if (maDilAmzPieChart) {
            maDilAmzPieChart.destroy();
            maDilAmzPieChart = null;
        }
        maDilAmzPieChart = new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: MA_DIL_SLABS.map(function(s) { return s.label; }),
                datasets: [{
                    data: MA_DIL_SLABS.map(function(s) { return values[s.key] || 0; }),
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
                                return ' ' + maMoneyCompact(n) + '  ·  ' + pct + '% of total';
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
    function maRefreshDilLegends() {
        const countLegend = document.getElementById('ma-dil-legend');
        if (countLegend) countLegend.innerHTML = maDilLegendHtml('Dil', maDilLiveCounts, 'count', false);
        const amzLegend = document.getElementById('ma-dil-amz-legend');
        if (amzLegend) amzLegend.innerHTML = maDilLegendHtml('Amz $', maDilLiveAmzValues, 'amz', true);
    }
    function maRenderDilPie(rows, snapshot) {
        const counts = maCollectDilCounts(rows);
        const amzValues = maCollectDilAmzValues(rows);
        maDilLiveCounts = counts;
        maDilLiveAmzValues = amzValues;
        if (snapshot) {
            maSnapDilHistory(MA_DIL_HIST_KEY, counts);
            maSnapDilHistory(MA_DIL_AMZ_HIST_KEY, amzValues);
        }
        maRefreshDilLegends();
        maDrawDilPie(counts);
        maDrawDilAmzPie(amzValues);
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
                if (maRowInv(data) <= 0) return false;
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
        maRefreshDilLegends();
    }
    function maDilHistDotColors(values) {
        const gray = '#6c757d';
        const green = '#28a745';
        const red = '#dc3545';
        return values.map(function(v, i) {
            if (i === 0) return gray;
            const prev = values[i - 1];
            if (Math.abs(v - prev) <= 0.01) return gray;
            return v > prev ? green : red;
        });
    }
    let maDilHistBand = null;
    let maDilHistKind = 'count';
    function maDrawDilHist(band, kind) {
        maDilHistBand = band;
        maDilHistKind = kind === 'amz' ? 'amz' : 'count';
        const spec = MA_DIL_SLABS.find(function(s) { return s.key === band; })
            || { key: band, label: band, color: '#6f42c1' };
        $('#ma-dil-hist-title').text(
            maDilHistKind === 'amz'
                ? ('Dil ' + spec.label + ' Amz $')
                : ('Dil ' + spec.label + ' count')
        );
        const modalEl = document.getElementById('maDilHistModal');
        if (modalEl && $(modalEl).hasClass('show')) {
            maPaintDilHistChart(band);
            return;
        }
        if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            $('#maDilHistModal').modal('show');
        }
    }
    function maPaintDilHistChart(band) {
        band = band || maDilHistBand;
        if (!band) return;
        const money = maDilHistKind === 'amz';
        const live = money ? maDilLiveAmzValues : maDilLiveCounts;
        const storeKey = money ? MA_DIL_AMZ_HIST_KEY : MA_DIL_HIST_KEY;
        const rows = maLocalDilHistory(storeKey).slice();
        const today = maTodayKey();
        const rec = Object.assign({ date: today, label: today.slice(5) }, live);
        const last = rows[rows.length - 1];
        if (last && last.date === today) Object.assign(last, rec);
        else rows.push(rec);
        const canvas = document.getElementById('ma-dil-hist');
        if (!canvas || typeof Chart === 'undefined') return;
        if (maDilHistChart) {
            maDilHistChart.destroy();
            maDilHistChart = null;
        }
        const labels = rows.map(function(r) { return r.label || r.date; });
        const values = rows.map(function(r) { return Number(r[band]) || 0; });
        const fmtVal = money
            ? function(v) { return maMoneyCompact(v); }
            : function(v) { return Math.round(Number(v) || 0).toLocaleString('en-US'); };
        const dataMin = values.length ? Math.min.apply(null, values) : 0;
        const dataMax = values.length ? Math.max.apply(null, values) : 0;
        const sorted = values.slice().sort(function(a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        const median = !sorted.length ? 0
            : (sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2);
        const range = dataMax - dataMin || 1;
        const yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
        const yMin = Math.max(0, dataMin - range * 0.12);
        const yMax = dataMax + yPad;
        const dotColors = maDilHistDotColors(values);
        const labelColors = dotColors.slice();
        const refGray = '#6c757d';
        let maxIdx = 0;
        let minIdx = 0;
        values.forEach(function(v, i) {
            if (v >= values[maxIdx]) maxIdx = i;
            if (v <= values[minIdx]) minIdx = i;
        });
        const highestEl = document.getElementById('ma-dil-hist-highest');
        const medianEl = document.getElementById('ma-dil-hist-median');
        const lowestEl = document.getElementById('ma-dil-hist-lowest');
        if (highestEl) {
            highestEl.textContent = fmtVal(dataMax);
            highestEl.style.color = dotColors[maxIdx] || refGray;
        }
        if (medianEl) {
            medianEl.textContent = fmtVal(median);
            medianEl.style.color = refGray;
        }
        if (lowestEl) {
            lowestEl.textContent = fmtVal(dataMin);
            lowestEl.style.color = dotColors[minIdx] || refGray;
        }
        const medianLinePlugin = {
            id: 'maDilMedianLine',
            afterDraw: function(chart) {
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const ctx = chart.ctx;
                const yPixel = yScale.getPixelForValue(median);
                ctx.save();
                ctx.setLineDash([6, 4]);
                ctx.strokeStyle = '#6c757d';
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.moveTo(xScale.left, yPixel);
                ctx.lineTo(xScale.right, yPixel);
                ctx.stroke();
                ctx.restore();
            },
        };
        const valueLabelsPlugin = {
            id: 'maDilValueLabels',
            afterDraw: function(chart) {
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const ctx = chart.ctx;
                const lastIdx = meta.data.length - 1;
                const anchors = [];
                ctx.save();
                ctx.font = 'bold 10px Inter, system-ui, sans-serif';
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                meta.data.forEach(function(point, i) {
                    const val = dataset.data[i];
                    let offsetY = (i % 2 === 0) ? -12 : -26;
                    if (i === lastIdx) offsetY = (lastIdx % 2 === 0) ? -26 : -12;
                    if (anchors.length) {
                        const prev = anchors[anchors.length - 1];
                        if (Math.abs(point.x - prev.x) < 36 && Math.abs((point.y + offsetY) - prev.y) < 14) {
                            offsetY = (offsetY === -12) ? -28 : -12;
                        }
                    }
                    anchors.push({ x: point.x, y: point.y + offsetY });
                    ctx.save();
                    ctx.fillStyle = labelColors[i];
                    ctx.translate(point.x, point.y + offsetY);
                    ctx.rotate(-Math.PI / 5);
                    ctx.fillText(fmtVal(val), 2, 0);
                    ctx.restore();
                });
                ctx.restore();
            },
        };
        maDilHistChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: 'rgba(108,117,125,0.08)',
                    borderColor: '#adb5bd',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                    pointBorderWidth: 1.5,
                }],
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                clip: false,
                layout: { padding: { top: 44, left: 4, right: 22, bottom: 8 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + fmtVal(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    const arrow = diff < 0 ? '▼' : (diff > 0 ? '▲' : '▬');
                                    parts.push('vs Yesterday: ' + arrow + ' ' + fmtVal(Math.abs(diff)));
                                }
                                return parts;
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        ticks: {
                            font: { size: 9 },
                            precision: 0,
                            callback: function(value) { return fmtVal(value); },
                        },
                    },
                    x: {
                        ticks: {
                            maxRotation: 60,
                            minRotation: 60,
                            autoSkip: false,
                            maxTicksLimit: Math.max(labels.length, 31),
                            font: { size: 8 },
                        },
                    },
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
                    headerTooltip: "OV L30 ÷ INV. 0% red · 0–25% yellow · 25–50% green · 50–100% pink · >100% purple.",
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

        $(document).on('click', '#ma-dil-legend .ma-dil-hist-dot, #ma-dil-amz-legend .ma-dil-hist-dot', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const band = $(this).data('band');
            const kind = $(this).data('chart');
            if (band) maDrawDilHist(String(band), kind);
        });
        $(document).on('click', '#ma-dil-legend .ma-dil-pie-row.is-filterable, #ma-dil-amz-legend .ma-dil-pie-row.is-filterable', function (e) {
            if ($(e.target).closest('.ma-dil-hist-dot').length) return;
            maSetDilBandFilter(String($(this).data('band') || ''));
        });
        $('#maDilHistModal').on('shown.bs.modal', function () {
            maPaintDilHistChart(maDilHistBand);
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
