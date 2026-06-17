@extends('layouts.vertical', ['title' => 'LQS Data', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }

        /* ── Parent row – identical to aliexpress ── */
        .tabulator-row.lqs-parent-row,
        .tabulator-row.lqs-parent-row .tabulator-cell {
            background-color: #bde0ff !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        .tabulator-row.lqs-parent-row .tabulator-cell {
            min-height: 48px !important; height: 48px !important;
            padding-top: 8px !important; padding-bottom: 8px !important;
            overflow: visible !important; vertical-align: middle !important;
            color: #1e3a5f;
        }
        .tabulator-row.lqs-parent-row:hover,
        .tabulator-row.lqs-parent-row:hover .tabulator-cell {
            background-color: #93c5fd !important;
        }

        /* ── Modern pagination – identical to aliexpress ── */
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important;
            min-width: 36px !important; height: 36px !important; line-height: 36px !important;
            padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important;
            color: #475569 !important; cursor: pointer; transition: all 0.15s ease !important;
            text-align: center !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important;
            color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        /* ── DIL dropdown (identical to aliexpress) ── */
        .lqs-manual-dropdown { position: relative; display: inline-block; }
        .lqs-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .lqs-manual-dropdown.show .dropdown-menu { display: block; }
        .lqs-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .lqs-dropdown-item:hover { background: #e9ecef; }

        /* ── Status circles ── */
        .lqs-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .lqs-sc.def    { background:#6c757d; }
        .lqs-sc.red    { background:#dc3545; }
        .lqs-sc.yellow { background:#ffc107; }
        .lqs-sc.green  { background:#28a745; }
        .lqs-sc.pink   { background:#e83e8c; }

        /* Summary badges — horizontal scroll on narrow viewports (identical to aliexpress) */
        #summary-stats .lqs-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        #summary-stats .lqs-summary-badge-row > .badge {
            flex: 1 1 0;
            min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'LQS Data',
        'sub_title'  => 'Parent SKU, Image, DIL, and Inventory metrics',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- ── Filter bar (identical structure to aliexpress) ── --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                        {{-- Row type filter (All Rows / Parents / SKUs) – same as aliexpress --}}
                        <select id="lqs-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>

                        {{-- Inventory filter --}}
                        <select id="lqs-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>

                        {{-- LQS filter --}}
                        <select id="lqs-score-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">All LQS</option>
                            <option value="8-10">High (8-10)</option>
                            <option value="6-7">Good (6-7)</option>
                            <option value="4-5">Medium (4-5)</option>
                            <option value="1-3">Low (1-3)</option>
                            <option value="missing">No LQS</option>
                        </select>

                        {{-- DIL% dropdown (identical to aliexpress) --}}
                        <div class="lqs-manual-dropdown">
                            <button class="btn btn-light btn-sm lqs-dil-toggle" type="button" id="lqs-dil-btn">
                                <span class="lqs-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="lqs-dropdown-item lqs-dil-item active" href="#" data-color="all">
                                    <span class="lqs-sc def"></span>All DIL</a></li>
                                <li><a class="lqs-dropdown-item lqs-dil-item" href="#" data-color="red">
                                    <span class="lqs-sc red"></span>Red (&lt;16.7%)</a></li>
                                <li><a class="lqs-dropdown-item lqs-dil-item" href="#" data-color="yellow">
                                    <span class="lqs-sc yellow"></span>Yellow (16.7–25%)</a></li>
                                <li><a class="lqs-dropdown-item lqs-dil-item" href="#" data-color="green">
                                    <span class="lqs-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="lqs-dropdown-item lqs-dil-item" href="#" data-color="pink">
                                    <span class="lqs-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>

                        {{-- SKU search --}}
                        <input type="text" id="lqs-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU...">

                        <button type="button" id="refresh-lqs-table" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="export-lqs-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                    </div>

                    {{-- ── Summary badges ── --}}
                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2 lqs-summary-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-primary fs-6 p-2 lqs-badge-chart" id="lqs-total-inv-badge" data-metric="total_inv" style="font-weight:700;cursor:pointer;" title="Click for trend">Total INV: 0</span>
                            <span class="badge bg-warning fs-6 p-2 lqs-badge-chart" id="lqs-total-ov-badge" data-metric="total_ov" style="font-weight:700;color:#111;cursor:pointer;" title="Click for trend">Total OV L30: 0</span>
                            <span class="badge bg-info fs-6 p-2 lqs-badge-chart" id="lqs-avg-dil-badge" data-metric="avg_dil" style="font-weight:700;color:#111;cursor:pointer;" title="Click for trend">Avg DIL: 0%</span>
                            <span class="badge bg-success fs-6 p-2 lqs-badge-chart" id="lqs-avg-lqs-badge" data-metric="avg_lqs" style="font-weight:700;cursor:pointer;" title="Click for trend">Avg LQS: –</span>
                        </div>
                    </div>

                    <div id="lqs-data-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge Trend Chart Modal – matches Aliexpress style --}}
    <div class="modal fade" id="lqsBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width:80vw;width:80vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="lqsBadgeChartTitle">LQS Data – Badge Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="lqsBadgeChartRange" class="form-select form-select-sm bg-white"
                            style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <!-- Line chart + stat panel -->
                    <div id="lqsBadgeLineWrap" style="display:none;height:38vh;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="lqsBadgeLineCanvas"></canvas>
                        </div>
                        <div id="lqsBadgeStatPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="lqsBadgeHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="lqsBadgeMedian"  style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="lqsBadgeLowest"  style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <!-- Bar chart -->
                    <div id="lqsBadgeBarWrap" style="display:none;height:160px;margin-top:8px;">
                        <canvas id="lqsBadgeBarCanvas"></canvas>
                    </div>
                    <div id="lqsBadgeLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="lqsBadgeNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No trend data yet. Data is saved each time the page loads.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
        let table = null;
        let summaryDataCache = [];

        function lqsNotify(msg, type) {
            if (window.toastr) {
                if (type === 'warning') toastr.warning(msg);
                else if (type === 'error') toastr.error(msg);
                else toastr.success(msg);
            } else {
                alert(msg);
            }
        }

        // ── applyFilters (mirrors aliexpress applyFilters) ────────────────
        function applyFilters() {
            if (!table) return;
            table.clearFilter();

            const skuSearch  = ($('#lqs-sku-search').val() || '').toLowerCase().trim();
            const rowType    = $('#lqs-row-type-filter').val();
            const invFilter  = $('#lqs-inv-filter').val();
            const lqsFilter  = $('#lqs-score-filter').val();
            const dilColor   = $('.lqs-dil-item.active').data('color') || 'all';

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }

            // Row type filter (All / Parents / SKUs) – same as aliexpress
            if (rowType === 'parents') {
                table.addFilter(d => d.is_parent === true);
            } else if (rowType === 'skus') {
                table.addFilter(d => !d.is_parent);
            }

            // Inventory filter
            if (invFilter === 'zero') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) === 0);
            } else if (invFilter === 'more') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) > 0);
            }

            // LQS filter
            if (lqsFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const lqs = parseInt(d.lqs, 10);
                    if (lqsFilter === 'missing') return !lqs || isNaN(lqs);
                    if (lqsFilter === '8-10') return lqs >= 8 && lqs <= 10;
                    if (lqsFilter === '6-7') return lqs >= 6 && lqs < 8;
                    if (lqsFilter === '4-5') return lqs >= 4 && lqs < 6;
                    if (lqsFilter === '1-3') return lqs >= 1 && lqs < 4;
                    return true;
                });
            }

            // DIL% filter (identical to aliexpress)
            if (dilColor !== 'all') {
                table.addFilter(function(d) {
                    const inv   = parseFloat(d.inv)    || 0;
                    const ovL30 = parseFloat(d.ov_l30) || 0;
                    const dil   = inv === 0 ? 0 : (ovL30 / inv) * 100;
                    if (dilColor === 'red')    return dil < 16.66;
                    if (dilColor === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilColor === 'green')  return dil >= 25 && dil < 50;
                    if (dilColor === 'pink')   return dil >= 50;
                    return true;
                });
            }
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(row => {
                    if (row && typeof row.getData === "function") {
                        return row.getData();
                    }
                    return row || {};
                });
            }
            if (rowsInput && typeof rowsInput === "object") {
                return Object.values(rowsInput).map(row => {
                    if (row && typeof row.getData === "function") {
                        return row.getData();
                    }
                    return row || {};
                });
            }
            return [];
        }

        function updateSummary(rowsInput = null) {
            let rows = normalizeRows(rowsInput);
            if (!rows.length && table && typeof table.getData === "function") {
                const activeRows = normalizeRows(table.getData("active"));
                const allRows    = normalizeRows(table.getData());
                rows = activeRows.length ? activeRows : allRows;
            }
            if (!rows.length) rows = normalizeRows(summaryDataCache);

            let totalInv = 0, totalOv = 0;
            let dilSum = 0, dilCount = 0;
            let lqsSum = 0, lqsCount = 0;

            rows.forEach(row => {
                if (row.is_parent) return;
                
                const inv = parseFloat(row.inv) || 0;
                const ov = parseFloat(row.ov_l30) || 0;
                const lqs = parseInt(row.lqs, 10);

                totalInv += inv;
                totalOv += ov;
                
                if (inv > 0) {
                    const dil = (ov / inv) * 100;
                    dilSum += dil;
                    dilCount++;
                }

                if (lqs && !isNaN(lqs)) {
                    lqsSum += lqs;
                    lqsCount++;
                }
            });

            const avgDil = dilCount > 0 ? dilSum / dilCount : 0;
            const avgLqs = lqsCount > 0 ? lqsSum / lqsCount : 0;

            $('#lqs-total-inv-badge').text(`Total INV: ${totalInv.toLocaleString()}`);
            $('#lqs-total-ov-badge').text(`Total OV L30: ${totalOv.toLocaleString()}`);
            $('#lqs-avg-dil-badge').text(`Avg DIL: ${Math.round(avgDil)}%`);
            $('#lqs-avg-lqs-badge').text(`Avg LQS: ${avgLqs > 0 ? avgLqs.toFixed(1) : '–'}`);
        }

        $(document).ready(function() {
            table = new Tabulator("#lqs-data-table", {
                ajaxURL: "/lqs/data",
                ajaxResponse: function(url, params, response) {
                    summaryDataCache = normalizeRows(response);
                    updateSummary(summaryDataCache);
                    return response;
                },
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('lqs-parent-row');
                    }
                },
                columns: [
                    {
                        title: "Parent",
                        field: "parent",
                        width: 120,
                        frozen: true,
                        cssClass: "text-muted",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#0d6efd;font-size:11px;font-weight:600;">${v}</span>`;
                        }
                    },
                    {
                        title: "Image",
                        field: "image",
                        width: 60,
                        headerSort: false,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent || !src) return '';
                            return `<img src="${src}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;"
                                onerror="this.style.display='none'">`;
                        }
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        minWidth: 200,
                        frozen: true,
                        headerFilter: "input",
                        cssClass: "fw-bold text-primary",
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return `<span style="color:#1e40af;font-size:13px;font-weight:700;">${val}</span>`;
                            }
                            const esc = val.replace(/&/g,'&amp;').replace(/</g,'&lt;');
                            return `<span class="fw-bold">${esc}</span>`;
                        }
                    },
                    {
                        title: "INV",
                        field: "inv",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return `<span style="font-weight:700;">${cell.getValue()}</span>`;
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "ov_l30",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        formatter: function(cell) {
                            return `<span style="font-weight:700;">${parseInt(cell.getValue(), 10) || 0}</span>`;
                        }
                    },
                    {
                        title: "Dil",
                        field: "dil_percent",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv   = parseFloat(row.inv)    || 0;
                            const ovL30 = parseFloat(row.ov_l30) || 0;
                            if (inv === 0) return `<span style="color:#6c757d;">0%</span>`;
                            const dil = (ovL30 / inv) * 100;
                            let color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: "LQS",
                        field: "lqs",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const lqs = parseInt(cell.getValue(), 10);
                            if (!lqs || isNaN(lqs)) return '<span style="color:#adb5bd;">–</span>';
                            
                            // Color coding based on LQS value (1-10 scale)
                            let color = '#6c757d';
                            if (lqs >= 8) color = '#28a745';      // Green for 8-10
                            else if (lqs >= 6) color = '#3591dc'; // Blue for 6-7
                            else if (lqs >= 4) color = '#ffc107'; // Yellow for 4-5
                            else color = '#dc3545';                // Red for 1-3
                            
                            return `<span style="color:${color};font-weight:600;">${lqs}</span>`;
                        }
                    }
                ],
                dataLoaded: function(data) {
                    updateSummary(data);
                },
                dataFiltered: function(filters, rows) {
                    updateSummary(rows);
                },
                dataProcessed: function() {
                    updateSummary();
                },
                renderComplete: function() {
                    updateSummary();
                }
            });

            $('#lqs-sku-search').on('input', function() { applyFilters(); });
            $('#lqs-row-type-filter').on('change', function() { applyFilters(); });
            $('#lqs-inv-filter').on('change',    function() { applyFilters(); });
            $('#lqs-score-filter').on('change',  function() { applyFilters(); });

            // DIL dropdown (identical to aliexpress manual dropdown)
            $(document).on('click', '.lqs-dil-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.lqs-manual-dropdown').toggleClass('show');
            });
            $(document).on('click', '.lqs-dil-item', function(e) {
                e.preventDefault(); e.stopPropagation();
                $('.lqs-dil-item').removeClass('active');
                $(this).addClass('active');
                const circle = $(this).find('.lqs-sc').clone();
                $('#lqs-dil-btn').html('').append(circle).append('DIL%');
                $(this).closest('.lqs-manual-dropdown').removeClass('show');
                applyFilters();
            });
            $(document).on('click', function() {
                $('.lqs-manual-dropdown').removeClass('show');
            });

            $('#refresh-lqs-table').on('click', function() {
                table.setData("/lqs/data");
            });

            $('#export-lqs-btn').on('click', function() {
                table.download("csv", "lqs_data.csv");
            });

            // ── Badge Trend Chart (mirrors Aliexpress badge chart) ──────
            let lqsBadgeLineChart = null;
            let lqsBadgeBarChart  = null;
            let lqsBadgeMetric    = '';
            let lqsBadgeDays      = 30;
            let lqsBadgeAjax      = null;

            const lqsCountMetrics   = ['total_inv', 'total_ov'];
            const lqsPercentMetrics = ['avg_dil', 'avg_lqs'];

            const lqsBadgeLabels = {
                total_inv: 'Total INV',
                total_ov: 'Total OV L30',
                avg_dil: 'Avg DIL%',
                avg_lqs: 'Avg LQS'
            };

            function lqsFormatChartVal(v) {
                const n = Number(v) || 0;
                if (lqsPercentMetrics.includes(lqsBadgeMetric)) {
                    if (lqsBadgeMetric === 'avg_lqs') return n.toFixed(1);
                    return Math.round(n) + '%';
                }
                return Math.round(n).toLocaleString('en-US');
            }

            function lqsRenderCharts(points) {
                if (!Array.isArray(points) || !points.length) return false;

                const labels = points.map(p => p.date);
                const values = points.map(p => Number(p.value) || 0);
                const sorted = [...values].sort((a, b) => a - b);
                const mid    = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 ? sorted[mid] : (sorted[mid-1] + sorted[mid]) / 2;
                const highest = sorted[sorted.length - 1];
                const lowest  = sorted[0];

                $('#lqsBadgeHighest').text(lqsFormatChartVal(highest));
                $('#lqsBadgeMedian').text(lqsFormatChartVal(median));
                $('#lqsBadgeLowest').text(lqsFormatChartVal(lowest));

                const lineCtx = document.getElementById('lqsBadgeLineCanvas');
                const barCtx  = document.getElementById('lqsBadgeBarCanvas');
                if (!lineCtx || typeof Chart === 'undefined') return false;

                if (lqsBadgeLineChart) lqsBadgeLineChart.destroy();
                if (lqsBadgeBarChart)  lqsBadgeBarChart.destroy();

                const label = lqsBadgeLabels[lqsBadgeMetric] || lqsBadgeMetric;

                // Point colors: red if below median, green if above
                const pointColors = values.map(v => v >= median ? '#28a745' : '#dc3545');

                // Register datalabels plugin globally if available
                if (typeof ChartDataLabels !== 'undefined') {
                    Chart.register(ChartDataLabels);
                }

                // ── Line chart with value labels on each point ──────────
                lqsBadgeLineChart = new Chart(lineCtx.getContext('2d'), {
                    type: 'line',
                    plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : [],
                    data: {
                        labels: labels,
                        datasets: [{
                            label: label,
                            data: values,
                            borderColor: '#adb5bd',
                            backgroundColor: 'rgba(173,181,189,0.08)',
                            pointBackgroundColor: pointColors,
                            pointBorderColor: pointColors,
                            pointRadius: 5, pointHoverRadius: 7,
                            borderWidth: 2, tension: 0.2, fill: true
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 24 } },
                        scales: {
                            y: {
                                min: lowest >= 0 ? 0 : undefined,
                                ticks: { callback: v => lqsFormatChartVal(v), font: { size: 11 } },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: { ticks: { font: { size: 10 }, maxRotation: 45 } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => label + ': ' + lqsFormatChartVal(ctx.parsed.y) } },
                            datalabels: typeof ChartDataLabels !== 'undefined' ? {
                                align: 'top', anchor: 'end',
                                font: { size: 10, weight: '600' },
                                color: ctx => ctx.dataset.pointBackgroundColor[ctx.dataIndex],
                                formatter: v => lqsFormatChartVal(v),
                                clip: false
                            } : false
                        }
                    }
                });

                // ── Bar chart ────────────────────────────────────────────
                lqsBadgeBarChart = new Chart(barCtx.getContext('2d'), {
                    type: 'bar',
                    plugins: typeof ChartDataLabels !== 'undefined' ? [ChartDataLabels] : [],
                    data: {
                        labels: labels,
                        datasets: [{
                            label: label,
                            data: values,
                            backgroundColor: values.map(v => v >= median ? 'rgba(13,110,253,0.7)' : 'rgba(13,110,253,0.4)'),
                            borderRadius: 3
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            y: { ticks: { callback: v => lqsFormatChartVal(v), font: { size: 10 } }, beginAtZero: false },
                            x: { ticks: { maxRotation: 45, font: { size: 9 } } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => label + ': ' + lqsFormatChartVal(ctx.parsed.y) } },
                            datalabels: { display: false }
                        }
                    }
                });
                return true;
            }

            function lqsLoadChart() {
                if (!lqsBadgeMetric) return;
                if (lqsBadgeAjax) lqsBadgeAjax.abort();
                $('#lqsBadgeNoData,#lqsBadgeLineWrap,#lqsBadgeBarWrap').hide();
                $('#lqsBadgeLoading').show();

                lqsBadgeAjax = $.ajax({
                    url: '{{ route("lqs.badge.chart") }}',
                    method: 'GET',
                    data: { metric: lqsBadgeMetric, days: lqsBadgeDays },
                    success: function(res) {
                        lqsBadgeAjax = null;
                        $('#lqsBadgeLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (lqsRenderCharts(pts)) {
                            $('#lqsBadgeLineWrap').css('display','flex');
                            $('#lqsBadgeBarWrap').show();
                        } else {
                            $('#lqsBadgeNoData').show();
                        }
                    },
                    error: function() {
                        lqsBadgeAjax = null;
                        $('#lqsBadgeLoading').hide();
                        $('#lqsBadgeNoData').show();
                    }
                });
            }

            $(document).on('click', '.lqs-badge-chart', function() {
                lqsBadgeMetric = $(this).data('metric');
                lqsBadgeDays   = 30;
                $('#lqsBadgeChartRange').val('30');
                $('#lqsBadgeChartTitle').text('LQS Data – ' + (lqsBadgeLabels[lqsBadgeMetric] || lqsBadgeMetric) + ' Trend');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('lqsBadgeChartModal')).show();
                lqsLoadChart();
            });

            $(document).on('change', '#lqsBadgeChartRange', function() {
                const d = parseInt($(this).val(), 10) || 30;
                if (d === lqsBadgeDays) return;
                lqsBadgeDays = d;
                lqsLoadChart();
            });
        });
    </script>
@endsection
