@extends('layouts.vertical', ['title' => 'Alibaba Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #alibaba-analytics-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 13px;
            background: #fff;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        #alibaba-analytics-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header .tabulator-col {
            background: #f8f9fa !important;
            border-right: 1px solid #e9ecef;
            color: #000 !important;
            font-weight: 700;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: #000 !important;
            padding: 6px 4px;
        }
        #summary-stats .badge {
            font-size: 1rem;
            white-space: nowrap;
            font-weight: bold;
        }
        .ab-filter-badge.active-filter { outline: 3px solid #0d6efd; outline-offset: 2px; }
        #alibaba-analytics-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 6px 8px !important;
            border-right: 1px solid #f1f5f9;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-row:hover {
            background-color: #f8fafc !important;
        }
        #alibaba-analytics-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .ab-stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            min-width: 86px;
            justify-content: center;
        }
        .ab-stat-badge--rows { background: #0f172a; }
        .ab-stat-badge--active { background: #16a34a; }
        .ab-stat-badge--bulk { background: #2563eb; }
        .ab-stat-badge--manual { background: #d97706; }
        .ab-stat-badge--soh { background: #7c3aed; }
        .ab-stat-badge--inv { background: #0d9488; }
        .ab-stat-badge--ovl30 { background: #0369a1; }
        .card-loader-overlay {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.78);
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Alibaba Analytics',
        'sub_title' => 'Alibaba',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card position-relative shadow-sm">
                <div class="card-body">
                    <div id="summary-stats" class="mb-2 p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-dark fs-6 p-2" id="ab-rows-badge">Row: 0</span>
                            <span class="badge bg-primary fs-6 p-2" id="ab-sales-badge"
                                title="Sales from /alibaba/daily-sales: Σ (unit price × qty) for the last 30 Pacific days.">Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="ab-gpft-badge"
                                title="GPFT = PFT ÷ Sales. PFT = (sales × margin) − (LP × AB L30). Ship is not subtracted.">GPFT: 0%</span>
                            <span class="badge bg-success fs-6 p-2" id="ab-pft-badge"
                                title="PFT = Σ ((sales × margin) − LP × AB L30). Ship is not subtracted.">PFT: $0</span>
                            <span class="badge fs-6 p-2" id="ab-groi-badge" style="background-color:#6f42c1;color:#fff;"
                                title="GROI = PFT ÷ COGS. COGS = Σ (LP × AB L30) on SKUs with sales. Ship is not subtracted.">GROI: 0%</span>
                            <span class="badge bg-success fs-6 p-2 ab-filter-badge" id="ab-sold-badge" data-filter="more" style="cursor:pointer;"
                                title="AB L30 &gt; 0 and INV &gt; 0">Sold &gt;0: <span id="ab-more-sold">0</span></span>
                            <span class="badge bg-danger fs-6 p-2 ab-filter-badge" id="ab-zero-badge" data-filter="zero" style="cursor:pointer;"
                                title="AB L30 = 0 and INV &gt; 0">0 Sold: <span id="ab-zero-sold">0</span></span>
                            <span class="badge bg-secondary fs-6 p-2"
                                title="marketplace_percentages for Alibaba. Ship is not used.">Margin: {{ number_format((float) ($marginPercent ?? 95), 2) }}%</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        <select id="ab-inventory-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="ab-sold-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all">All</option>
                            <option value="zero">0 sold</option>
                            <option value="more">&gt; 0 sold</option>
                        </select>
                        <select id="ab-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="ab-cvr-filter" class="form-select form-select-sm" style="width:130px;" title="CVR = AB L30 ÷ OV L30">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                        <select id="ab-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>
                        <select id="ab-dil-filter" class="form-select form-select-sm" style="width:150px;">
                            <option value="all">DIL%</option>
                            <option value="red">Red (&lt;25%)</option>
                            <option value="green">Green (25–50%)</option>
                            <option value="pink">Pink (50%+)</option>
                        </select>
                        <select id="ab-status-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">Status: All</option>
                            <option value="Active">Active</option>
                            <option value="Offline">Offline</option>
                        </select>
                        <input type="text" id="ab-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search Parent or SKU...">
                        <button type="button" class="btn btn-sm btn-primary" id="ab-sync-btn">
                            <i class="fas fa-sync"></i> Sync from API
                        </button>
                    </div>

                    <div id="alibaba-analytics-wrap">
                        <div id="alibaba-analytics-table"></div>
                    </div>

                    <div id="ab-loader" class="card-loader-overlay" style="display:none;">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="mt-2 fw-semibold text-muted">Loading Alibaba Analytics...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.body.style.zoom = "90%";

        const AB_MARGIN = {{ (float) ($marginPercent ?? 95) }} / 100;
        let abTable = null;
        let abAllRows = [];

        function isAbParentRow(data) {
            if (!data) return false;
            return !!(data.is_parent_summary || data.is_parent_row);
        }

        function abDilValue(data) {
            const inv = parseFloat(data.INV) || 0;
            const ovL30 = parseFloat(data.L30 != null ? data.L30 : data.ov_l30) || 0;
            if (inv === 0) return 0;
            return (ovL30 / inv) * 100;
        }

        function abPct(value, kind) {
            const p = parseFloat(value);
            if (!isFinite(p)) return '<span style="color:#6c757d;">–</span>';
            let color = '#e83e8c';
            if (kind === 'roi') {
                color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
            } else {
                color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
            }
            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(p) + '%</span>';
        }

        function abMoney(value) {
            const n = parseFloat(value);
            if (!isFinite(n)) return '–';
            return '$' + n.toFixed(2);
        }

        function abRowMatches(data) {
            const inv = parseFloat(data.INV) || 0;
            const q = (document.getElementById('ab-search').value || '').trim().toLowerCase();
            if (isAbParentRow(data)) {
                const inventory = document.getElementById('ab-inventory-filter').value;
                if (inventory === 'zero' && inv !== 0) return false;
                if (inventory === 'more' && !(inv > 0)) return false;
                if (q && String(data.Parent || data.sku || '').toLowerCase().indexOf(q) === -1) return false;
                return document.getElementById('ab-sold-filter').value === 'all'
                    && document.getElementById('ab-gpft-filter').value === 'all'
                    && document.getElementById('ab-cvr-filter').value === 'all'
                    && document.getElementById('ab-roi-filter').value === 'all'
                    && document.getElementById('ab-dil-filter').value === 'all'
                    && document.getElementById('ab-status-filter').value === 'all';
            }
            const inventory = document.getElementById('ab-inventory-filter').value;
            if (inventory === 'zero' && inv !== 0) return false;
            if (inventory === 'more' && !(inv > 0)) return false;
            const sold = parseInt(data.al30, 10) || 0;
            const soldF = document.getElementById('ab-sold-filter').value;
            if (soldF === 'zero' && !(inv > 0 && sold === 0)) return false;
            if (soldF === 'more' && !(inv > 0 && sold > 0)) return false;
            const gpft = parseFloat(data.gpft) || 0;
            const gpftF = document.getElementById('ab-gpft-filter').value;
            if (gpftF === 'negative' && !(gpft < 0)) return false;
            if (gpftF === '0-10' && !(gpft >= 0 && gpft < 10)) return false;
            if (gpftF === '10-20' && !(gpft >= 10 && gpft < 20)) return false;
            if (gpftF === '20-30' && !(gpft >= 20 && gpft < 30)) return false;
            if (gpftF === '30-40' && !(gpft >= 30 && gpft < 40)) return false;
            if (gpftF === '40-50' && !(gpft >= 40 && gpft < 50)) return false;
            if (gpftF === '50plus' && !(gpft >= 50)) return false;
            const cvr = parseFloat(data.cvr) || 0;
            const cvrF = document.getElementById('ab-cvr-filter').value;
            if (cvrF === '0-0' && cvr !== 0) return false;
            if (cvrF === '0-3' && !(cvr > 0 && cvr < 3)) return false;
            if (cvrF === '3-7' && !(cvr >= 3 && cvr < 7)) return false;
            if (cvrF === '7-13' && !(cvr >= 7 && cvr < 13)) return false;
            if (cvrF === '13plus' && !(cvr >= 13)) return false;
            const roi = parseFloat(data.groi) || 0;
            const roiF = document.getElementById('ab-roi-filter').value;
            if (roiF === 'lt40' && !(roi < 40)) return false;
            if (roiF === '40-75' && !(roi >= 40 && roi < 75)) return false;
            if (roiF === '75-125' && !(roi >= 75 && roi < 125)) return false;
            if (roiF === 'gt125' && !(roi >= 125)) return false;
            const dil = abDilValue(data);
            const dilF = document.getElementById('ab-dil-filter').value;
            if (dilF === 'red' && !(dil < 25)) return false;
            if (dilF === 'green' && !(dil >= 25 && dil < 50)) return false;
            if (dilF === 'pink' && !(dil >= 50)) return false;
            const status = document.getElementById('ab-status-filter').value;
            if (status !== 'all' && String(data.status || '').toLowerCase() !== status.toLowerCase()) return false;
            if (q) {
                const hay = (String(data.Parent || '') + ' ' + String(data.sku || '') + ' ' + String(data.product_id || '')).toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        }

        function updateSummary(rows) {
            let totalSales = 0, totalProfit = 0, totalCogs = 0, zeroSold = 0, moreSold = 0, visible = 0;
            (rows || []).forEach(function (row) {
                if (isAbParentRow(row)) return;
                visible++;
                const qty = parseInt(row.al30, 10) || 0;
                const sales = parseFloat(row.sales) || 0;
                const lp = parseFloat(row.lp) || 0;
                const margin = parseFloat(row._margin) || AB_MARGIN;
                if (sales > 0 && qty > 0) {
                    totalSales += sales;
                    totalProfit += (sales * margin) - (lp * qty);
                    totalCogs += lp * qty;
                }
                const inv = parseFloat(row.INV) || 0;
                if (inv <= 0) return;
                if (qty === 0) zeroSold++; else moreSold++;
            });
            const gpft = totalSales > 0 ? Math.round((totalProfit / totalSales) * 100) : 0;
            const groi = totalCogs > 0 ? Math.round((totalProfit / totalCogs) * 100) : 0;
            document.getElementById('ab-rows-badge').textContent = 'Row: ' + visible.toLocaleString();
            document.getElementById('ab-sales-badge').textContent = 'Sales: $' + Math.round(totalSales).toLocaleString();
            document.getElementById('ab-pft-badge').textContent = 'PFT: $' + Math.round(totalProfit).toLocaleString();
            document.getElementById('ab-gpft-badge').textContent = 'GPFT: ' + gpft + '%';
            document.getElementById('ab-groi-badge').textContent = 'GROI: ' + groi + '%';
            document.getElementById('ab-more-sold').textContent = moreSold.toLocaleString();
            document.getElementById('ab-zero-sold').textContent = zeroSold.toLocaleString();
            document.querySelectorAll('.ab-filter-badge').forEach(function (el) { el.classList.remove('active-filter'); });
            const soldF = document.getElementById('ab-sold-filter').value;
            if (soldF === 'more') document.getElementById('ab-sold-badge').classList.add('active-filter');
            if (soldF === 'zero') document.getElementById('ab-zero-badge').classList.add('active-filter');
        }

        function applyFilters() {
            if (!abTable) return;
            const filtered = (abAllRows || []).filter(abRowMatches);
            abTable.setData(filtered).then(function () { updateSummary(filtered); });
        }

        function loadTable() {
            const loader = document.getElementById('ab-loader');
            loader.style.display = 'flex';
            fetch("{{ route('alibaba.analytics.data') }}", {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(json => {
                abAllRows = json.data || [];
                if (window.ParentExpand) ParentExpand.captureDataset(abAllRows);
                if (abTable) {
                    applyFilters();
                    return;
                }
                abTable = new Tabulator('#alibaba-analytics-table', {
                    data: (abAllRows || []).filter(abRowMatches),
                    layout: 'fitDataStretch',
                    height: '68vh',
                    placeholder: 'No Alibaba API products yet. Use Sync from API.',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 250],
                    initialSort: [{ column: 'al30', dir: 'desc' }],
                    rowFormatter: function (row) {
                        const data = row.getData();
                        if (isAbParentRow(data)) {
                            row.getElement().classList.add('parent-row');
                            row.getElement().style.backgroundColor = '#fffef2';
                        }
                    },
                    columns: [
                        {
                            title: 'Parent', field: 'Parent', hozAlign: 'left', width: 110, frozen: true,
                            formatter: function (cell) {
                                const v = cell.getValue() || '';
                                if (!v || isAbParentRow(cell.getRow().getData())) return '';
                                return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + v + '</span>';
                            }
                        },
                        (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, headerSort: false, frozen: true }),
                        {
                            title: 'Image', field: 'image_path', headerSort: false, width: 60, hozAlign: 'center',
                            formatter: function (cell) {
                                const v = cell.getValue();
                                if (isAbParentRow(cell.getRow().getData()) || !v) return '';
                                return '<img src="' + v + '" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;">';
                            }
                        },
                        { title: 'SKU', field: 'sku', hozAlign: 'left', minWidth: 160, frozen: true, cssClass: 'fw-bold' },
                        { title: 'INV', field: 'INV', hozAlign: 'center', width: 55, sorter: 'number' },
                        { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 60, sorter: 'number' },
                        {
                            title: 'Dil', field: 'dil_percent', hozAlign: 'center', width: 55, sorter: 'number',
                            headerTooltip: 'Dil = OV L30 ÷ INV × 100',
                            formatter: function (cell) {
                                const rowData = cell.getRow().getData();
                                const dil = abDilValue(rowData);
                                if ((parseFloat(rowData.INV) || 0) === 0) return '<span style="color:#6c757d;">0%</span>';
                                let color = '#e83e8c';
                                if (dil < 25) color = '#dc3545';
                                else if (dil < 50) color = '#28a745';
                                return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                            }
                        },
                        {
                            title: 'AB L30', field: 'al30', hozAlign: 'center', width: 60, sorter: 'number',
                            headerTooltip: 'Units from /alibaba/daily-sales, last 30 Pacific days'
                        },
                        {
                            title: 'Price', field: 'price', hozAlign: 'center', width: 80, sorter: 'number',
                            headerTooltip: 'Alibaba API SKU price',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                const v = parseFloat(cell.getValue()) || 0;
                                if (v === 0) return '<span style="color:#a00211;font-weight:600;">$0.00</span>';
                                return '$' + v.toFixed(2);
                            }
                        },
                        {
                            title: 'GROI', field: 'groi', hozAlign: 'center', width: 55, sorter: 'number',
                            headerTooltip: 'GROI = ((Price × margin) − LP) ÷ LP. Ship is not subtracted.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                return abPct(cell.getValue(), 'roi');
                            }
                        },
                        {
                            title: 'GPFT', field: 'gpft', hozAlign: 'center', width: 55, sorter: 'number',
                            headerTooltip: 'GPFT = ((Price × margin) − LP) ÷ Price. Ship is not subtracted.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                return abPct(cell.getValue(), 'gpft');
                            }
                        },
                        {
                            title: 'Profit', field: 'profit', hozAlign: 'center', width: 70, sorter: 'number',
                            headerTooltip: 'Unit profit = (Price × margin) − LP. Ship is not subtracted.',
                            formatter: function (cell) {
                                if (isAbParentRow(cell.getRow().getData())) return '<span style="color:#6c757d;">–</span>';
                                const v = parseFloat(cell.getValue()) || 0;
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return '<span style="color:' + color + ';font-weight:600;">$' + v.toFixed(2) + '</span>';
                            }
                        },
                        {
                            title: 'Sales', field: 'sales', hozAlign: 'center', width: 80, sorter: 'number',
                            headerTooltip: 'Line sales from /alibaba/daily-sales (unit price × qty), last 30 Pacific days',
                            formatter: function (cell) { return abMoney(cell.getValue()); }
                        },
                        { title: 'Product Id', field: 'product_id', hozAlign: 'left', minWidth: 130 },
                        { title: 'Status', field: 'status', hozAlign: 'center', width: 80 },
                        { title: 'SOH', field: 'soh', hozAlign: 'center', width: 55, sorter: 'number' },
                    ],
                });
                if (window.ParentExpand) {
                    ParentExpand.configure({
                        parentField: 'Parent',
                        skuField: 'sku',
                        getTable: function () { return abTable; },
                        getDataset: function () { return abAllRows; },
                        onCollapse: function () { applyFilters(); }
                    });
                    ParentExpand.bind();
                }
                updateSummary((abAllRows || []).filter(abRowMatches));
            })
            .finally(() => { loader.style.display = 'none'; });
        }

        ['ab-inventory-filter', 'ab-sold-filter', 'ab-gpft-filter', 'ab-cvr-filter', 'ab-roi-filter', 'ab-dil-filter', 'ab-status-filter'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', applyFilters);
        });
        document.getElementById('ab-search').addEventListener('input', applyFilters);
        document.getElementById('ab-sold-badge').addEventListener('click', function () {
            document.getElementById('ab-sold-filter').value = document.getElementById('ab-sold-filter').value === 'more' ? 'all' : 'more';
            applyFilters();
        });
        document.getElementById('ab-zero-badge').addEventListener('click', function () {
            document.getElementById('ab-sold-filter').value = document.getElementById('ab-sold-filter').value === 'zero' ? 'all' : 'zero';
            applyFilters();
        });
        document.getElementById('ab-sync-btn').addEventListener('click', function () {
            const btn = this;
            const loader = document.getElementById('ab-loader');
            const label = loader.querySelector('.fw-semibold');
            btn.disabled = true;
            loader.style.display = 'flex';

            function syncPage(page, reset) {
                if (label) label.textContent = 'Syncing Alibaba API, page ' + page + '...';
                const body = new URLSearchParams();
                body.set('page', String(page));
                body.set('reset', reset ? '1' : '0');
                return fetch("{{ route('alibaba.analytics.sync') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body.toString()
                })
                .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
                .then(({ ok, body }) => {
                    if (!ok || !body.success) {
                        throw new Error(body.message || 'Sync failed.');
                    }
                    if (!body.done) {
                        return syncPage(page + 1, false);
                    }
                    if (label) label.textContent = body.message || 'Sync finished.';
                    loadTable();
                });
            }

            syncPage(1, true)
                .catch(function (err) {
                    alert(err.message || 'Sync failed.');
                })
                .finally(function () {
                    btn.disabled = false;
                    loader.style.display = 'none';
                });
        });

        loadTable();
    </script>
@endsection
