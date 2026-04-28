@extends('layouts.vertical', ['title' => 'Tiendamia Princing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }

        #tiendamia-products-table .tabulator-header,
        #tiendamia-products-table .tabulator-header .tabulator-col {
            background-color: #17a2b8 !important;
            color: #fff !important;
            border-color: rgba(255, 255, 255, 0.22) !important;
        }
        #tiendamia-products-table .tabulator-header .tabulator-col .tabulator-col-title {
            color: #fff !important;
        }
        #tiendamia-products-table .tabulator-header .tabulator-col.tabulator-moving,
        #tiendamia-products-table .tabulator-header .tabulator-col.tabulator-range-highlight {
            background-color: #138496 !important;
        }

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
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        .tm-manual-dropdown { position: relative; display: inline-block; }
        .tm-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
        }
        .tm-manual-dropdown.show .dropdown-menu { display: block; }
        .tm-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .tm-dropdown-item:hover { background: #e9ecef; }
        .tm-sc { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 6px; border: 1px solid #ddd; }
        .tm-sc.def { background: #6c757d; }
        .tm-sc.red { background: #dc3545; }
        .tm-sc.yellow { background: #ffc107; }
        .tm-sc.green { background: #28a745; }
        .tm-sc.pink { background: #e83e8c; }

        #summary-stats .ebay2-summary-badge-row {
            display: flex; flex-wrap: nowrap; align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem); width: 100%;
            overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 1 1 0; min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold; box-sizing: border-box;
            display: inline-flex; align-items: center; justify-content: center;
            text-align: center; white-space: nowrap;
        }

        #tmUploadPriceModal .modal-header {
            background-color: #26b9bd !important;
            color: #fff !important;
            border-bottom: 0;
        }
        #tmUploadPriceModal .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.9;
        }
        #tmUploadPriceModal .tm-upload-warning {
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="modal fade" id="tmUploadPriceModal" tabindex="-1" aria-labelledby="tmUploadPriceModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title d-flex align-items-center gap-2 mb-0" id="tmUploadPriceModalLabel">
                                        <i class="fas fa-dollar-sign"></i> Upload Price Data
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="tm-price-file" class="form-label mb-1">
                                            <i class="fas fa-file-alt text-primary me-1"></i>Choose File
                                        </label>
                                        <input type="file" class="form-control" id="tm-price-file" name="price_file">
                                        <div class="form-text">First row = header (same columns as <code>teinda.txt</code>). Plain text: delimiter is detected automatically (<strong>TAB</strong>, <strong>comma</strong>, or <strong>semicolon</strong>). <strong>Excel</strong> <code>.xlsx</code> / <code>.xls</code> / <code>.ods</code> uses the <strong>active sheet</strong>. Columns <strong>Offer SKU</strong> and <strong>Price</strong> are required.</div>
                                    </div>
                                    <div class="tm-upload-warning mb-3">
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        <strong>Warning:</strong> This will TRUNCATE (clear) the <code>tiendamia_price_uploads</code> table before uploading!
                                    </div>
                                    <div id="tm-upload-price-result" class="small" role="status"></div>
                                </div>
                                <div class="modal-footer border-top">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn text-white" id="tm-upload-price-btn" style="background-color:#26b9bd;border-color:#26b9bd;">
                                        <i class="fas fa-upload me-1"></i>Upload
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <select id="tm-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>

                        <select id="tm-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>

                        <select id="tm-stock-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">TM Stock</option>
                            <option value="zero">0 TM Stock</option>
                            <option value="more">More than 0</option>
                        </select>

                        <select id="tm-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>

                        <select id="tm-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="125-175">125–175%</option>
                            <option value="175-250">175–250%</option>
                            <option value="gt250">&gt; 250%</option>
                        </select>

                        <select id="tm-ml30-filter" class="form-select form-select-sm" style="width:130px;" title="Excludes 0 inventory rows when filtering bands">
                            <option value="all">ML30</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>

                        <select id="tm-map-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all">Map</option>
                            <option value="map">Map only</option>
                            <option value="nmap">N Map only</option>
                        </select>

                        <div class="tm-manual-dropdown">
                            <button class="btn btn-light btn-sm tm-dil-toggle" type="button" id="tm-dil-btn">
                                <span class="tm-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="tm-dropdown-item tm-dil-item active" href="#" data-color="all">
                                    <span class="tm-sc def"></span>All DIL</a></li>
                                <li><a class="tm-dropdown-item tm-dil-item" href="#" data-color="red">
                                    <span class="tm-sc red"></span>Red (&lt;16.7%)</a></li>
                                <li><a class="tm-dropdown-item tm-dil-item" href="#" data-color="yellow">
                                    <span class="tm-sc yellow"></span>Yellow (16.7–25%)</a></li>
                                <li><a class="tm-dropdown-item tm-dil-item" href="#" data-color="green">
                                    <span class="tm-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="tm-dropdown-item tm-dil-item" href="#" data-color="pink">
                                    <span class="tm-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>

                        <input type="text" id="tm-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU...">

                        <button type="button" id="tm-refresh-btn" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="tm-export-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-sm text-white" data-bs-toggle="modal" data-bs-target="#tmUploadPriceModal" style="background-color:#26b9bd;border-color:#26b9bd;">
                            <i class="fas fa-dollar-sign me-1"></i> Upload Price Data
                        </button>
                    </div>

                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2 ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-primary fs-6 p-2" id="tm-total-sales-badge" style="font-weight:700;">Sales: $0</span>
                            <span class="badge bg-warning fs-6 p-2" id="tm-total-ml30-badge" style="font-weight:700;color:#111;">ML30: 0</span>
                            <span class="badge bg-success fs-6 p-2" id="tm-total-profit-badge" style="font-weight:700;">Profit: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="tm-avg-gpft-badge" style="font-weight:700;color:#111;">GPFT: 0%</span>
                            <span class="badge bg-danger fs-6 p-2" id="tm-missing-badge" style="font-weight:700;">Missing L: 0</span>
                            <span class="badge fs-6 p-2" id="tm-map-badge" style="font-weight:700;background:#198754;color:#fff;">Map: 0</span>
                            <span class="badge fs-6 p-2" id="tm-nmap-badge" style="font-weight:700;background:#a71d2a;color:#fff;">N Map: 0</span>
                            <span class="badge fs-6 p-2" id="tm-zero-sold-badge" style="font-weight:700;background:#dc3545;color:#fff;">0 Sold: 0</span>
                            <span class="badge fs-6 p-2" id="tm-more-sold-badge" style="font-weight:700;background:#b6e0fe;color:#0f172a;">&gt;0 Sold: 0</span>
                            <span class="badge bg-secondary fs-6 p-2" id="tm-avg-roi-badge" style="font-weight:700;color:#111;">ROI: 0%</span>
                        </div>
                    </div>

                    <div id="tiendamia-products-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let table = null;
        let allRows = [];
        let tmDilColor = 'all';

        function money(v) {
            const n = parseFloat(v);
            if (!Number.isFinite(n)) return '—';
            return '$' + n.toFixed(2);
        }

        function tmStrictNMapFromMap(mapVal) {
            const s = (mapVal || '').trim();
            return s.startsWith('N Map');
        }

        function tmDilBucket(row) {
            const inv = parseFloat(row.inv) || 0;
            const ov = parseFloat(row.ov_l30) || 0;
            if (inv === 0) return 'all';
            const dil = (ov / inv) * 100;
            if (dil < 16.66) return 'red';
            if (dil < 25) return 'yellow';
            if (dil < 50) return 'green';
            return 'pink';
        }

        function updateSummary(rows) {
            let totalSales = 0, totalMl30 = 0, totalProfit = 0;
            let gpftSum = 0, gpftCount = 0;
            let roiSum = 0, roiCount = 0;
            let missingCount = 0, mapCount = 0, nmapCount = 0;
            let zeroSold = 0, moreSold = 0;

            rows.forEach(row => {
                if (row.is_parent) return;
                const isMissing = (row.missing || '').trim().toUpperCase() === 'M';
                const ml30 = parseFloat(row.al30 != null ? row.al30 : row.m_l30) || 0;
                const profit = parseFloat(row.profit) || 0;
                const mapVal = (row.map || '').trim();

                if (!isMissing) {
                    totalProfit += ml30 * profit;
                    totalSales += parseFloat(row.sales) || 0;
                    const gpft = parseFloat(row.gpft);
                    if (Number.isFinite(gpft)) { gpftSum += gpft; gpftCount++; }
                    const groi = parseFloat(row.groi);
                    if (Number.isFinite(groi)) { roiSum += groi; roiCount++; }
                }

                totalMl30 += ml30;
                if (ml30 === 0) zeroSold++; else moreSold++;
                if (isMissing) missingCount++;
                if (!isMissing && mapVal === 'Map') mapCount++;
                else if (!isMissing && tmStrictNMapFromMap(mapVal)) nmapCount++;
            });

            const avgGpft = gpftCount > 0 ? gpftSum / gpftCount : 0;
            const avgRoi = roiCount > 0 ? roiSum / roiCount : 0;

            $('#tm-total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#tm-total-ml30-badge').text('ML30: ' + totalMl30.toLocaleString());
            $('#tm-total-profit-badge').text('Profit: $' + Math.round(totalProfit).toLocaleString());
            $('#tm-avg-gpft-badge').text('GPFT: ' + Math.round(avgGpft) + '%');
            $('#tm-missing-badge').text('Missing L: ' + missingCount.toLocaleString());
            $('#tm-map-badge').text('Map: ' + mapCount.toLocaleString());
            $('#tm-nmap-badge').text('N Map: ' + nmapCount.toLocaleString());
            $('#tm-zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
            $('#tm-more-sold-badge').text('>0 Sold: ' + moreSold.toLocaleString());
            $('#tm-avg-roi-badge').text('ROI: ' + Math.round(avgRoi) + '%');
        }

        function gpftMatchesBand(gpft, band) {
            if (band === 'all') return true;
            const v = parseFloat(gpft) || 0;
            if (band === 'negative') return v < 0;
            if (band === '0-10') return v >= 0 && v < 10;
            if (band === '10-20') return v >= 10 && v < 20;
            if (band === '20-30') return v >= 20 && v < 30;
            if (band === '30-40') return v >= 30 && v < 40;
            if (band === '40-50') return v >= 40 && v < 50;
            if (band === '50plus') return v >= 50;
            return true;
        }

        function roiMatchesBand(groi, band) {
            if (band === 'all') return true;
            const roi = parseFloat(groi) || 0;
            if (band === 'lt40') return roi < 40;
            if (band === '40-75') return roi >= 40 && roi < 75;
            if (band === '75-125') return roi >= 75 && roi < 125;
            if (band === '125-175') return roi >= 125 && roi < 175;
            if (band === '175-250') return roi >= 175 && roi < 250;
            if (band === 'gt250') return roi >= 250;
            return true;
        }

        function computeFiltered() {
            const rowType = $('#tm-row-type-filter').val();
            const invF = $('#tm-inv-filter').val();
            const stockF = $('#tm-stock-filter').val();
            const gpftF = $('#tm-gpft-filter').val();
            const roiF = $('#tm-roi-filter').val();
            const ml30F = $('#tm-ml30-filter').val();
            const mapF = $('#tm-map-filter').val();
            const q = ($('#tm-sku-search').val() || '').trim().toLowerCase();

            return allRows.filter(row => {
                if (row.is_parent) return true;

                const parent = (row.parent || '').trim();
                if (rowType === 'parents' && !parent) return false;
                if (rowType === 'skus' && parent) return false;

                const inv = parseInt(row.inv, 10) || 0;
                if (invF === 'zero' && inv !== 0) return false;
                if (invF === 'more' && inv <= 0) return false;

                const st = parseInt(row.tm_stock, 10) || 0;
                if (stockF === 'zero' && st !== 0) return false;
                if (stockF === 'more' && st <= 0) return false;

                if (ml30F !== 'all' && inv <= 0) return false;
                const ml30 = parseFloat(row.al30 != null ? row.al30 : row.m_l30) || 0;
                if (ml30F === '0' && ml30 !== 0) return false;
                if (ml30F === '0-10' && !(ml30 > 0 && ml30 <= 10)) return false;
                if (ml30F === '10plus' && !(ml30 > 10)) return false;

                if (!gpftMatchesBand(row.gpft, gpftF)) return false;
                if (!roiMatchesBand(row.groi, roiF)) return false;

                const mapVal = (row.map || '').trim();
                if (mapF === 'map' && mapVal !== 'Map') return false;
                if (mapF === 'nmap' && !tmStrictNMapFromMap(mapVal)) return false;

                if (tmDilColor !== 'all' && tmDilBucket(row) !== tmDilColor) return false;

                if (q && String(row.sku || '').toLowerCase().indexOf(q) === -1) return false;
                return true;
            });
        }

        function applyClientFilters() {
            if (!table) return;
            const filtered = computeFiltered();
            table.setData(filtered);
            updateSummary(filtered);
        }

        $(document).ready(function() {
            $(document).on('click', '.tm-dil-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.tm-manual-dropdown').toggleClass('show');
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.tm-manual-dropdown').length) {
                    $('.tm-manual-dropdown').removeClass('show');
                }
            });
            $(document).on('click', '.tm-dil-item', function(e) {
                e.preventDefault();
                const color = $(this).data('color') || 'all';
                tmDilColor = color;
                $('.tm-dil-item').removeClass('active');
                $(this).addClass('active');
                const sc = color === 'all' ? 'def' : color;
                $('#tm-dil-btn').find('.tm-sc').attr('class', 'tm-sc ' + sc);
                $('.tm-manual-dropdown').removeClass('show');
                applyClientFilters();
            });

            table = new Tabulator('#tiendamia-products-table', {
                ajaxURL: '{{ route('tiendamia.products.tabulator.data') }}',
                ajaxResponse: function(url, params, response) {
                    if (response && response.error) {
                        console.error(response.error);
                        allRows = [];
                        return [];
                    }
                    allRows = Array.isArray(response) ? response : [];
                    const filtered = computeFiltered();
                    updateSummary(filtered);
                    return filtered;
                },
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 200, 500],
                paginationCounter: 'rows',
                initialSort: [{ column: 'sku', dir: 'asc' }],
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        width: 120,
                        visible: false,
                        formatter: function(cell) {
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' +
                                String(v).replace(/</g, '&lt;') + '</span>';
                        }
                    },
                    {
                        title: 'Image',
                        field: 'image',
                        width: 60,
                        headerSort: false,
                        formatter: function(cell) {
                            const src = cell.getValue();
                            if (!src) return '';
                            return '<img src="' + String(src).replace(/"/g, '&quot;') + '" alt="" ' +
                                'style="width:44px;height:44px;object-fit:cover;border-radius:4px;" onerror="this.style.display=\'none\'">';
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        minWidth: 180,
                        frozen: true,
                        headerFilter: 'input',
                        cssClass: 'fw-bold text-primary',
                    },
                    {
                        title: 'INV',
                        field: 'inv',
                        width: 55,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue(), 10) || 0;
                            if (v === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + v + '</span>';
                        }
                    },
                    {
                        title: 'TM Stock',
                        field: 'tm_stock',
                        width: 65,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue(), 10) || 0;
                            if (v === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + v + '</span>';
                        }
                    },
                    {
                        title: 'OV L30',
                        field: 'ov_l30',
                        width: 60,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function(cell) {
                            return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'Dil',
                        field: 'dil_percent',
                        width: 55,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ov = parseFloat(row.ov_l30) || 0;
                            if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ov / inv) * 100;
                            const color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        }
                    },
                    {
                        title: 'ML30',
                        field: 'm_l30',
                        width: 55,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function(cell) {
                            return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'ML60',
                        field: 'm_l60',
                        width: 55,
                        hozAlign: 'center',
                        sorter: 'number',
                        formatter: function(cell) {
                            return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'Price',
                        field: 'price',
                        width: 72,
                        hozAlign: 'right',
                        sorter: 'number',
                        formatter: function(cell) { return money(cell.getValue()); }
                    },
                    {
                        title: 'LMP',
                        field: 'lmp',
                        hozAlign: 'center',
                        width: 56,
                        headerSort: false,
                        formatter: function() {
                            const redDot = '<span style="display:inline-flex;width:14px;height:14px;border-radius:50%;background:#dc3545;"></span>';
                            return '<span title="No LMP data for Tiendamia feed">' + redDot + '</span>';
                        }
                    },
                    {
                        title: 'Missing L',
                        field: 'missing',
                        width: 72,
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const value = (cell.getValue() || '').toString().trim().toUpperCase();
                            if (value === 'M') return '<span class="badge bg-danger">L</span>';
                            return '';
                        }
                    },
                    {
                        title: 'Map',
                        field: 'map',
                        hozAlign: 'center',
                        width: 90,
                        formatter: function(cell) {
                            const val = (cell.getValue() || '').trim();
                            if (val === 'Map') return '<span style="color:#198754;font-weight:bold;">Map</span>';
                            if (val.startsWith('N Map|')) {
                                const part = val.split('|')[1];
                                const ad = Math.abs(parseFloat(String(part || '').trim()) || 0);
                                if (Number.isFinite(ad) && ad <= 3) {
                                    return '<span style="color:#198754;font-weight:bold;">Map</span>';
                                }
                                return '<span style="color:#dc3545;font-weight:bold;">N Map (' + String(part).replace(/</g, '&lt;') + ')</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: 'GPFT',
                        field: 'gpft',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (!Number.isFinite(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0) return '0%';
                            const color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'GROI',
                        field: 'groi',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            let color;
                            if (v < 40) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v < 125) color = '#3591dc';
                            else if (v < 250) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'Profit',
                        field: 'profit',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) { return money(cell.getValue()); }
                    },
                    {
                        title: 'Sales',
                        field: 'sales',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) { return money(cell.getValue()); }
                    },
                    {
                        title: 'LP',
                        field: 'lp',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) { return money(cell.getValue()); }
                    },
                    {
                        title: 'Ship',
                        field: 'ship',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) { return money(cell.getValue()); }
                    },
                    {
                        title: 'WT ACT',
                        field: 'wt_act',
                        sorter: 'number',
                        hozAlign: 'right',
                        width: 78,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '<span style="color:#6c757d;">—</span>';
                            const n = parseFloat(v);
                            if (!Number.isFinite(n)) return '<span style="color:#6c757d;">—</span>';
                            return '<span style="font-weight:600;">' + n.toFixed(2) + '</span>';
                        }
                    },
                    {
                        title: 'M Ship',
                        field: 'm_ship',
                        sorter: 'number',
                        hozAlign: 'right',
                        width: 78,
                        formatter: function(cell) { return money(cell.getValue()); }
                    },
                    {
                        title: 'Sprice',
                        field: 'sprice',
                        sorter: 'number',
                        hozAlign: 'right',
                        editor: 'number',
                        editorParams: { min: 0, step: 0.01 },
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue()) || 0;
                            return '<span style="font-weight:600;padding:2px 6px;border-radius:3px;">' + money(v) + '</span>';
                        }
                    },
                    {
                        title: 'SPFT',
                        field: 'sgpft',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (!Number.isFinite(v) || v === 0) return '0%';
                            const color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'SROI',
                        field: 'sroi',
                        sorter: 'number',
                        hozAlign: 'right',
                        formatter: function(cell) {
                            const v = parseFloat(cell.getValue());
                            if (!Number.isFinite(v) || v === 0) return '0%';
                            let color;
                            if (v < 40) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v < 125) color = '#3591dc';
                            else if (v < 250) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                ],
            });

            function tmSaveSpriceUpdates(updates) {
                fetch('{{ route('tiendamia.products.save.sprice') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ updates: updates }),
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (!data.success && window.toastr) {
                        toastr.error(data.error || 'Save failed');
                    }
                }).catch(function() {
                    if (window.toastr) toastr.error('Save failed');
                });
            }

            table.on('cellEdited', function(cell) {
                if (cell.getField() !== 'sprice') return;
                const d = cell.getRow().getData();
                if (d.is_parent) return;
                const sku = d.sku;
                const sprice = parseFloat(cell.getValue()) || 0;
                const margin = parseFloat(d._margin) || 1;
                const lp = parseFloat(d.lp) || 0;
                const mShip = parseFloat(d.m_ship) || 0;
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - lp - mShip) / sprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((sprice * margin - lp - mShip) / lp) * 100) : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi });
                tmSaveSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            $('#tm-row-type-filter, #tm-inv-filter, #tm-stock-filter, #tm-gpft-filter, #tm-roi-filter, #tm-ml30-filter, #tm-map-filter')
                .on('change', applyClientFilters);
            let searchTimer = null;
            $('#tm-sku-search').on('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyClientFilters, 200);
            });

            const tmUploadModalEl = document.getElementById('tmUploadPriceModal');
            if (tmUploadModalEl) {
                tmUploadModalEl.addEventListener('show.bs.modal', function() {
                    $('#tm-upload-price-result').empty();
                    $('#tm-price-file').val('');
                });
            }

            $('#tm-upload-price-btn').on('click', function() {
                const $inp = $('#tm-price-file');
                const $out = $('#tm-upload-price-result');
                const $btn = $('#tm-upload-price-btn');
                if (!$inp[0].files || !$inp[0].files.length) {
                    $out.html('<span class="text-danger">Choose a file first.</span>');
                    return;
                }
                const fd = new FormData();
                fd.append('price_file', $inp[0].files[0]);
                fd.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                $btn.prop('disabled', true);
                $out.html('<span class="text-muted">Uploading…</span>');
                fetch('{{ route('tiendamia.products.upload.price') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                    .then(function(res) {
                        const j = res.j;
                        if (!res.ok || !j.success) {
                            $out.html('<span class="text-danger">' + (j.error || j.message || 'Upload failed') + '</span>');
                            if (window.toastr) toastr.error(j.error || 'Upload failed');
                            return;
                        }
                        const msg = 'Stored ' + j.rows_stored + ' rows. Updated ' + j.products_updated + ' products. ' +
                            'Skipped (no SKU match): ' + j.skipped_no_matching_sku + ', open box: ' + j.skipped_open_box + '.';
                        if (window.toastr) toastr.success(msg);
                        if (tmUploadModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            const inst = bootstrap.Modal.getInstance(tmUploadModalEl);
                            if (inst) inst.hide();
                        }
                        if (j.products_updated > 0 || j.rows_stored > 0) {
                            table.setData('{{ route('tiendamia.products.tabulator.data') }}');
                        }
                    })
                    .catch(function() {
                        $out.html('<span class="text-danger">Network error.</span>');
                        if (window.toastr) toastr.error('Network error');
                    })
                    .finally(function() {
                        $btn.prop('disabled', false);
                    });
            });

            $('#tm-refresh-btn').on('click', function() {
                table.setData('{{ route('tiendamia.products.tabulator.data') }}');
            });

            $('#tm-export-btn').on('click', function() {
                const name = 'tiendamia_princing_' + new Date().toISOString().slice(0, 10) + '.csv';
                table.download('csv', name);
            });
        });
    </script>
@endsection
