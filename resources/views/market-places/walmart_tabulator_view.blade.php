@extends('layouts.vertical', ['title' => 'Walmart Pricing CVR', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .parent-row {
            background-color: #fffef2 !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #fffef2 !important;
            font-weight: bold !important;
        }

        /* Vertical column headers */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* NR Status Color Coding */
        .nr-select.nr-status {
            color: white;
            font-weight: bold;
        }

        .nr-select.nr-status option {
            color: black;
            font-weight: normal;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'walmart'])
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Walmart Pricing CVR',
        'sub_title' => 'Walmart Pricing CVR',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..."
                        style="width: 150px; display: inline-block;">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..."
                        style="width: 150px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: 120px; display: inline-block;">
                        <option value="all">All INV</option>
                        <option value="zero">INV = 0</option>
                        <option value="more" selected>INV &gt; 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm" style="width: 120px; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <select id="cvr-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">CVR</option>
                        <option value="0-0">0 to 0.00%</option>
                        <option value="0.01-1">0.01 - 1%</option>
                        <option value="1-2">1-2%</option>
                        <option value="2-3">2-3%</option>
                        <option value="3-4">3-4%</option>
                        <option value="0-4">0-4%</option>
                        <option value="4-7">4-7%</option>
                        <option value="7-13">7-13%</option>
                        <option value="10plus">10%+</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50-60">50-60%</option>
                        <option value="50plus">50%+</option>
                    </select>

                    <select id="ads-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">AD%</option>
                        <option value="0-10">Below 10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-100">30-100%</option>
                        <option value="100plus">100%+</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm"
                        style="width: 130px; display: inline-block;">
                        <option value="all">Show All</option>
                        <option value="hide" selected>Hide Parents</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm"
                        style="width: 120px; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="listed">Listed</option>
                        <option value="live">Live</option>
                        <option value="both">Listed & Live</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                            id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu p-2" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye"></i> Show All
                    </button>

                    <!-- Export Button -->
                    <a href="{{ url('/walmart-export') }}" class="btn btn-sm btn-success">
                        <i class="fas fa-file-csv"></i> Export
                    </a>

                    <!-- Import Button -->
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import
                    </button>

                    <!-- Import Ratings Button -->
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importRatingsModal">
                        <i class="fas fa-star"></i> Import Ratings
                    </button>

                    <!-- Template Download Button -->
                    <a href="{{ url('/walmart-analytics/sample') }}" class="btn btn-sm btn-info">
                        <i class="fas fa-download"></i> Template
                    </a>

                    <!-- Refresh Button -->
                    <button id="refresh-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'walmart'])
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">All Calculations Summary</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Top Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="avg-pft" style="color: black; font-weight: bold;">AVG PFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        
                        <!-- Walmart Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-inv-badge" style="color: black; font-weight: bold;">Total Walmart INV: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold;">0 Sold Count: 0</span>
                        <span class="badge fs-6 p-2" id="walmart-blue-triangle-badge" style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;" title="Blue triangle: S PRC ≠ Price.">
                            <i class="fas fa-exclamation-triangle"></i> 0</span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-l30-badge" style="color: black; font-weight: bold;">Total Spend L30: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI %: 0%</span>
                        
                        <!-- Status Counts -->
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="walmart-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="walmart-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Walmart Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="importForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Upload CSV/Excel File</label>
                            <input type="file" class="form-control" id="excelFile" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-info">
                            <small><strong>File should contain:</strong> SKU, Listed, Live</small>
                            <br><small>Example: SKU, Listed, Live<br>ABC123, TRUE, FALSE</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Ratings Modal -->
    <div class="modal fade" id="importRatingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Walmart Ratings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="importRatingsForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select CSV/Excel File</label>
                            <input type="file" class="form-control" id="ratingsFile" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">Upload a CSV/Excel file with columns: <strong>sku, rating</strong> (0-5)</div>
                        </div>
                        <div class="alert alert-info">
                            <small><strong>Example format:</strong></small>
                            <br><code>sku,rating<br>ABC123,4.5<br>DEF456,3.8</code>
                        </div>
                        <div class="mt-2">
                            <a href="/walmart-ratings-sample" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download"></i> Download Sample Template
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadRatingsBtn">
                            <i class="fa fa-upload"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'walmart'])
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "walmart_tabulator_column_visibility";
        const MARKETPLACE_PERCENTAGE = {{ $percentage ?? 80 }} / 100; // Walmart marketplace percentage
        let table = null;
        let allTableData = []; // Full dataset for ParentExpand

        /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
        function walmartStdPrcChangeDotMeta(stdPrc, comparePrice) {
            const sp = parseFloat(stdPrc);
            const ap = parseFloat(comparePrice);
            if (!isFinite(sp) || sp <= 0 || !isFinite(ap) || ap <= 0) return null;
            const sp2 = sp.toFixed(2);
            const ap2 = ap.toFixed(2);
            if (parseFloat(sp2) < parseFloat(ap2)) {
                return { kind: 'reduce', color: '#dc3545', title: 'Reduce vs Amz price' };
            }
            if (parseFloat(sp2) > parseFloat(ap2)) {
                return { kind: 'increase', color: '#28a745', title: 'Increase vs Amz price' };
            }
            return null;
        }

        function walmartStdPrcChangeDotHtml(stdPrc, comparePrice) {
            const meta = walmartStdPrcChangeDotMeta(stdPrc, comparePrice);
            if (!meta) return '';
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
        }

        function applyWalmartStandardPriceToLinkedRows(sku, std, appliedSkus) {
            if (typeof table === 'undefined' || !table) return null;
            const target = String(sku || '').trim().toUpperCase();
            const appliedSet = new Set(
                (Array.isArray(appliedSkus) ? appliedSkus : [])
                    .map(function(s) { return String(s || '').trim().toUpperCase(); })
                    .filter(Boolean)
            );
            if (target) appliedSet.add(target);

            let primaryRow = null;
            (table.getRows('all') || table.getRows() || []).forEach(function(r) {
                const d = r.getData();
                if (!d || d.is_parent_summary) return;
                const rowSku = String(d['(Child) sku'] || d.SKU || d.sku || '').trim();
                if (!rowSku) return;
                const rowKey = rowSku.toUpperCase();
                const linked = Array.isArray(d.linked_lmp_skus) ? d.linked_lmp_skus : [];
                const inGroup = appliedSet.has(rowKey)
                    || linked.some(function(s) { return String(s || '').trim().toUpperCase() === target; })
                    || (target && rowKey === target);
                if (!inGroup) return;
                r.update({ STANDARD_PRICE: std });
                if (typeof applyChannelSpriceFromStdChange === 'function') {
                    applyChannelSpriceFromStdChange(r);
                }
                if (rowKey === target) primaryRow = r;
            });
            return primaryRow;
        }

        document.addEventListener('lmp-modal-sp-saved', function(e) {
            const detail = (e && e.detail) || {};
            const sku = detail.sku;
            const saved = parseFloat(detail.standard_price);
            if (!sku || !isFinite(saved) || saved <= 0) return;
            applyWalmartStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
        });

        function isWalmartParentRow(row) {
            if (!row) return false;
            if (row.is_parent_summary === true) return true;
            const sku = String(row['(Child) sku'] || row.SKU || row.sku || '').toUpperCase();
            return sku.includes('PARENT');
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'walmart'])
        function walmartRowSpriceForAlert(data) {
            let sprice = parseFloat(data && (data.SPRICE != null ? data.SPRICE : data.sprice)) || 0;
            if (typeof chPromoSpriceFromStdTPromo === 'function' && !isWalmartParentRow(data)) {
                const calc = chPromoSpriceFromStdTPromo(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function walmartHasBlueTriangle(data) {
            if (isWalmartParentRow(data)) return false;
            const sprice = walmartRowSpriceForAlert(data);
            const price = parseFloat(data && data.price) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        let blueTriangleFilterActive = false;
        function syncWalmartTriangleBadgeState() {
            $('#walmart-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

        $(document).ready(function() {
            table = new Tabulator("#walmart-table", {
                ajaxURL: "/walmart-data-json",
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationCounter: "rows",
                columnCalcs: "both",
                ajaxResponse: function(url, params, response) {
                    var payload = (response && response.data) ? response.data : response;
                    if (Array.isArray(payload)) {
                        allTableData = payload;
                        if (window.ParentExpand) ParentExpand.captureDataset(payload);
                    }
                    return payload;
                },
                initialSort: [{
                    column: "parent",
                    dir: "asc"
                }],
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent_summary) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        visible: false
                    },
                    ParentExpand.columnDef(),
                    {
                        title: "Image",
                        field: "image_path",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const imagePath = cell.getValue();
                            if (imagePath) {
                                return `<img src="${imagePath}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" />`;
                            }
                            return '';
                        },
                        width: 80
                    },
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        frozen: true,
                        width: 250,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            const rowData = cell.getRow().getData();

                            // Don't show copy button for parent rows
                            if (rowData.is_parent_summary) {
                                return `<span style="font-weight: bold;">${sku}</span>`;
                            }

                            return `<div style="display: flex; align-items: center; gap: 5px;">
                                <span>${sku}</span>
                                <button class="btn btn-sm btn-link copy-sku-btn p-0" data-sku="${sku}" title="Copy SKU">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>`;
                        }
                    },
                    {
                        title: "Rating",
                        field: "rating",
                        hozAlign: "center",
                        editor: "input",
                        tooltip: "Enter rating between 0 and 5",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';

                            if (!value || value === null || value === 0) {
                                return '<span style="color: #999;">-</span>';
                            }

                            return `<span style="font-weight: 600;"><i class="fa fa-star" style="color: orange; font-size: 10px;"></i> ${parseFloat(value).toFixed(1)}</span>`;
                        },
                        width: 70
                    },
                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },
                    {
                        title: "Dil",
                        field: "E Dil%",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;

                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';

                            const dil = (OVL30 / INV) * 100;
                            let color = '';

                            // Color logic from Amazon
                            if (dil < 16.66) color = '#a00211'; // red
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                            else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50 and above)

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "W L30",
                        field: "W_L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        sorterParams: {dir: "asc"}
                    },
                    {
                        title: "CVR",
                        field: "CVR_L30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const wL30 = parseFloat(row['W_L30']) || 0;
                            // Use page_views first, fallback to insights_views
                            let pageViews = parseFloat(row['page_views']) || 0;
                            if (pageViews === 0) {
                                pageViews = parseFloat(row['insights_views']) || 0;
                            }

                            if (pageViews === 0) return '<span style="color: #6c757d; font-weight: 600;">0.0%</span>';

                            const cvr = (wL30 / pageViews) * 100;
                            let color = '';
                            
                            // getCvrColor logic from Amazon
                            if (cvr <= 4) color = '#a00211'; // red
                            else if (cvr > 4 && cvr <= 7) color = '#ffc107'; // yellow
                            else if (cvr > 7 && cvr <= 13) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${cvr.toFixed(1)}%</span>`;
                        },
                        sorter: function(a, b, aRow, bRow) {
                            const calcCVR = (row) => {
                                const wL30 = parseFloat(row['W_L30']) || 0;
                                let pageViews = parseFloat(row['page_views']) || 0;
                                if (pageViews === 0) {
                                    pageViews = parseFloat(row['insights_views']) || 0;
                                }
                                return pageViews === 0 ? 0 : (wL30 / pageViews) * 100;
                            };
                            return calcCVR(aRow.getData()) - calcCVR(bRow.getData());
                        },
                        sorterParams: {dir: "asc"},
                        width: 60
                    },

                     {
                        title: "Views",
                        field: "page_views",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';

                            // Use page_views first, fallback to insights_views
                            let views = cell.getValue();
                            if (!views || views === 0) {
                                views = rowData.insights_views || 0;
                            }

                            if (!views || views === 0) {
                                return '<span style="color: #999;">0</span>';
                            }

                            return parseInt(views).toLocaleString();
                        },
                        sorter: "number",
                        width: 100
                    },
                    // {
                    //     title: "View",
                    //     field: "Sess30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     width: 50
                    // },
                    // {
                    //     title: "API Views",
                    //     field: "api_views",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: function(cell) {
                    //         const value = cell.getValue();
                    //         if (!value || value === 0) return '<span style="color: #6c757d;">0</span>';
                    //         return `<span style="font-weight: 600;">${parseInt(value).toLocaleString()}</span>`;
                    //     },
                    //     width: 70
                    // },
                    // {
                    //     title: "Reviews",
                    //     field: "total_review_count",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: function(cell) {
                    //         const value = cell.getValue();
                    //         if (!value || value === 0) return '<span style="color: #6c757d;">0</span>';
                    //         return `<span style="font-weight: 600;">${parseInt(value).toLocaleString()}</span>`;
                    //     },
                    //     width: 70
                    // },
                    {
                        title: "NR/RL",
                        field: "NR",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();

                            // Empty for parent rows
                            if (row.is_parent_summary) return '';

                            const nrl = row['NR'] || '';
                            const sku = row['(Child) sku'];

                            // Determine current value (default to REQ if empty)
                            let value = '';
                            if (nrl === 'NR') {
                                value = 'NR';
                            } else if (nrl === 'REQ') {
                                value = 'REQ';
                            } else {
                                value = 'REQ'; // Default to REQ
                            }

                            // Set background color based on value
                            let bgColor = '#28a745'; // Green for RL
                            let textColor = 'black';
                            if (value === 'NR') {
                                bgColor = '#dc3545'; // Red for NR
                                textColor = 'black';
                            }

                            return `<select class="form-select form-select-sm nr-select" data-sku="${sku}"
                                style="background-color: ${bgColor}; color: ${textColor}; border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 4px;">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>RL</option>
                                <option value="NR" ${value === 'NR' ? 'selected' : ''}>NRL</option>
                            </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 90
                    },
                    {
                        title: "Std Prc",
                        field: "STANDARD_PRICE",
                        hozAlign: "center",
                        headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view (amazon_data_view.STANDARD_PRICE). Editable; saves to all Sku Link LMP siblings. Dot vs Amz/channel price.",
                        editor: "input",
                        width: 70,
                        sorter: "number",
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent_summary) return false;
                            const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                            return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.is_parent_summary) return '';
                            const value = cell.getValue();
                            const std = parseFloat(value) || 0;
                            if (!value || std <= 0) return '';
                            const amzPrice = parseFloat(rowData['A Price'] || rowData.a_price || rowData.amazon_price || 0) || 0;
                            const channelPrice = parseFloat(rowData.price || rowData['Price'] || 0) || 0;
                            const comparePrice = amzPrice > 0 ? amzPrice : channelPrice;
                            const dot = walmartStdPrcChangeDotHtml(std, comparePrice);

                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' +
                                dot + ('$' + std.toFixed(2)) + '</span>';
                        }
                    },
                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary || !value) return '';

                            return '$' + parseFloat(value).toFixed(2);
                        },
                        sorter: "number",
                        width: 70
                    },
                    {
                        title: "GPFT %",
                        field: "GPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const percent = parseFloat(value) || 0;
                            let color = '';

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "AD%",
                        field: "AD%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const adSpend = parseFloat(rowData.AD_Spend_L30) || 0;
                            const sales = parseFloat(rowData['W_L30']) || 0;
                            
                            // If there is ad spend but no sales, show 100%
                            if (adSpend > 0 && sales === 0) {
                                return `<span style="color: #a00211; font-weight: 600;">100%</span>`;
                            }
                            
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '0.00%';
                            
                            // If spend > 0 but AD% is 0, show red alert
                            if (adSpend > 0 && percent === 0) {
                                return `<span style="color: #dc3545; font-weight: 600;">100%</span>`;
                            }
                            
                            // Color coding for AD%
                            let color = '';
                            if (percent < 10) color = '#28a745'; // green - good
                            else if (percent >= 10 && percent < 20) color = '#ffc107'; // yellow
                            else if (percent >= 20 && percent < 30) color = '#fd7e14'; // orange
                            else color = '#a00211'; // red - bad
                            
                            return `<span style="color: ${color}; font-weight: 600;">${parseFloat(value).toFixed(0)}%</span>`;
                        },
                        width: 55
                    },
                    {
                        title: "PFT %",
                        field: "PFT%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const gpft = parseFloat(rowData['GPFT%'] || 0);
                            const ad = parseFloat(rowData['AD%'] || 0);
                            
                            // If AD% is 100% (no sales case), PFT% = GPFT% (same as eBay)
                            const percent = (ad === 100) ? gpft : (gpft - ad);
                            let color = '';
                            
                            // getPftColor logic from Amazon
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 50
                    },
                    {
                        title: "ROI%",
                        field: "ROI_percentage",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '0.00%';
                            const percent = parseFloat(value);
                            let color = '';
                            
                            // getRoiColor logic from Amazon
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        sorter: "number",
                        width: 65
                    },
                    {
                        title: "W Prc",
                        field: "buybox_base_price",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();

                            // Empty for parent rows
                            if (rowData.is_parent_summary) return '';

                            const buyboxPrice = cell.getValue();

                            if (!buyboxPrice || buyboxPrice === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            return '$' + parseFloat(buyboxPrice).toFixed(2);
                        },
                        sorter: "number",
                        width: 90
                    },
                    // {
                    //     title: "BB Total",
                    //     field: "buybox_total_price",
                    //     hozAlign: "center",
                    //     formatter: function(cell) {
                    //         const rowData = cell.getRow().getData();

                    //         // Empty for parent rows
                    //         if (rowData.is_parent_summary) return '';

                    //         const buyboxTotal = cell.getValue();

                    //         if (!buyboxTotal || buyboxTotal === 0) {
                    //             return '<span style="color: #999;">N/A</span>';
                    //         }

                    //         return '$' + parseFloat(buyboxTotal).toFixed(2);
                    //     },
                    //     sorter: "number",
                    //     width: 90
                    // },
                   
                    // {
                    //     title: "Page Views",
                    //     field: "page_views",
                    //     hozAlign: "center",
                    //     formatter: function(cell) {
                    //         const rowData = cell.getRow().getData();

                    //         // Empty for parent rows
                    //         if (rowData.is_parent_summary) return '';

                    //         const pageViews = cell.getValue();

                    //         if (!pageViews || pageViews === 0) {
                    //             return '<span style="color: #999;">0</span>';
                    //         }

                    //         return parseInt(pageViews).toLocaleString();
                    //     },
                    //     sorter: "number",
                    //     width: 100
                    // },
                    ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                    {
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        headerTooltip: "S PRC = Std × (1 − (PRMT% + cvr%)/100). Blue triangle = S PRC ≠ Price. Red text = S PRC > LMP.",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (isWalmartParentRow(rowData)) return '';
                            let value = parseFloat(cell.getValue() || 0);
                            if (typeof chPromoSpriceFromStdTPromo === 'function') {
                                const calc = chPromoSpriceFromStdTPromo(rowData);
                                if (calc > 0) value = calc;
                            }
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const live = parseFloat(rowData.price) || 0;
                            const lmp = parseFloat(rowData.lmp_price || rowData.lmp || rowData.LMP) || 0;
                            if (!(value > 0)) return '';
                            const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(rowData, value) : null;
                            if (cap && cap.shown > 0) value = cap.shown;
                            const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                            const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                            const formatted = '$' + value.toFixed(2);
                            let style = 'font-weight:600;';
                            if (overLmp) style += 'color:#dc3545;';
                            else if (hasCustomSprice === false) style += 'color:#0d6efd;';
                            const priceHtml = `<span style="${style}">${formatted}</span>`;
                            const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${redTri}${blueTri}</span>`;
                        },
                        width: 92
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "S GPFT",
                        field: "SGPFT",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as GPFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "S PFT",
                        field: "Spft%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as PFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },
                    {
                        title: "SPEND L30",
                        field: "AD_Spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            
                            if (value === 0) return '';
                            return `$${value.toFixed(2)}`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 90
                    },
                ]
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'Parent',
                    skuField: '(Child) sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    onAfterExpand: () => {
                        if (typeof updateSummary === 'function') updateSummary();
                        if (typeof updateCalcValues === 'function') updateCalcValues();
                    },
                    onCollapse: () => {
                        if (typeof applyFilters === 'function') applyFilters();
                    },
                });
                ParentExpand.bind();
            }

            // NR select change handler with color coding
            $(document).on('change', '.nr-select', function() {
                const $select = $(this);
                const value = $select.val();
                const sku = $select.data('sku');

                // Update background color based on selection (only NR or REQ)
                let bgColor = '#28a745'; // Default green for REQ
                if (value === 'NR') {
                    bgColor = '#dc3545'; // Red for NR
                    $select.css('background-color', bgColor).css('color', 'white');
                } else if (value === 'REQ') {
                    bgColor = '#28a745'; // Green for REQ
                    $select.css('background-color', bgColor).css('color', 'white');
                }

                // Save to database
                $.ajax({
                    url: '/walmart/save-nr',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        nr: value
                    },
                    success: function(response) {
                        showToast('success', `NR status updated to ${value}`);
                    },
                    error: function() {
                        showToast('error', 'Failed to update NR status');
                    }
                });
            });

            // Listed checkbox change handler
            $(document).on('change', '.listed-checkbox', function() {
                const sku = $(this).data('sku');
                const value = $(this).is(':checked');

                $.ajax({
                    url: '/walmart/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        field: 'Listed',
                        value: value
                    },
                    success: function(response) {
                        showToast('success', 'Listed status updated');
                    },
                    error: function() {
                        showToast('error', 'Failed to update Listed status');
                    }
                });
            });

            // Live checkbox change handler
            $(document).on('change', '.live-checkbox', function() {
                const sku = $(this).data('sku');
                const value = $(this).is(':checked');

                $.ajax({
                    url: '/walmart/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        field: 'Live',
                        value: value
                    },
                    success: function(response) {
                        showToast('success', 'Live status updated');
                    },
                    error: function() {
                        showToast('error', 'Failed to update Live status');
                    }
                });
            });

            // SKU Search functionality
            $('#sku-search, #parent-search').on('keyup', function() {
                table.setFilter([
                    { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                    { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
                ]);
            });

            // Cell edited handler
            table.on('cellEdited', function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const data = row.getData();
                const value = cell.getValue();

                if (field === 'STANDARD_PRICE') {
                    const sku = data['(Child) sku'] || data.sku || data.SKU;
                    const std = parseFloat(value);
                    if (!sku || !isFinite(std) || std <= 0) {
                        row.update({ STANDARD_PRICE: null });
                        return;
                    }
                    $.ajax({
                        url: '/save-amazon-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: std,
                            is_standard_price: 1
                        },
                        success: function(response) {
                            const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                            applyWalmartStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                            const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                            showToast('success', n > 1
                                ? ('Std Prc saved for ' + n + ' linked SKUs')
                                : 'Std Prc saved');
                        },
                        error: function() {
                            showToast('error', 'Failed to save Std Prc');
                        }
                    });
                    return;
                }

                if (field === 'SPRICE') {
                    const sku = data['(Child) sku'];
                    const sprice = parseFloat(cell.getValue()) || 0;

                    // Save to database
                    $.ajax({
                        url: '/save-walmart-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: sprice
                        },
                        success: function(response) {
                            showToast('success', 'SPRICE updated successfully');
                            
                            // Update SGPFT, SPFT% and SROI% from server response
                            if (response.sgpft_percent !== undefined) {
                                row.update({
                                    'SGPFT': response.sgpft_percent
                                });
                            }
                            if (response.spft_percent !== undefined) {
                                row.update({
                                    'Spft%': response.spft_percent
                                });
                            }
                            if (response.sroi_percent !== undefined) {
                                row.update({
                                    'SROI': response.sroi_percent
                                });
                            }
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update SPRICE');
                        }
                    });
                } else if (field === 'buybox_price') {
                    const sku = data['(Child) sku'];
                    const buyboxPrice = parseFloat(cell.getValue()) || 0;

                    // Save to database
                    $.ajax({
                        url: '/save-walmart-buybox-price',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            buybox_price: buyboxPrice
                        },
                        success: function(response) {
                            showToast('success', 'Buybox price updated successfully');
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update buybox price');
                        }
                    });
                } else if (field === 'rating') {
                    const sku = data['(Child) sku'];
                    const rating = parseFloat(cell.getValue());

                    // Validate rating
                    if (isNaN(rating) || rating < 0 || rating > 5) {
                        showToast('error', 'Rating must be between 0 and 5');
                        cell.setValue(data.rating || null);
                        return;
                    }

                    // Save to database
                    $.ajax({
                        url: '/update-walmart-rating',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            rating: rating
                        },
                        success: function(response) {
                            showToast('success', 'Rating updated successfully');
                            row.update({ rating: rating });
                        },
                        error: function(xhr) {
                            showToast('error', 'Failed to update rating');
                            cell.setValue(data.rating || null);
                        }
                    });
                } else if (field === 'price') {
                    updateCalcValues();
                }
            });

            // Apply filters
            function applyFilters() {
                if (window.ParentExpand && ParentExpand.isExpanded()) {
                    ParentExpand.beforeFilters(function() {
                        applyFilters();
                    });
                    return;
                }
                const inventoryFilter = $('#inventory-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const parentFilter = $('#parent-filter').val();
                const statusFilter = $('#status-filter').val();

                table.clearFilter(true);

                if (inventoryFilter === 'zero') {
                    table.addFilter('INV', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('INV', '>', 0);
                }

                if (nrlFilter !== 'all') {
                    if (nrlFilter === 'REQ') {
                        // Show only REQ (exclude NR)
                        table.addFilter(function(data) {
                            return data.NR !== 'NR';
                        });
                    } else if (nrlFilter === 'NR') {
                        // Show only NR
                        table.addFilter(function(data) {
                            return data.NR === 'NR';
                        });
                    }
                }

                if (cvrFilter !== 'all') {
                    table.addFilter(function(data) {
                        // Always show parent rows
                        if (data.is_parent_summary) return true;
                        
                        const wL30 = parseFloat(data['W_L30']) || 0;
                        const insightsViews = parseFloat(data['insights_views']) || 0;
                        const cvr = insightsViews > 0 ? (wL30 / insightsViews) * 100 : 0;
                        
                        if (cvrFilter === '0-0') return cvr === 0;
                        if (cvrFilter === '0.01-1') return cvr > 0 && cvr <= 1;
                        if (cvrFilter === '1-2') return cvr > 1 && cvr <= 2;
                        if (cvrFilter === '2-3') return cvr > 2 && cvr <= 3;
                        if (cvrFilter === '3-4') return cvr > 3 && cvr <= 4;
                        if (cvrFilter === '0-4') return cvr >= 0 && cvr <= 4;
                        if (cvrFilter === '4-7') return cvr > 4 && cvr <= 7;
                        if (cvrFilter === '7-13') return cvr > 7 && cvr <= 13;
                        if (cvrFilter === '10plus') return cvr > 10;
                        return true;
                    });
                }

                if (parentFilter === 'hide') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                }

                // GPFT% filter
                const gpftFilter = $('#gpft-filter').val();
                if (gpftFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return true;
                        const gpft = parseFloat(data['GPFT%']) || 0;
                        
                        if (gpftFilter === 'negative') return gpft < 0;
                        if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                        if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                        if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                        if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                        if (gpftFilter === '40-50') return gpft >= 40 && gpft < 50;
                        if (gpftFilter === '50-60') return gpft >= 50 && gpft < 60;
                        if (gpftFilter === '50plus') return gpft >= 50;
                        return true;
                    });
                }

                // AD% filter
                const adsFilter = $('#ads-filter').val();
                if (adsFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent_summary) return true;
                        const adPercent = parseFloat(data['AD%']) || 0;
                        
                        if (adsFilter === '0-10') return adPercent >= 0 && adPercent < 10;
                        if (adsFilter === '10-20') return adPercent >= 10 && adPercent < 20;
                        if (adsFilter === '20-30') return adPercent >= 20 && adPercent < 30;
                        if (adsFilter === '30-100') return adPercent >= 30 && adPercent <= 100;
                        if (adsFilter === '100plus') return adPercent > 100;
                        return true;
                    });
                }

                if (statusFilter === 'listed') {
                    table.addFilter('Listed', '=', true);
                } else if (statusFilter === 'live') {
                    table.addFilter('Live', '=', true);
                } else if (statusFilter === 'both') {
                    table.addFilter([{
                            field: 'Listed',
                            type: '=',
                            value: true
                        },
                        {
                            field: 'Live',
                            type: '=',
                            value: true
                        }
                    ]);
                }
                if (blueTriangleFilterActive) {
                    table.addFilter(function(data) {
                        return walmartHasBlueTriangle(data);
                    });
                }

                updateCalcValues();
                updateSummary();
            }

            $('#walmart-blue-triangle-badge').on('click', function() {
                blueTriangleFilterActive = !blueTriangleFilterActive;
                applyFilters();
            });

            $('#inventory-filter, #nrl-filter, #cvr-filter, #gpft-filter, #ads-filter, #parent-filter, #status-filter').on('change', function() {
                applyFilters();
            });

            // Update calc values using MARKETPLACE_PERCENTAGE
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let totalCogs = 0;

                data.forEach(row => {
                    if (row.is_parent_summary || parseFloat(row.INV) <= 0) return;

                    const price = parseFloat(row.price) || 0;
                    const wL30 = parseFloat(row.W_L30) || 0;
                    const lp = parseFloat(row.LP_productmaster) || 0;
                    const ship = parseFloat(row.Ship_productmaster) || 0;

                    const sales = price * wL30;
                    const profit = (price * MARKETPLACE_PERCENTAGE - lp - ship) * wL30;
                    const cogs = lp * wL30;

                    totalSales += sales;
                    totalProfit += profit;
                    totalCogs += cogs;
                });

                // TOP PFT% = (total profit sum / total sales) * 100
                const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // TOP ROI% = (total profit sum / total COGS) * 100
                const avgRoi = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

                $('#avg-pft').text('Avg PFT%: ' + avgPft.toFixed(2) + '%');
                $('#avg-roi').text('Avg ROI%: ' + avgRoi.toFixed(2) + '%');
            }

            // Update summary
            function updateSummary() {
                const data = table.getData("active");
                const childData = data.filter(row => !row.is_parent_summary);

                let totalSkuCount = childData.length;
                let invGt0 = 0;
                let listedCount = 0;
                let liveCount = 0;
                let totalSalesAmt = 0;
                let totalPftAmt = 0;
                let totalViews = 0;
                let totalInv = 0;
                let totalWL30 = 0;
                let zeroSoldCount = 0;
                let totalSpendL30 = 0;
                let totalCogs = 0;
                let totalPrices = 0;
                let priceCount = 0;
                let totalDilPercent = 0;
                let dilCount = 0;
                let totalAdPercent = 0;

                childData.forEach(row => {
                    if (parseFloat(row.INV) > 0) invGt0++;
                    if (row.Listed) listedCount++;
                    if (row.Live) liveCount++;

                    // Only calculate totals for rows with INV > 0 (same as eBay)
                    if (parseFloat(row.INV) > 0) {
                        const price = parseFloat(row.price) || 0;
                        const wL30 = parseFloat(row.W_L30) || 0;
                        const l30 = parseFloat(row.L30) || 0;
                        const lp = parseFloat(row.LP_productmaster) || 0;
                        const ship = parseFloat(row.Ship_productmaster) || 0;
                        const inv = parseFloat(row.INV) || 0;
                        const views = parseFloat(row.page_views) || parseFloat(row.insights_views) || 0;
                        const adSpend = parseFloat(row.AD_Spend_L30) || 0;

                        totalSalesAmt += price * wL30;
                        totalPftAmt += (price * MARKETPLACE_PERCENTAGE - lp - ship) * wL30;
                        totalViews += views;
                        totalInv += inv;
                        totalWL30 += wL30;
                        totalSpendL30 += adSpend;
                        totalCogs += lp * wL30;
                        
                        // Count SKUs with 0 sold (W_L30 = 0)
                        if (wL30 === 0) zeroSoldCount++;
                        
                        if (price > 0) {
                            totalPrices += price;
                            priceCount++;
                        }
                        
                        const dil = parseFloat(row['E Dil%'] || row.L30 / row.INV * 100) || 0;
                        if (!isNaN(dil)) {
                            totalDilPercent += dil;
                            dilCount++;
                        }
                        
                        // Sum AD% for AVG PFT calculation (same as eBay2)
                        const adPercent = parseFloat(row['AD%']) || 0;
                        totalAdPercent += adPercent;
                    }
                });

                // Calculate average CVR = (Total W_L30 / Total Views) * 100
                const avgCvr = totalViews > 0 ? (totalWL30 / totalViews) * 100 : 0;
                
                // Calculate DIL% = average of individual DIL% (same as eBay)
                const avgDil = dilCount > 0 ? (totalDilPercent / dilCount) : 0;
                
                // Calculate average GPFT = (Total Profit / Total Sales) * 100 (same as eBay)
                const avgGpft = totalSalesAmt > 0 ? (totalPftAmt / totalSalesAmt) * 100 : 0;
                
                // Calculate weighted average price = Total Sales / Total L30
                const avgPrice = totalWL30 > 0 ? totalSalesAmt / totalWL30 : 0;
                
                // Calculate ROI% = (Total Profit / Total COGS) * 100
                const roiPercent = totalCogs > 0 ? (totalPftAmt / totalCogs) * 100 : 0;
                
                // Calculate AVG PFT = GPFT - (Sum of AD% / count of INV > 0 rows) (same as eBay2)
                const avgAdPercent = invGt0 > 0 ? totalAdPercent / invGt0 : 0;
                const avgPft = avgGpft - avgAdPercent;

                // Update all badges
                $('#total-sku-count-badge').text('Total SKUs: ' + totalSkuCount.toLocaleString());
                $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt).toLocaleString());
                $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt).toLocaleString());
                $('#avg-gpft-badge').text('AVG GPFT: ' + avgGpft.toFixed(1) + '%');
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#avg-cvr-badge').text('Avg CVR: ' + avgCvr.toFixed(2) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#total-inv-badge').text('Total Walmart INV: ' + totalInv.toLocaleString());
                $('#total-l30-badge').text('Total W L30: ' + totalWL30.toLocaleString());
                $('#zero-sold-count-badge').text('0 Sold Count: ' + zeroSoldCount.toLocaleString());
                let blueTriangleCount = 0;
                (table ? table.getData() : []).forEach(function(row) {
                    if (walmartHasBlueTriangle(row)) blueTriangleCount++;
                });
                $('#walmart-blue-triangle-badge').html(
                    '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
                );
                if (typeof syncWalmartTriangleBadgeState === 'function') syncWalmartTriangleBadgeState();
                $('#avg-dil-percent-badge').text('DIL %: ' + avgDil.toFixed(1) + '%');
                $('#total-spend-l30-badge').text('Total Spend L30: $' + totalSpendL30.toFixed(2));
                $('#total-cogs-amt-badge').text('COGS AMT: $' + Math.round(totalCogs).toLocaleString());
                $('#roi-percent-badge').text('ROI %: ' + roiPercent.toFixed(1) + '%');
                $('#avg-pft').text('AVG PFT: ' + avgPft.toFixed(1) + '%');
            }

            // Build column visibility dropdown
            function buildColumnDropdown() {
                if (window.AnalyticsColVis) {
                    window.AnalyticsColVis.install({
                        getTable: function() { return table; },
                        menuId: 'column-dropdown-menu',
                        storageKey: 'walmart_col_cats_v1',
                        skipFields: ['_select'],
                        onSave: function() {
                            if (typeof saveColumnVisibilityToServer === 'function') saveColumnVisibilityToServer();
                        }
                    });
                    window.AnalyticsColVis.rebuild();
                    return;
                }
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                table.getColumns().forEach(col => {
                    const field = col.getField();
                    const title = col.getDefinition().title;
                    const visible = col.isVisible();

                    if (field && title) {
                        const div = document.createElement('div');
                        div.className = 'form-check';
                        div.innerHTML = `
                            <input class="form-check-input" type="checkbox" 
                                   data-field="${field}" ${visible ? 'checked' : ''}>
                            <label class="form-check-label">${title}</label>
                        `;
                        menu.appendChild(div);
                    }
                });
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field) {
                        visibility[field] = col.isVisible();
                    }
                });

                fetch('/walmart-column-visibility', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify({
                        visibility: JSON.stringify(visibility)
                    })
                }).catch(err => console.error('Error saving column visibility:', err));
            }

            function applyColumnVisibilityFromServer() {
                fetch('/walmart-column-visibility', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.visibility) {
                            const visibility = JSON.parse(data.visibility);
                            Object.keys(visibility).forEach(field => {
                                const column = table.getColumn(field);
                                if (column) {
                                    if (visibility[field]) {
                                        column.show();
                                    } else {
                                        column.hide();
                                    }
                                }
                            });
                            buildColumnDropdown();
                        }
                    })
                    .catch(err => console.error('Error loading column visibility:', err));
            }

            // Wait for table to be built
            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();
            });

            table.on('dataLoaded', function() {
                updateCalcValues();
                updateSummary();
                setTimeout(function() {
                    buildColumnDropdown();
                }, 100);
            });

            table.on('renderComplete', function() {
                setTimeout(function() {
                    updateSummary();
                }, 100);
            });

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const field = e.target.getAttribute('data-field');
                    const column = table.getColumn(field);

                    if (e.target.checked) {
                        column.show();
                    } else {
                        column.hide();
                    }

                    saveColumnVisibilityToServer();
                }
            });

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => {
                    col.show();
                });
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            // Export button - now handled by anchor tag, no JS needed

            // Refresh button
            $('#refresh-btn').on('click', function() {
                table.setData();
            });

            // Copy SKU button handler
            $(document).on('click', '.copy-sku-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).data('sku');
                navigator.clipboard.writeText(sku).then(() => {
                    showToast('success', 'SKU copied: ' + sku);
                }).catch(() => {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = sku;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showToast('success', 'SKU copied: ' + sku);
                });
            });

            // Import form handler
            $('#importForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                const file = $('#excelFile')[0].files[0];

                if (!file) {
                    showToast('error', 'Please select a file');
                    return;
                }

                formData.append('excel_file', file);
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                $('#uploadBtn').prop('disabled', true).html(
                    '<i class="fa fa-spinner fa-spin"></i> Importing...');

                $.ajax({
                    url: '/walmart-import',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.success);
                            $('#importModal').modal('hide');
                            $('#importForm')[0].reset();
                            table.setData();
                        } else if (response.error) {
                            showToast('error', response.error);
                        } else {
                            showToast('success', 'Import successful');
                            $('#importModal').modal('hide');
                            table.setData();
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Import failed';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        showToast('error', errorMsg);
                    },
                    complete: function() {
                        $('#uploadBtn').prop('disabled', false).html(
                            '<i class="fas fa-upload"></i> Upload');
                    }
                });
            });

            // Import Ratings Handler
            $('#importRatingsForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                const file = $('#ratingsFile')[0].files[0];

                if (!file) {
                    showToast('error', 'Please select a file');
                    return;
                }

                formData.append('file', file);
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                const uploadBtn = $('#uploadRatingsBtn');
                uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                $.ajax({
                    url: '/import-walmart-ratings',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        $('#importRatingsModal').modal('hide');
                        $('#ratingsFile').val('');
                        showToast('success', response.success || 'Ratings imported successfully');
                        
                        // Reload table data
                        setTimeout(() => {
                            table.setData('/walmart-data-json');
                        }, 1000);
                    },
                    error: function(xhr) {
                        uploadBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                        const errorMsg = xhr.responseJSON?.error || 'Failed to import ratings';
                        showToast('error', errorMsg);
                    }
                });
            });

            // Toast notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);

                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();

                setTimeout(() => toast.remove(), 3000);
            }
        });
    </script>
@endsection
