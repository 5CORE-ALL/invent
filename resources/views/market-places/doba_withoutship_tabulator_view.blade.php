@extends('layouts.vertical', ['title' => 'Doba pickup / prepaid label', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* ── #doba-withoutship-table: match /aliexpress-pricing spacing, headers, parent rows, footer ── */
        #doba-withoutship-table.tabulator {
            border: 1px solid #dee2e6 !important;
            border-radius: 8px !important;
            font-size: 12px !important;
        }
        #doba-withoutship-table .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        #doba-withoutship-table .tabulator-row {
            min-height: 50px;
        }
        #doba-withoutship-table .tabulator-cell {
            padding: 10px 12px !important;
            vertical-align: middle !important;
        }
        #doba-withoutship-table .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        #doba-withoutship-table .tabulator-header .tabulator-col {
            height: 80px !important;
        }
        #doba-withoutship-table .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #doba-withoutship-table .tabulator-row.nr-hide {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }
        #doba-withoutship-table .tabulator-row.parent-row,
        #doba-withoutship-table .tabulator-row.parent-row .tabulator-cell {
            background-color: #bde0ff !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        #doba-withoutship-table .tabulator-row.parent-row .tabulator-cell {
            padding-top: 8px !important;
            padding-bottom: 8px !important;
            overflow: visible !important;
            vertical-align: middle !important;
            color: #1e3a5f;
        }
        #doba-withoutship-table .tabulator-row.parent-row:hover,
        #doba-withoutship-table .tabulator-row.parent-row:hover .tabulator-cell {
            background-color: #93c5fd !important;
        }
        #doba-withoutship-table .tabulator-cell[tabulator-field="thumb_image"],
        #doba-withoutship-table .tabulator-cell.tabulator-field-thumb_image {
            overflow: hidden;
            padding: 8px 10px !important;
        }
        #doba-withoutship-table .tabulator-footer {
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 10px !important;
        }
        #doba-withoutship-table .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        #doba-withoutship-table .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important;
            font-weight: 500 !important;
            min-width: 36px !important;
            height: 36px !important;
            line-height: 36px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            color: #475569 !important;
            cursor: pointer;
            transition: all 0.15s ease !important;
            text-align: center !important;
        }
        #doba-withoutship-table .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }
        #doba-withoutship-table .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }
        #doba-withoutship-table .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important;
            cursor: not-allowed !important;
        }

        /* Color coded cells */
        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            color: #dc3545;
        }

        .dil-percent-value.blue {
            color: #3591dc;
        }

        .dil-percent-value.yellow {
            color: #ffc107;
        }

        .dil-percent-value.green {
            color: #28a745;
        }

        .dil-percent-value.pink {
            color: #e83e8c;
        }

        .dil-percent-value.gray {
            color: #6c757d;
        }

        /* Status circles for DIL filter */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-circle.default {
            background-color: #6c757d;
        }
        .status-circle.red {
            background-color: #dc3545;
        }
        .status-circle.yellow {
            background-color: #ffc107;
        }
        .status-circle.green {
            background-color: #28a745;
        }
        .status-circle.pink {
            background-color: #e83e8c;
        }
        .manual-dropdown-container .dropdown-menu {
            z-index: 1050;
        }
        .column-filter.active {
            background-color: #e7f3ff;
            font-weight: bold;
        }

        /* SKU Tooltips */
        .sku-tooltip-container {
            position: relative;
            display: inline-block;
        }

        .sku-tooltip {
            visibility: hidden;
            width: auto;
            min-width: 120px;
            background-color: #fff;
            color: #333;
            text-align: left;
            border-radius: 4px;
            padding: 8px;
            position: absolute;
            z-index: 1001;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .sku-tooltip-container:hover .sku-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .sku-link {
            padding: 4px 0;
            white-space: nowrap;
        }

        .sku-link a {
            color: #0d6efd;
            text-decoration: none;
        }

        .sku-link a:hover {
            text-decoration: underline;
        }

        /* Image column: thumb in cell; large preview is #dws-img-hover-preview (fixed, avoids frozen-col / overflow clip) */
        .dws-img-hover-wrap {
            display: inline-block;
            width: fit-content;
            max-width: 100%;
            line-height: 0;
            vertical-align: middle;
        }
        .dws-img-hover-wrap .dws-thumb-sm {
            display: block;
            cursor: zoom-in;
        }
        #dws-img-hover-preview {
            position: fixed;
            display: none;
            z-index: 200050;
            pointer-events: none;
            width: min(480px, 92vw);
            height: min(480px, 85vh);
            object-fit: contain;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        }
        .dws-copy-sku {
            color: #0d6efd !important;
            vertical-align: middle;
            line-height: 1;
        }
        .dws-copy-sku:hover {
            color: #0a58ca !important;
        }

        #dws-footer-visible-rows {
            font-weight: 600;
            color: #4361ee;
            font-size: 13px;
            white-space: nowrap;
        }

        /* DIL dropdown (manual, like AliExpress pricing) */
        .dws-manual-dropdown { position: relative; display: inline-block; }
        .dws-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
        }
        .dws-manual-dropdown.show .dropdown-menu { display: block; }
        .dws-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .dws-dropdown-item:hover { background: #e9ecef; }
        .dws-sc { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 6px; border: 1px solid #ddd; }
        .dws-sc.def { background: #6c757d; }
        .dws-sc.red { background: #dc3545; }
        .dws-sc.yellow { background: #ffc107; }
        .dws-sc.green { background: #28a745; }
        .dws-sc.pink { background: #e83e8c; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Doba Listing — Pickup with prepaid label',
        'sub_title' => 'doba_daily_data: order type = Pickup with a prepaid label only',
    ])
    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">

                {{-- Row 1: filters + DIL; Search SKU aligned right (/aliexpress-pricing style) --}}
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2 w-100">
                    <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                    <select id="dws-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                        <option value="all" selected>All Rows</option>
                        <option value="parents">Parents</option>
                        <option value="skus">SKUs</option>
                    </select>

                    <select id="dws-inv-filter" class="form-select form-select-sm" style="width:140px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>&gt; 0</option>
                    </select>

                    <select id="dws-ovl30-filter" class="form-select form-select-sm" style="width:140px;" title="Shopify OV L30">
                        <option value="all">OV L30</option>
                        <option value="zero">0 OV L30</option>
                        <option value="more">OV L30 &gt; 0</option>
                    </select>

                    <select id="dws-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0–10%</option>
                        <option value="10-20">10–20%</option>
                        <option value="20-30">20–30%</option>
                        <option value="30-40">30–40%</option>
                        <option value="40-50">40–50%</option>
                        <option value="50-60">50–60%</option>
                        <option value="60plus">60%+</option>
                    </select>

                    <select id="dws-roi-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="125-250">125–250%</option>
                        <option value="gt250">&gt; 250%</option>
                    </select>

                    <select id="dws-al30-filter" class="form-select form-select-sm" style="width:130px;" title="Doba L30 sold; excludes 0 inventory when filtering buckets">
                        <option value="all">L30</option>
                        <option value="0">0</option>
                        <option value="0-10">1–10</option>
                        <option value="10plus">10+</option>
                    </select>

                    <select id="dws-map-filter" class="form-select form-select-sm" style="width:120px;">
                        <option value="all">Map</option>
                        <option value="map">Map only</option>
                        <option value="nmap">N Map only</option>
                    </select>

                    <select id="dws-growth-sign-filter" class="form-select form-select-sm" style="width: 150px;"
                            title="Doba L30 vs L60 sales growth % (same as Growth column)">
                        <option value="all" selected>Growth</option>
                        <option value="negative">Growth &lt; 0</option>
                        <option value="zero">Growth = 0</option>
                        <option value="positive">Growth &gt; 0</option>
                    </select>

                    <div class="dws-manual-dropdown">
                        <button class="btn btn-light btn-sm dws-dil-toggle" type="button" id="dws-dil-btn">
                            <span class="dws-sc def"></span>DIL%
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dws-dropdown-item dws-dil-item active" href="#" data-color="all">
                                <span class="dws-sc def"></span>All DIL</a></li>
                            <li><a class="dws-dropdown-item dws-dil-item" href="#" data-color="red">
                                <span class="dws-sc red"></span>Red (&lt;16.7%)</a></li>
                            <li><a class="dws-dropdown-item dws-dil-item" href="#" data-color="yellow">
                                <span class="dws-sc yellow"></span>Yellow (16.7–25%)</a></li>
                            <li><a class="dws-dropdown-item dws-dil-item" href="#" data-color="green">
                                <span class="dws-sc green"></span>Green (25–50%)</a></li>
                            <li><a class="dws-dropdown-item dws-dil-item" href="#" data-color="pink">
                                <span class="dws-sc pink"></span>Pink (50%+)</a></li>
                        </ul>
                    </div>
                    </div>
                    <input type="text" id="dws-sku-search" class="form-control form-control-sm" style="max-width:220px; min-width:180px;" placeholder="Search SKU..." autocomplete="off">
                </div>

                {{-- Row 2: actions (same order feel as /aliexpress-pricing) --}}
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" id="reload-data-btn" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-refresh"></i> Reload
                    </button>
                    <button type="button" id="export-csv-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" id="column-visibility-btn">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu" id="column-dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                            <button class="dropdown-item" type="button" id="show-all-columns-btn">Show All Columns</button>
                            <div class="dropdown-divider"></div>
                        </div>
                    </div>
                    <button id="dws-price-mode-btn" type="button" class="btn btn-sm btn-secondary"
                            title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>
                    <button id="push-to-doba-btn" class="btn btn-sm btn-primary" style="display: none;">
                        <i class="fas fa-upload"></i> Push to Doba
                    </button>
                </div>

                {{-- Row 3: summary KPIs (/aliexpress-pricing style) --}}
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <span id="total-skus" class="badge bg-primary fs-6 p-2" style="font-weight:700; color: white !important;">Total SKUs: 0</span>
                        <span id="zero-sold-count" class="badge bg-danger fs-6 p-2" style="font-weight:700; color: white !important;">L30 0 Sold: 0</span>
                        <span id="sold-count" class="badge bg-success fs-6 p-2" style="font-weight:700; color: white !important;">SOLD: 0</span>
                        <span id="missing-count" class="badge fs-6 p-2" style="background-color: #b02a37; color: white !important; font-weight:700; cursor: pointer;" title="Click to filter missing items"><i class="fas fa-exclamation-triangle"></i> Missing: 0</span>
                        <span id="disc-vs-amz-count" class="badge fs-6 p-2" style="background-color: #dc3545; color: white !important; font-weight:700; cursor: pointer;" title="Click to filter non-competitive items"><i class="fas fa-chart-line"></i> VS AMZ: 0</span>
                        <span id="growth-sales-badge" class="badge fs-6 p-2" style="background-color: #28a745; color: white; font-weight:700;">GROWTH: 0%</span>
                        <span id="pft-percentage-badge" class="badge bg-danger fs-6 p-2" style="color: white; font-weight:700;">L30 GPFT %: 0%</span>
                        <span id="growth-gpft-percent-badge" class="badge fs-6 p-2" style="background-color: #198754; color: white; font-weight:700;">GROWTH GPFT %: 0%</span>
                        <span id="roi-percentage-badge" class="badge fs-6 p-2" style="background-color: #6f42c1; color: white; font-weight:700;">L30 ROI %: 0%</span>
                        <span id="pft-total-badge" class="badge bg-dark fs-6 p-2" style="color: white; font-weight:700;">L30 GPFT: $0</span>
                        <span id="growth-gpft-badge" class="badge fs-6 p-2" style="background-color: #28a745; color: white; font-weight:700;">GROWTH GPFT: $0</span>
                        <span id="total-cogs-badge" class="badge bg-secondary fs-6 p-2" style="color: white; font-weight:700;">Total COGS: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="dws-discount-type-block" class="d-flex align-items-center gap-2">
                            <label class="mb-0 fw-bold">Type:</label>
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 130px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter percentage" step="0.01" min="0"
                            style="width: 150px; display: inline-block;">
                        <button id="apply-discount-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button id="clear-sprice-selected-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-eraser"></i> Clear SPRICE
                        </button>
                        <button id="sugg-amz-30-btn" class="btn btn-sm btn-warning">
                            <i class="fab fa-amazon"></i> SUGG AMZ - 30%
                        </button>
                        <button id="sugg-amz-25-btn" class="btn btn-sm btn-info">
                            <i class="fab fa-amazon"></i> SUGG AMZ - 25%
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div id="doba-withoutship-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div id="doba-withoutship-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "doba_tabulator_column_visibility";
        /** Omit ship in PFT/ROI/GPFT/promo/SPFT/self-pick math; LP column shows product LP (COGS). */
        const FORMULA_SHIP = 0;

        function dwsEscapeHtml(s) {
            if (s == null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
        function dwsAttrEscape(s) {
            if (s == null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false; // Fixed SPRICE for all selected (merged into Price % cycle)
        let selectedSkus = new Set(); // Track selected SKUs across all pages
        let discVsAmzFilterActive = false; // Track DISC VS AMZ filter state
        let missingBadgeFilterActive = false; // Missing badge: INV > 0 and Doba L30 === 0

        $(document).ready(function() {

            (function initDwsImageHoverPreview() {
                const popup = document.createElement('img');
                popup.id = 'dws-img-hover-preview';
                popup.alt = '';
                document.body.appendChild(popup);
                let moveRaf = false;
                let clientX = 0;
                let clientY = 0;
                function positionPopup() {
                    popup.style.top = (clientY + 16) + 'px';
                    popup.style.left = (clientX + 16) + 'px';
                }
                $(document).on('mouseenter', '#doba-withoutship-table .dws-img-hover-wrap', function (e) {
                    const sm = this.querySelector('.dws-thumb-sm');
                    if (!sm || !sm.getAttribute('src')) return;
                    popup.src = sm.src;
                    popup.style.display = 'block';
                    clientX = e.clientX;
                    clientY = e.clientY;
                    positionPopup();
                });
                $(document).on('mousemove', '#doba-withoutship-table .dws-img-hover-wrap', function (e) {
                    if (popup.style.display !== 'block') return;
                    clientX = e.clientX;
                    clientY = e.clientY;
                    if (moveRaf) return;
                    moveRaf = true;
                    requestAnimationFrame(function () {
                        moveRaf = false;
                        positionPopup();
                    });
                });
                $(document).on('mouseleave', '#doba-withoutship-table .dws-img-hover-wrap', function () {
                    popup.style.display = 'none';
                });
            })();

            $(document).on('click', '.dws-copy-sku', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const text = $(this).attr('data-sku');
                if (text == null || text === '') return;
                const done = function() {
                    showToast('success', 'Copied: ' + text);
                };
                const fail = function() {
                    showToast('danger', 'Could not copy to clipboard');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function() {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        try {
                            document.execCommand('copy');
                            done();
                        } catch (err) {
                            fail();
                        }
                        document.body.removeChild(ta);
                    });
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        document.execCommand('copy');
                        done();
                    } catch (err) {
                        fail();
                    }
                    document.body.removeChild(ta);
                }
            });

            $('#dws-sku-search').on('input', function() {
                applyFilters();
            });

            // Discount type dropdown change handler
            $('#discount-type-select').on('change', function() {
                if (samePriceModeActive) {
                    return;
                }
                const type = $(this).val();
                const $input = $('#discount-percentage-input');
                
                if (type === 'percentage') {
                    $input.attr('placeholder', 'Enter percentage');
                    $input.attr('max', '100');
                } else {
                    $input.attr('placeholder', 'Enter value');
                    $input.removeAttr('max');
                }
            });

            function syncDwsDiscountBarForMode() {
                const $inp = $('#discount-percentage-input');
                if (samePriceModeActive) {
                    $('#dws-discount-type-block').addClass('d-none');
                    $('#discount-input-label').text('Price ($):');
                    $inp.attr('placeholder', 'Enter SPRICE for all selected');
                    $inp.removeAttr('max');
                } else {
                    $('#dws-discount-type-block').removeClass('d-none');
                    $('#discount-input-label').text('Value:');
                    const type = $('#discount-type-select').val();
                    if (type === 'percentage') {
                        $inp.attr('placeholder', 'Enter percentage');
                        $inp.attr('max', '100');
                    } else {
                        $inp.attr('placeholder', 'Enter value');
                        $inp.removeAttr('max');
                    }
                }
            }

            function syncDwsPriceModeUi() {
                const $btn = $('#dws-price-mode-btn');
                const selectColumn = table.getColumn('_select');
                syncDwsDiscountBarForMode();
                if (decreaseModeActive) {
                    $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                    selectColumn.show();
                    return;
                }
                if (increaseModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                        .html('<i class="fas fa-arrow-up"></i> Increase ON');
                    selectColumn.show();
                    return;
                }
                if (samePriceModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                        .html('<i class="fas fa-equals"></i> Same Price ON');
                    selectColumn.show();
                    return;
                }
                $btn.removeClass('btn-danger btn-success btn-outline-primary').addClass('btn-secondary')
                    .html('<i class="fas fa-exchange-alt"></i> Price %');
                selectColumn.hide();
                selectedSkus.clear();
                $('.sku-select-checkbox').prop('checked', false);
                $('#select-all-checkbox').prop('checked', false);
                $('#discount-input-container').hide();
                updateSelectedCount();
            }

            // Single control: Off → Decrease → Increase → Same Price → Off
            $('#dws-price-mode-btn').on('click', function() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    decreaseModeActive = true;
                } else if (decreaseModeActive) {
                    decreaseModeActive = false;
                    increaseModeActive = true;
                } else if (increaseModeActive) {
                    increaseModeActive = false;
                    samePriceModeActive = true;
                } else {
                    samePriceModeActive = false;
                }
                syncDwsPriceModeUi();
            });

            // Checkbox change handler - track selected SKUs
            $(document).on('change', '.sku-select-checkbox', function() {
                const sku = $(this).data('sku');
                const isChecked = $(this).prop('checked');
                
                if (isChecked) {
                    selectedSkus.add(sku);
                } else {
                    selectedSkus.delete(sku);
                }
                
                updateSelectedCount();
                updateSelectAllCheckbox();
                updatePushButtonVisibility(); // Update push button count when selection changes
            });

            // Update selected count and discount input visibility
            function updateSelectedCount() {
                const selectedCount = selectedSkus.size;
                
                if (selectedCount > 0) {
                    $('#discount-input-container').show();
                    $('#selected-skus-count').text(`(${selectedCount} SKU${selectedCount > 1 ? 's' : ''} selected)`);
                } else {
                    $('#discount-input-container').hide();
                }
            }

            // Update select all checkbox state based on current selections
            function updateSelectAllCheckbox() {
                if (!table) return;
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !row.is_parent);
                
                if (filteredData.length === 0) {
                    $('#select-all-checkbox').prop('checked', false);
                    return;
                }
                
                // Get all filtered SKUs
                const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
                
                // Check if all filtered SKUs are selected
                const allFilteredSelected = filteredSkus.size > 0 && 
                    Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
                
                $('#select-all-checkbox').prop('checked', allFilteredSelected);
            }

            // Select All checkbox handler
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !row.is_parent);
                
                // Add or remove all filtered SKUs from the selected set
                filteredData.forEach(row => {
                    const sku = row['(Child) sku'];
                    if (sku) {
                        if (isChecked) {
                            selectedSkus.add(sku);
                        } else {
                            selectedSkus.delete(sku);
                        }
                    }
                });
                
                // Update all visible checkboxes
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                
                updateSelectedCount();
                updatePushButtonVisibility(); // Update push button count when select all changes
            });

            // Apply Discount/Increase Button
            $('#apply-discount-btn').on('click', function() {
                const $applyBtn = $(this);

                if (selectedSkus.size === 0) {
                    showToast('danger', 'Please select at least one SKU');
                    return;
                }

                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('danger', 'Turn on Price % (Decrease, Increase, or Same Price)');
                    return;
                }

                if (samePriceModeActive) {
                    const inputValue = parseFloat($('#discount-percentage-input').val());
                    if (isNaN(inputValue) || inputValue < 0) {
                        showToast('danger', 'Please enter a valid price');
                        return;
                    }
                    const newPrice = parseFloat(inputValue.toFixed(2));
                    const ship = FORMULA_SHIP;
                    const selfPickValue = parseFloat(Math.max(0, newPrice - ship).toFixed(2));
                    const skusToProcess = Array.from(selectedSkus);
                    let currentIndex = 0;
                    let successCount = 0;
                    let errorCount = 0;
                    $applyBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

                    function processNextSamePrice() {
                        if (currentIndex >= skusToProcess.length) {
                            $applyBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Apply');
                            if (successCount > 0) {
                                showToast('success', `Same price $${newPrice.toFixed(2)} applied to ${successCount} SKU(s)`);
                            }
                            if (errorCount > 0) {
                                showToast('warning', `${errorCount} SKU(s) could not be updated`);
                            }
                            updatePushButtonVisibility();
                            return;
                        }
                        const sku = skusToProcess[currentIndex];
                        let row = null;
                        table.getRows().forEach(r => {
                            if (r.getData()['(Child) sku'] === sku) {
                                row = r;
                            }
                        });
                        if (!row || row.getData().is_parent) {
                            errorCount++;
                            currentIndex++;
                            setTimeout(processNextSamePrice, 50);
                            return;
                        }
                        const rowData = row.getData();
                        row.update({ apply_status: 'applying' });
                        const lp = parseFloat(rowData.LP_productmaster) || 0;
                        let spftValue = 0;
                        let sroiValue = 0;
                        if (newPrice > 0 && lp > 0) {
                            spftValue = ((newPrice * 0.95) - ship - lp) / newPrice * 100;
                            sroiValue = ((newPrice * 0.95) - ship - lp) / lp * 100;
                        }
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku,
                                sprice: newPrice,
                                spft_percent: spftValue.toFixed(2),
                                sroi_percent: sroiValue.toFixed(2),
                                s_self_pick: selfPickValue
                            },
                            success: function() {
                                row.update({
                                    sprice: newPrice,
                                    s_self_pick: selfPickValue,
                                    self_pick_price: selfPickValue,
                                    spft: spftValue,
                                    sroi: sroiValue,
                                    apply_status: 'applied'
                                });
                                successCount++;
                                currentIndex++;
                                setTimeout(processNextSamePrice, 100);
                            },
                            error: function(xhr) {
                                console.error('Same price save error:', sku, xhr.responseText);
                                row.update({ apply_status: 'error' });
                                errorCount++;
                                currentIndex++;
                                setTimeout(processNextSamePrice, 100);
                            }
                        });
                    }
                    processNextSamePrice();
                    return;
                }

                const inputValue = parseFloat($('#discount-percentage-input').val());
                
                if (isNaN(inputValue) || inputValue < 0) {
                    showToast('danger', 'Please enter a valid positive number');
                    return;
                }
                
                const mode = increaseModeActive ? 'increase' : 'decrease';
                const discountType = $('#discount-type-select').val();
                let successCount = 0;
                let errorCount = 0;
                let totalToProcess = selectedSkus.size;
                const skusToProcess = Array.from(selectedSkus);
                let currentIndex = 0;
                
                // Disable button during processing
                $applyBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                
                // Process SKUs sequentially (like Amazon)
                function processNextSku() {
                    if (currentIndex >= skusToProcess.length) {
                        // All done
                        $('#apply-discount-btn').prop('disabled', false).html('<i class="fas fa-check"></i> Apply');
                        
                        if (successCount > 0) {
                            const action = mode === 'decrease' ? 'decreased' : 'increased';
                            showToast('success', `Successfully ${action} SPRICE for ${successCount} SKU(s)`);
                        }
                        if (errorCount > 0) {
                            showToast('warning', `${errorCount} SKU(s) could not be updated`);
                        }
                        
                        // DON'T clear selections - keep checkboxes checked like Amazon
                        // Update Push to Doba button visibility
                        updatePushButtonVisibility();
                        return;
                    }
                    
                    const sku = skusToProcess[currentIndex];
                    
                    // Find the row
                    let row = null;
                    table.getRows().forEach(r => {
                        if (r.getData()['(Child) sku'] === sku) {
                            row = r;
                        }
                    });
                    
                    if (row) {
                        const rowData = row.getData();
                        const currentPrice = parseFloat(rowData.self_pick_price) || 0;
                        
                        if (currentPrice <= 0) {
                            row.update({ apply_status: 'error' });
                            errorCount++;
                            currentIndex++;
                            processNextSku();
                            return;
                        }
                        
                        // Show clock icon while processing
                        row.update({ apply_status: 'applying' });
                        
                        let newPrice;
                        if (discountType === 'percentage') {
                            const adjustmentAmount = currentPrice * (inputValue / 100);
                            newPrice = mode === 'decrease' 
                                ? currentPrice - adjustmentAmount 
                                : currentPrice + adjustmentAmount;
                        } else {
                            newPrice = mode === 'decrease' 
                                ? currentPrice - inputValue 
                                : currentPrice + inputValue;
                        }
                        
                        newPrice = Math.max(0, newPrice);
                        newPrice = parseFloat(newPrice.toFixed(2));
                        
                        // Calculate Self Pick Price = SPRICE - SHIP (ship excluded on this page)
                        const ship = FORMULA_SHIP;
                        const calculatedSelfPick = Math.max(0, newPrice - ship);
                        const selfPickValue = parseFloat(calculatedSelfPick.toFixed(2));
                        
                        // Calculate SPFT and SROI with new price
                        const lp = parseFloat(rowData.LP_productmaster) || 0;
                        
                        let spftValue = 0;
                        let sroiValue = 0;
                        if (newPrice > 0 && lp > 0) {
                            spftValue = ((newPrice * 0.95) - ship - lp) / newPrice * 100;
                            sroiValue = ((newPrice * 0.95) - ship - lp) / lp * 100;
                        }
                        
                        // Save to database
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku,
                                sprice: newPrice,
                                spft_percent: spftValue.toFixed(2),
                                sroi_percent: sroiValue.toFixed(2),
                                s_self_pick: selfPickValue
                            },
                            success: function(response) {
                                // Update row with new values and show double tick
                                row.update({ 
                                    sprice: newPrice, 
                                    s_self_pick: selfPickValue,
                                    spft: spftValue,
                                    sroi: sroiValue,
                                    apply_status: 'applied' 
                                });
                                successCount++;
                                currentIndex++;
                                // Small delay before next to avoid overwhelming
                                setTimeout(processNextSku, 100);
                            },
                            error: function(xhr) {
                                console.error('Save error for SKU:', sku, xhr.responseText);
                                row.update({ apply_status: 'error' });
                                errorCount++;
                                currentIndex++;
                                setTimeout(processNextSku, 100);
                            }
                        });
                    } else {
                        errorCount++;
                        currentIndex++;
                        processNextSku();
                    }
                }
                
                // Start processing
                processNextSku();
            });

            // Allow Enter key to apply discount
            $('#discount-percentage-input').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#apply-discount-btn').click();
                }
            });

            // Clear SPRICE button handler
            $('#clear-sprice-selected-btn').on('click', function() {
                if (selectedSkus.size === 0) {
                    showToast('danger', 'Please select SKUs first');
                    return;
                }

                if (confirm('Are you sure you want to clear SPRICE for ' + selectedSkus.size + ' selected SKU(s)?')) {
                    let clearedCount = 0;

                    selectedSkus.forEach(sku => {
                        const rows = table.searchRows("(Child) sku", "=", sku);
                        
                        if (rows.length > 0) {
                            const row = rows[0];
                            row.update({
                                sprice: 0,
                                spft: 0,
                                sroi: 0,
                                s_self_pick: 0
                            });
                            
                            row.reformat();
                            
                            // Save to database
                            $.ajax({
                                url: '/doba/save-sprice',
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    sku: sku,
                                    sprice: 0,
                                    spft_percent: 0,
                                    sroi_percent: 0,
                                    s_self_pick: 0,
                                    push_status: null,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function() {
                                    console.log('SPRICE cleared for SKU:', sku);
                                },
                                error: function(xhr) {
                                    console.error('Failed to clear SPRICE for SKU:', sku, xhr.responseText);
                                }
                            });
                            
                            clearedCount++;
                        }
                    });

                    showToast('success', 'SPRICE cleared for ' + clearedCount + ' SKU(s)');
                }
            });

            // SUGG AMZ - 30% button handler
            $('#sugg-amz-30-btn').on('click', function() {
                if (selectedSkus.size === 0) {
                    showToast('danger', 'Please select SKUs first');
                    return;
                }

                let appliedCount = 0;
                let noAmazonPriceCount = 0;

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows("(Child) sku", "=", sku);
                    
                    if (rows.length > 0) {
                        const row = rows[0];
                        const rowData = row.getData();
                        const amazonPrice = parseFloat(rowData.amazon_price) || 0;
                        
                        if (amazonPrice === 0) {
                            noAmazonPriceCount++;
                            return; // Skip this SKU if no Amazon price
                        }
                        
                        // Calculate suggested price: Amazon Price - 30%
                        const suggestedPrice = amazonPrice * 0.70;
                        const lp = parseFloat(rowData.LP_productmaster) || 0;
                        const ship = FORMULA_SHIP;
                        
                        // Calculate SPFT and SROI
                        const spftValue = suggestedPrice > 0 ? ((suggestedPrice * 0.95) - ship - lp) / suggestedPrice * 100 : 0;
                        const sroiValue = lp > 0 ? (((suggestedPrice * 0.95) - ship - lp) / lp) * 100 : 0;
                        const selfPickValue = suggestedPrice * 0.95;
                        
                        // Update row
                        row.update({
                            sprice: suggestedPrice.toFixed(2),
                            spft: spftValue.toFixed(2),
                            sroi: sroiValue.toFixed(2),
                            s_self_pick: selfPickValue.toFixed(2)
                        });
                        
                        row.reformat();
                        
                        // Save to database
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                sprice: suggestedPrice.toFixed(2),
                                spft_percent: spftValue.toFixed(2),
                                sroi_percent: sroiValue.toFixed(2),
                                s_self_pick: selfPickValue.toFixed(2),
                                push_status: null,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function() {
                                console.log('Amazon - 30% price applied for SKU:', sku);
                            },
                            error: function(xhr) {
                                console.error('Failed to save Amazon - 30% price for SKU:', sku, xhr.responseText);
                            }
                        });
                        
                        appliedCount++;
                    }
                });

                if (appliedCount > 0) {
                    showToast('success', `Amazon - 30% price applied to ${appliedCount} SKU(s)`);
                }
                if (noAmazonPriceCount > 0) {
                    showToast('warning', `${noAmazonPriceCount} SKU(s) skipped (no Amazon price)`);
                }
            });

            // SUGG AMZ - 25% button handler
            $('#sugg-amz-25-btn').on('click', function() {
                if (selectedSkus.size === 0) {
                    showToast('danger', 'Please select SKUs first');
                    return;
                }

                let appliedCount = 0;
                let noAmazonPriceCount = 0;

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows("(Child) sku", "=", sku);
                    
                    if (rows.length > 0) {
                        const row = rows[0];
                        const rowData = row.getData();
                        const amazonPrice = parseFloat(rowData.amazon_price) || 0;
                        
                        if (amazonPrice === 0) {
                            noAmazonPriceCount++;
                            return; // Skip this SKU if no Amazon price
                        }
                        
                        // Calculate suggested price: Amazon Price - 25%
                        const suggestedPrice = amazonPrice * 0.75;
                        const lp = parseFloat(rowData.LP_productmaster) || 0;
                        const ship = FORMULA_SHIP;
                        
                        // Calculate SPFT and SROI
                        const spftValue = suggestedPrice > 0 ? ((suggestedPrice * 0.95) - ship - lp) / suggestedPrice * 100 : 0;
                        const sroiValue = lp > 0 ? (((suggestedPrice * 0.95) - ship - lp) / lp) * 100 : 0;
                        const selfPickValue = suggestedPrice * 0.95;
                        
                        // Update row
                        row.update({
                            sprice: suggestedPrice.toFixed(2),
                            spft: spftValue.toFixed(2),
                            sroi: sroiValue.toFixed(2),
                            s_self_pick: selfPickValue.toFixed(2)
                        });
                        
                        row.reformat();
                        
                        // Save to database
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                sprice: suggestedPrice.toFixed(2),
                                spft_percent: spftValue.toFixed(2),
                                sroi_percent: sroiValue.toFixed(2),
                                s_self_pick: selfPickValue.toFixed(2),
                                push_status: null,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function() {
                                console.log('Amazon - 25% price applied for SKU:', sku);
                            },
                            error: function(xhr) {
                                console.error('Failed to save Amazon - 25% price for SKU:', sku, xhr.responseText);
                            }
                        });
                        
                        appliedCount++;
                    }
                });

                if (appliedCount > 0) {
                    showToast('success', `Amazon - 25% price applied to ${appliedCount} SKU(s)`);
                }
                if (noAmazonPriceCount > 0) {
                    showToast('warning', `${noAmazonPriceCount} SKU(s) skipped (no Amazon price)`);
                }
            });

            // Save push status to database
            function savePushStatusToDatabase(sku, pushStatus, rowData) {
                return $.ajax({
                    url: '/doba/save-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: rowData.sprice || 0,
                        spft_percent: rowData.spft || 0,
                        sroi_percent: rowData.sroi || 0,
                        s_self_pick: rowData.s_self_pick || 0,
                        push_status: pushStatus,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }
                });
            }

            // Push price to Doba API with retry functionality (5 retries, 1 minute gap)
            function pushPriceToDobaWithRetry(sku, price, selfPickPrice = null, maxRetries = 5, delay = 5000) {
                return new Promise((resolve, reject) => {
                    let attempt = 0;
                    
                    function attemptPush() {
                        attempt++;
                        console.log(`Attempt ${attempt}/${maxRetries} for SKU ${sku}`);
                        
                        const requestData = {
                            sku: sku,
                            price: price,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        };
                        
                        // Add self_pick_price if provided
                        if (selfPickPrice !== null && selfPickPrice > 0) {
                            requestData.self_pick_price = selfPickPrice;
                        }
                        
                        $.ajax({
                            url: '/doba/push-price',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: requestData,
                            success: function(response) {
                                console.log(`Attempt ${attempt} response for SKU ${sku}:`, response);
                                
                                if (response.errors && response.errors.length > 0) {
                                    const errorMsg = response.errors[0].message || 'Unknown error';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                    
                                    if (attempt < maxRetries) {
                                        console.log(`Retry ${attempt + 1}/${maxRetries} for SKU ${sku} in ${delay/1000} seconds...`);
                                        setTimeout(attemptPush, delay);
                                    } else {
                                        console.error(`Max retries (${maxRetries}) reached for SKU ${sku}`);
                                        reject({ error: true, response: response, message: errorMsg });
                                    }
                                } else if (response.success === false) {
                                    const errorMsg = response.errors?.[0]?.message || 'Push failed';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                    
                                    if (attempt < maxRetries) {
                                        console.log(`Retry ${attempt + 1}/${maxRetries} for SKU ${sku} in ${delay/1000} seconds...`);
                                        setTimeout(attemptPush, delay);
                                    } else {
                                        console.error(`Max retries (${maxRetries}) reached for SKU ${sku}`);
                                        reject({ error: true, response: response, message: errorMsg });
                                    }
                                } else {
                                    console.log(`Successfully pushed price for SKU ${sku} on attempt ${attempt}`);
                                    resolve({ success: true, response: response });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                
                                if (attempt < maxRetries) {
                                    console.log(`Retry ${attempt + 1}/${maxRetries} for SKU ${sku} in ${delay/1000} seconds...`);
                                    setTimeout(attemptPush, delay);
                                } else {
                                    console.error(`Max retries (${maxRetries}) reached for SKU ${sku}`);
                                    reject({ error: true, xhr: xhr, message: errorMsg });
                                }
                            }
                        });
                    }
                    
                    attemptPush();
                });
            }

            // Push to Doba button handler
            $('#push-to-doba-btn').on('click', function() {
                // Get all SKUs that have SPRICE set
                const skusWithSprice = [];
                
                table.getRows().forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent && data.sprice && data.sprice > 0) {
                        skusWithSprice.push({
                            sku: data['(Child) sku'],
                            price: data.sprice,
                            selfPickPrice: data.s_self_pick || null, // Use calculated S (PP) (SPRICE - SHIP)
                            row: row
                        });
                    }
                });
                
                if (skusWithSprice.length === 0) {
                    showToast('warning', 'No SKUs with SPRICE found. Please set SPRICE first.');
                    return;
                }
                
                // Confirm before pushing
                if (!confirm(`Are you sure you want to push prices for ${skusWithSprice.length} SKU(s) to Doba?`)) {
                    return;
                }
                
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
                
                let currentIndex = 0;
                let successCount = 0;
                let errorCount = 0;
                
                function processNextSku() {
                    if (currentIndex >= skusWithSprice.length) {
                        // All done
                        $btn.prop('disabled', false).html(originalHtml);
                        
                        if (successCount > 0 && errorCount === 0) {
                            showToast('success', `Successfully pushed prices for ${successCount} SKU(s) to Doba`);
                        } else if (successCount > 0 && errorCount > 0) {
                            showToast('warning', `Pushed ${successCount} SKU(s), ${errorCount} failed`);
                        } else {
                            showToast('danger', `Failed to push prices for ${errorCount} SKU(s)`);
                        }
                        return;
                    }
                    
                    const { sku, price, selfPickPrice, row } = skusWithSprice[currentIndex];
                    
                    // Update status to show processing
                    $btn.html(`<i class="fas fa-spinner fa-spin"></i> ${currentIndex + 1}/${skusWithSprice.length}`);
                    
                    // Update row status to pushing
                    row.update({ push_status: 'pushing' });
                    
                    // 5 retries with 5 second gap
                    pushPriceToDobaWithRetry(sku, price, selfPickPrice, 5, 5000)
                        .then((result) => {
                            successCount++;
                            console.log(`SKU ${sku}: Price pushed successfully`);
                            row.update({ push_status: 'pushed', apply_status: null });
                            
                            // Force update the cell to show double tick immediately
                            const pushCell = row.getCell('_push');
                            if (pushCell) {
                                pushCell.getElement().innerHTML = '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Doba"></i>';
                            }
                            
                            // Get fresh row data for saving
                            const freshRowData = row.getData();
                            
                            // Save pushed status to database
                            savePushStatusToDatabase(sku, 'pushed', freshRowData)
                                .done(function() {
                                    console.log(`Push status 'pushed' saved to DB for SKU ${sku}`);
                                })
                                .fail(function(err) {
                                    console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                                });
                            
                            // Process next SKU with delay to avoid rate limiting
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                        })
                        .catch((error) => {
                            errorCount++;
                            console.error(`SKU ${sku}: Failed to push price after all retries`);
                            row.update({ push_status: 'error', apply_status: null });
                            
                            // Force update the cell to show error button
                            const pushCell = row.getCell('_push');
                            if (pushCell) {
                                pushCell.getElement().innerHTML = `<button class="push-single-btn" data-sku="${sku}" data-price="${price}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed - Click to retry">
                                    <i class="fa-solid fa-x"></i>
                                </button>`;
                            }
                            
                            // Uncheck the checkbox (like Amazon)
                            selectedSkus.delete(sku);
                            
                            // Get fresh row data for saving
                            const freshRowData = row.getData();
                            
                            // Save error status to database
                            savePushStatusToDatabase(sku, 'error', freshRowData)
                                .done(function() {
                                    console.log(`Push status 'error' saved to DB for SKU ${sku}`);
                                })
                                .fail(function(err) {
                                    console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                                });
                            
                            // Process next SKU with delay
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                        });
                }
                
                // Start processing
                processNextSku();
            });

            // Single push button click handler
            $(document).on('click', '.push-single-btn', function(e) {
                e.stopPropagation();
                
                const $btn = $(this);
                const sku = $btn.data('sku');
                const price = $btn.data('price');
                
                if (!sku || !price) {
                    showToast('danger', 'Invalid SKU or price');
                    return;
                }
                
                // Immediately show spinner on button (like Amazon)
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');
                
                // Find the row
                let row = null;
                table.getRows().forEach(r => {
                    if (r.getData()['(Child) sku'] === sku) {
                        row = r;
                    }
                });
                
                if (!row) {
                    showToast('danger', 'Row not found');
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fas fa-upload"></i>');
                    return;
                }
                
                // Get S (PP) from row data (calculated as SPRICE - SHIP)
                const rowData = row.getData();
                const selfPickPrice = rowData.s_self_pick || null;
                
                // Update status to pushing (this also updates the cell formatter)
                row.update({ push_status: 'pushing' });
                
                // 5 retries with 5 second gap
                pushPriceToDobaWithRetry(sku, price, selfPickPrice, 5, 5000)
                    .then((result) => {
                        // Success - update row immediately
                        row.update({ push_status: 'pushed', apply_status: null });
                        
                        // Force update the cell to show double tick immediately
                        const pushCell = row.getCell('_push');
                        if (pushCell) {
                            pushCell.getElement().innerHTML = '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Doba"></i>';
                        }
                        
                        // Get fresh row data for saving
                        const freshRowData = row.getData();
                        
                        // Save pushed status to database so it persists after refresh
                        savePushStatusToDatabase(sku, 'pushed', freshRowData)
                            .done(function() {
                                console.log(`Push status 'pushed' saved to DB for SKU ${sku}`);
                            })
                            .fail(function(err) {
                                console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                            });
                        
                        showToast('success', `Price pushed successfully for ${sku}`);
                    })
                    .catch((error) => {
                        // Failed after all retries - update row
                        row.update({ push_status: 'error', apply_status: null });
                        
                        // Force update the cell to show error button immediately
                        const pushCell = row.getCell('_push');
                        if (pushCell) {
                            pushCell.getElement().innerHTML = `<button class="push-single-btn" data-sku="${sku}" data-price="${price}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed - Click to retry">
                                <i class="fa-solid fa-x"></i>
                            </button>`;
                        }
                        
                        // Uncheck the checkbox (like Amazon)
                        selectedSkus.delete(sku);
                        
                        // Get fresh row data for saving
                        const freshRowData = row.getData();
                        
                        // Save error status to database so it persists after refresh
                        savePushStatusToDatabase(sku, 'error', freshRowData)
                            .done(function() {
                                console.log(`Push status 'error' saved to DB for SKU ${sku}`);
                            })
                            .fail(function(err) {
                                console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                            });
                        
                        const errorMsg = error.message || 'Unknown error after 5 retries';
                        showToast('danger', `Failed to push price for ${sku}: ${errorMsg}`);
                    });
            });

            table = new Tabulator("#doba-withoutship-table", {
                ajaxURL: "/doba-data-view-withoutship",
                ajaxConfig: "GET",
                ajaxResponse: function(url, params, response) {
                    // Process data exactly like doba_pricing_cvr.blade.php
                    if (response && response.data) {
                        const processedData = response.data.map((item, index) => {
                            const inv = Number(item.INV) || 0;
                            const l30 = Number(item.L30) || 0;
                            const dobaL30 = Number(item['doba L30']) || 0;
                            const dobaL60 = Number(item['doba L60']) || 0;
                            const dobaL45 = Number(item['doba L45']) || 0;
                            const quantityL7 = Number(item.quantity_l7) || 0;
                            const quantityL7Prev = Number(item.quantity_l7_prev) || 0;
                            const ovDil = inv > 0 ? l30 / inv : 0;
                            const selfPickPriceVal = Number(item.self_pick_price) || 0;
                            const price = selfPickPriceVal;
                            const listPriceCol = Number(item.doba_list_price) || 0;
                            const amazonPrice = Number(item.amazon_price) || 0;
                            const shipDisplay = Number(item.Ship_productmaster) || 0;
                            const ship = FORMULA_SHIP;
                            const lp = Number(item.LP_productmaster) || 0;
                            
                            // Calculate DISC VS AMZ for sorting
                            let discVsAmz = 0;
                            if (amazonPrice > 0 && price > 0) {
                                discVsAmz = (price / amazonPrice * 100) - 100;
                            }
                            
                            // Use PFT_percentage and ROI_percentage from controller (already in percentage form)
                            const npft_pct = Number(item.PFT_percentage) || 0;
                            const roi_pct = Number(item.ROI_percentage) || 0;
                            const sold = Number(item['doba L30']) || 0;
                            const promo = sold === 0 ? price * 0.90 : 0;
                            const promo_pu = promo - ship;

                            // SPFT and SROI calculations using same formula as PFT and ROI
                            const sprice = Number(item.SPRICE) || 0;
                            const spft = sprice > 0 ? ((sprice * 0.95) - ship - lp) / sprice * 100 : 0;
                            const sprofit = sprice > 0 ? (sprice * 0.95) - ship - lp : 0;
                            const sroi = lp > 0 && sprice > 0 ? (((sprice * 0.95) - ship - lp) / lp) * 100 : 0;

                            return {
                                sl_no: index + 1,
                                'Sl': index + 1,
                                Parent: item.Parent || item.parent || item.parent_asin || item.Parent_ASIN || '(No Parent)',
                                '(Child) sku': item['(Child) sku'] || '',
                                'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                INV: inv,
                                L30: l30,
                                ov_dil: ovDil,
                                'doba L30': dobaL30,
                                'doba L60': dobaL60,
                                'doba L45': dobaL45,
                                quantity_l7: quantityL7,
                                quantity_l7_prev: quantityL7Prev,
                                growth_percent: 0, // Calculated in formatter
                                self_pick_price: selfPickPriceVal,
                                amazon_price: amazonPrice,
                                disc_vs_amz: discVsAmz,
                                Profit: item.Total_pft || item.Profit || 0,
                                'Sales L30': dobaL30,
                                Roi: item.ROI_percentage || 0,
                                PFT_percentage: item.PFT_percentage || 0,
                                pickup_price: item['PICK UP PRICE '] || item.pickup_price || 0,
                                is_parent: item['(Child) sku'] ? item['(Child) sku'].toUpperCase().includes("PARENT") : false,
                                thumb_image: item.image_path || item.image || '',
                                raw_data: item || {},
                                NR: item.NR || '',
                                NPFT_pct: npft_pct,
                                Promo: promo,
                                Promo_PU: promo_pu,
                                missing: (inv > 0 && dobaL30 === 0) ? 1 : 0, // Missing indicator: has inventory but not selling
                                LP_productmaster: lp,
                                Ship_productmaster: shipDisplay,
                                sprice: item.SPRICE || 0,
                                spft: item.SPFT || spft,
                                sprofit: sprofit,
                                sroi: item.SROI || sroi,
                                s_self_pick: Number(item.S_SELF_PICK) || 0, // Saved S (PP)
                                s_l30: Number(item.s_l30) || 0,  // S L30 from doba_daily_data
                                doba_list_price: listPriceCol,
                                msrp: Number(item.msrp) || 0,
                                map: Number(item.map) || 0,
                                push_status: item.PUSH_STATUS || null, // Saved push status from DB
                                push_status_updated_at: item.PUSH_STATUS_UPDATED_AT || null // Timestamp when push status was updated
                            };
                        });
                        return processedData;
                    }
                    return [];
                },
                pagination: "local",
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                layout: "fitData",
                responsiveLayout: "hide",
                placeholder: "No Data Available",
                selectable: false,
                headerFilterPlaceholder: "",
                columnDefaults: {
                    hozAlign: "center",
                    headerHozAlign: "center",
                },
                columns: [
                    {
                        title: "Img",
                        field: "thumb_image",
                        width: 58,
                        frozen: true,
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent) {
                                return '';
                            }
                            const url = cell.getValue();
                            if (!url) {
                                return '<span class="text-muted" style="font-size:11px;">—</span>';
                            }
                            const safe = String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                            return '<div class="dws-img-hover-wrap"><img class="dws-thumb-sm" src="' + safe + '" alt="" loading="lazy" style="max-width:48px;max-height:48px;object-fit:contain;"></div>';
                        }
                    },
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        width: 175,
                        frozen: true,
                        hozAlign: "left",
                        headerHozAlign: "left",
                        formatter: function(cell, formatterParams) {
                            const value = cell.getValue();
                            const rawData = cell.getRow().getData().raw_data || {};
                            const buyerLink = rawData['B Link'] || '';
                            const sellerLink = rawData['S Link'] || '';
                            const copyBtn = '<button type="button" class="btn btn-link btn-sm py-0 px-1 dws-copy-sku" data-sku="' + dwsAttrEscape(value) + '" title="Copy SKU"><i class="fa-regular fa-copy"></i></button>';
                            
                            /* Image preview only in Img column — SKU tooltip: links only */
                            if (buyerLink || sellerLink) {
                                return (
                                    '<div class="sku-tooltip-container d-flex align-items-center gap-1 flex-wrap">' +
                                    '<span class="sku-text">' + dwsEscapeHtml(value) + '</span>' +
                                    copyBtn +
                                    '<div class="sku-tooltip">' +
                                    (buyerLink ? '<div class="sku-link"><a href="' + dwsAttrEscape(buyerLink) + '" target="_blank" rel="noopener noreferrer">Buyer link</a></div>' : '') +
                                    (sellerLink ? '<div class="sku-link"><a href="' + dwsAttrEscape(sellerLink) + '" target="_blank" rel="noopener noreferrer">Seller link</a></div>' : '') +
                                    '</div></div>'
                                );
                            }
                            return '<div class="d-flex align-items-center gap-1 flex-wrap"><span class="sku-text">' + dwsEscapeHtml(value) + '</span>' + copyBtn + '</div>';
                        }
                    },
                    
                    {
                        title: "INV",
                        field: "INV",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value.toString();
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseFloat(cell.getValue()) || 0;
                        }
                    },
                    {
                        title: "DIL",
                        field: "ov_dil",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const percent = value * 100;
                            let style = '';
                            if (percent < 16.66) style = 'color: #dc3545; font-weight: 800;'; // red - bold
                            else if (percent >= 16.66 && percent < 25) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (percent >= 25 && percent < 50) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - bold
                            
                            return `<span style="${style}">${Math.round(percent)}%</span>`;
                        }
                    },
                    {
                        title: "L60",
                        field: "doba L60",
                        width: 70,
                        sorter: "number",
                        visible: true,
                        formatter: function(cell, formatterParams) {
                            return cell.getValue() || 0;
                        }
                    },
                    {
                        title: "L45",
                        field: "doba L45",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseInt(cell.getValue(), 10) || 0;
                        }
                    },
                    {
                        title: "L30",
                        field: "doba L30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseFloat(cell.getValue()) || 0;
                        }
                    },
                    {
                        title: "Growth",
                        field: "growth_percent",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const rowData = cell.getRow().getData();
                            const l30 = parseFloat(rowData['doba L30']) || 0;
                            const l60 = parseFloat(rowData['doba L60']) || 0;
                            
                            // L60 is the previous 30 days (days 31-60)
                            // L30 is the last 30 days (days 1-30)
                            
                            if (l60 === 0) {
                                if (l30 > 0) {
                                    return `<span style="color: #28a745; font-weight: bold;">+100%</span>`;
                                }
                                return '<span style="color: #6c757d;">0%</span>';
                            }
                            
                            // Calculate growth percentage: (L30 - L60) / L60 * 100
                            const growth = ((l30 - l60) / l60) * 100;
                            const growthRounded = Math.round(growth);
                            
                            // Color code: green for positive, red for negative, gray for zero
                            let color = '#6c757d'; // gray for 0
                            if (growthRounded > 0) {
                                color = '#28a745'; // green for positive
                            } else if (growthRounded < 0) {
                                color = '#dc3545'; // red for negative
                            }
                            
                            const sign = growthRounded > 0 ? '+' : '';
                            return `<span style="color: ${color}; font-weight: bold;">${sign}${growthRounded}%</span>`;
                        }
                    },
                    {
                        title: "L7",
                        field: "quantity_l7",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value > 0) {
                                return `<span style="color: #28a745; font-weight: bold;">${value}</span>`;
                            }
                            return value;
                        }
                    },
                    {
                        title: "L7-14",
                        field: "quantity_l7_prev",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value > 0) {
                                return `<span style="color: #3591dc; font-weight: bold;">${value}</span>`;
                            }
                            return value;
                        }
                    },
                    {
                        title: "S L30",
                        field: "s_l30",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value > 0) {
                                return `<span style="color: #28a745; font-weight: bold;">${value}</span>`;
                            }
                            return value;
                        }
                    },
                    {
                        title: "PROMO",
                        field: "Promo",
                        width: 80,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: #000; font-weight: bold;">$${value.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Promo PU",
                        field: "Promo_PU",
                        width: 90,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: #000; font-weight: bold;">$${value.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Missing",
                        field: "missing",
                        width: 80,
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const rowData = cell.getRow().getData();
                            const inv = parseFloat(rowData.INV) || 0;
                            const dobaL30 = parseFloat(rowData['doba L30']) || 0;
                            
                            // Red badge: Has inventory but not selling (INV > 0 AND L30 = 0)
                            if (inv > 0 && dobaL30 === 0) {
                                return '<span class="badge" style="background-color: #b02a37; color: white;"><i class="fas fa-exclamation-triangle"></i></span>';
                            }
                            
                            // Yellow dot: 0 inventory (out of stock)
                            if (inv === 0) {
                                return '<span style="color: #ffc107; font-size: 24px; font-weight: bold;">●</span>';
                            }
                            
                            return '';
                        }
                    },
                    {
                        title: "Price",
                        field: "self_pick_price",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `$${value.toFixed(2)}`;
                        }
                    },
                    {
                        title: "W LP Prc",
                        field: "doba_list_price",
                        width: 90,
                        sorter: "number",
                        visible: true,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `<span style="color: #495057; font-weight: 600;">$${value.toFixed(2)}</span>` : '<span style="color: #6c757d;">-</span>';
                        }
                    },
                    {
                        title: "NPFT%",
                        field: "NPFT_pct",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            // Value is already a percentage from controller (PFT_percentage)
                            let style = '';
                            // getPftColor logic from pricing CVR
                            if (value < 10) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 10 && value < 15) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 15 && value < 20) style = 'color: #3591dc; font-weight: bold;'; // blue
                            else if (value >= 20 && value <= 40) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "NROI%",
                        field: "Roi",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            // Value is already a percentage from controller (ROI_percentage)
                            let style = '';
                            
                            // getRoiColor logic from pricing CVR
                            if (value < 50) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 50 && value < 75) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 75 && value <= 125) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        width: 50,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.is_parent;
                            
                            if (isParent) return '';
                            
                            const sku = rowData['(Child) sku'];
                            const isSelected = selectedSkus.has(sku);
                            
                            // Just show checkbox - status icons are in _push column (like Amazon)
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                        }
                    },
                    {
                        title: "SPRICE",
                        field: "sprice",
                        width: 80,
                        sorter: "number",
                        visible: true,
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `<span style="color: #000; font-weight: 600;">$${value.toFixed(2)}</span>` : '';
                        }
                    },
                    {
                        title: "LP",
                        field: "LP_productmaster",
                        width: 70,
                        sorter: "number",
                        visible: true,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    },
                    {
                        title: "SPFT",
                        field: "spft",
                        width: 70,
                        sorter: "number",
                        visible: true,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            if (value === 0) return '';
                            let style = '';
                            // Match NPFT% coloring
                            if (value < 10) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 10 && value < 15) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 15 && value < 20) style = 'color: #3591dc; font-weight: bold;'; // blue
                            else if (value >= 20 && value <= 40) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "SROI",
                        field: "sroi",
                        width: 70,
                        sorter: "number",
                        visible: true,
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            if (value === 0) return '';
                            let style = '';
                            // Match ROI coloring
                            if (value < 50) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 50 && value < 75) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 75 && value <= 125) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "Push",
                        field: "_push",
                        width: 50,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.is_parent;
                            const sprice = parseFloat(rowData.sprice) || 0;
                            const pushStatus = rowData.push_status || null;
                            const applyStatus = rowData.apply_status || null;
                            
                            if (isParent || sprice <= 0) return '';
                            
                            const sku = rowData['(Child) sku'];
                            
                            // Show spinner while applying discount
                            if (applyStatus === 'applying') {
                                return '<i class="fas fa-clock fa-spin" style="color: #ffc107;" title="Applying discount..."></i>';
                            }
                            
                            // Show spinner while pushing
                            if (pushStatus === 'pushing') {
                                return '<i class="fas fa-spinner fa-spin" style="color: #ffc107;" title="Pushing..."></i>';
                            }
                            
                            // Show double tick only if pushed successfully
                            if (pushStatus === 'pushed') {
                                return '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Doba"></i>';
                            }
                            
                            // Show error with retry button
                            if (pushStatus === 'error') {
                                return `<button class="push-single-btn" data-sku="${sku}" data-price="${sprice}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed - Click to retry">
                                    <i class="fa-solid fa-x"></i>
                                </button>`;
                            }
                            
                            // Default: show push button (for new SPRICE, after discount applied, or null status)
                            return `<button class="push-single-btn" data-sku="${sku}" data-price="${sprice}" style="border: none; background: none; color: #0d6efd; cursor: pointer;" title="Push to Doba">
                                <i class="fas fa-upload"></i>
                            </button>`;
                        }
                    },
                    {
                        title: "MSRP",
                        field: "msrp",
                        width: 75,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    },
                    {
                        title: "MAP",
                        field: "map",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    }
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent) {
                        row.getElement().classList.add("parent-row");
                    }
                    if (data.NR === 'NRA') {
                        row.getElement().classList.add('nr-hide');
                    }
                }
            });

            function ensureFooterVisibleRowsLabel() {
                const tableEl = document.getElementById('doba-withoutship-table');
                if (!tableEl) return;
                const footer = tableEl.querySelector('.tabulator-footer');
                if (!footer || document.getElementById('dws-footer-visible-rows')) return;
                const el = document.createElement('span');
                el.id = 'dws-footer-visible-rows';
                el.textContent = 'Visible rows: 0';
                footer.insertBefore(el, footer.firstChild);
            }

            // Apply filters (AliExpress-style controls)
            function applyFilters() {
                if (!table) return;
                table.clearFilter();

                const skuSearch = ($('#dws-sku-search').val() || '').toLowerCase().trim();
                const rowType = $('#dws-row-type-filter').val();
                const invFilter = $('#dws-inv-filter').val();
                const ovl30Filter = $('#dws-ovl30-filter').val();
                const gpftFilter = $('#dws-gpft-filter').val();
                const roiFilter = $('#dws-roi-filter').val();
                const al30Filter = $('#dws-al30-filter').val();
                const mapFilter = $('#dws-map-filter').val();
                const dilColor = $('.dws-dil-item.active').data('color') || 'all';

                if (skuSearch) {
                    table.addFilter(function(data) {
                        const sku = (data['(Child) sku'] || '').toLowerCase();
                        return sku.includes(skuSearch);
                    });
                }

                if (rowType === 'parents') {
                    table.addFilter(function(data) { return data.is_parent === true; });
                } else if (rowType === 'skus') {
                    table.addFilter(function(data) { return !data.is_parent; });
                }

                if (invFilter === 'zero') {
                    table.addFilter(function(data) { return (parseFloat(data.INV) || 0) === 0; });
                } else if (invFilter === 'more') {
                    table.addFilter(function(data) { return (parseFloat(data.INV) || 0) > 0; });
                }

                if (ovl30Filter === 'zero') {
                    table.addFilter(function(data) { return (parseFloat(data.L30) || 0) === 0; });
                } else if (ovl30Filter === 'more') {
                    table.addFilter(function(data) { return (parseFloat(data.L30) || 0) > 0; });
                }

                if (gpftFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent) return true;
                        const gpft = parseFloat(data.NPFT_pct) || 0;
                        if (gpftFilter === 'negative') return gpft < 0;
                        if (gpftFilter === '60plus') return gpft >= 60;
                        const parts = gpftFilter.split('-');
                        const min = parseFloat(parts[0]);
                        const max = parseFloat(parts[1]);
                        return gpft >= min && gpft < max;
                    });
                }

                if (roiFilter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent) return true;
                        const roiVal = parseFloat(data.Roi) || 0;
                        if (roiFilter === 'lt40') return roiVal < 40;
                        if (roiFilter === 'gt250') return roiVal > 250;
                        const [min, max] = roiFilter.split('-').map(Number);
                        return roiVal >= min && roiVal <= max;
                    });
                }

                if (al30Filter !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent) return true;
                        if ((parseFloat(data.INV) || 0) <= 0) return false;
                        const al30 = parseFloat(data['doba L30']) || 0;
                        if (al30Filter === '0') return al30 === 0;
                        if (al30Filter === '0-10') return al30 > 0 && al30 <= 10;
                        if (al30Filter === '10plus') return al30 > 10;
                        return true;
                    });
                }

                if (mapFilter === 'map') {
                    table.addFilter(function(data) {
                        if (data.is_parent) return true;
                        return (parseFloat(data.map) || 0) > 0;
                    });
                } else if (mapFilter === 'nmap') {
                    table.addFilter(function(data) {
                        if (data.is_parent) return true;
                        return (parseFloat(data.map) || 0) === 0;
                    });
                }

                if (dilColor !== 'all') {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data.INV) || 0;
                        const l30 = parseFloat(data.L30) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                        if (dilColor === 'red') return dil < 16.66;
                        if (dilColor === 'yellow') return dil >= 16.66 && dil < 25;
                        if (dilColor === 'green') return dil >= 25 && dil < 50;
                        if (dilColor === 'pink') return dil >= 50;
                        return true;
                    });
                }

                const growthSign = $('#dws-growth-sign-filter').val();
                if (growthSign && growthSign !== 'all') {
                    table.addFilter(function(data) {
                        if (data.is_parent) return true;
                        const l30 = parseFloat(data['doba L30']) || 0;
                        const l60 = parseFloat(data['doba L60']) || 0;
                        let growth = 0;
                        if (l60 > 0) {
                            growth = ((l30 - l60) / l60) * 100;
                        } else if (l30 > 0) {
                            growth = 100;
                        }
                        const g = Math.round(growth);
                        if (growthSign === 'negative') return g < 0;
                        if (growthSign === 'zero') return g === 0;
                        if (growthSign === 'positive') return g > 0;
                        return true;
                    });
                }

                if (missingBadgeFilterActive) {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data.INV) || 0;
                        const dobaL30 = parseFloat(data['doba L30']) || 0;
                        return inv > 0 && dobaL30 === 0;
                    });
                }

                if (discVsAmzFilterActive) {
                    table.addFilter(function(data) {
                        const dobaPrice = parseFloat(data.self_pick_price) || 0;
                        const amazonPrice = parseFloat(data.amazon_price) || 0;
                        if (amazonPrice === 0 || dobaPrice === 0) return false;
                        const discountPercent = (dobaPrice / amazonPrice * 100) - 100;
                        return discountPercent > -30;
                    });
                }

                setTimeout(function() {
                    updateSelectAllCheckbox();
                    updateVisibleRowsCount();
                }, 100);
            }

            function updateVisibleRowsCount() {
                if (!table) return;
                const visibleData = table.getData('active');
                const n = visibleData.filter(row => !row.is_parent).length;
                const el = document.getElementById('dws-footer-visible-rows');
                if (el) el.textContent = 'Visible rows: ' + n;
            }

            $('#dws-row-type-filter, #dws-inv-filter, #dws-ovl30-filter, #dws-gpft-filter, #dws-roi-filter, #dws-al30-filter, #dws-map-filter, #dws-growth-sign-filter').on('change', function() {
                applyFilters();
            });

            $(document).on('click', '.dws-dil-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.dws-manual-dropdown').toggleClass('show');
            });
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dws-manual-dropdown').length) {
                    $('.dws-manual-dropdown').removeClass('show');
                }
            });
            $(document).on('click', '.dws-dil-item', function(e) {
                e.preventDefault();
                $('.dws-dil-item').removeClass('active');
                $(this).addClass('active');
                const color = $(this).data('color');
                const circle = $(this).find('.dws-sc').first().clone();
                $('#dws-dil-btn').empty().append(circle).append(document.createTextNode('DIL%'));
                $('.dws-manual-dropdown').removeClass('show');
                applyFilters();
            });

            // Fetch and display summary metrics from marketplace_daily_metrics table
            // Update summary badges for SKU counts
            function updateSummary() {
                const tableData = table.getData("active");
                const filteredData = tableData.filter(row => !row.is_parent);
                
                // For missing count, use all data (not filtered by INV)
                const allData = table.getData();
                const allNonParentData = allData.filter(row => !row.is_parent);
                
                const totalSkus = filteredData.length;
                let l30ZeroSold = 0;
                let sold = 0;
                let missing = 0;
                let discVsAmzCount = 0;
                let totalL30Sales = 0;
                let totalL60Sales = 0;
                let totalL30COGS = 0;
                let totalL30Shipping = 0;
                let totalL60COGS = 0;
                let totalL60Shipping = 0;

                filteredData.forEach(row => {
                    const inv = parseFloat(row.INV) || 0;
                    const dobaL30 = parseFloat(row['doba L30']) || 0;
                    const dobaL60 = parseFloat(row['doba L60']) || 0;
                    const dobaPrice = parseFloat(row.self_pick_price) || 0;
                    const lp = parseFloat(row.LP_productmaster) || 0;
                    const ship = FORMULA_SHIP;
                    
                    if (dobaL30 === 0 && inv > 0) l30ZeroSold++;
                    if (dobaL30 > 0) sold++;
                    
                    totalL30Sales += dobaL30 * dobaPrice;
                    totalL60Sales += dobaL60 * dobaPrice;
                    totalL30COGS += dobaL30 * lp;
                    totalL30Shipping += dobaL30 * ship;
                    totalL60COGS += dobaL60 * lp;
                    totalL60Shipping += dobaL60 * ship;
                });

                // Calculate missing and disc vs amz from all data (not filtered)
                allNonParentData.forEach(row => {
                    const inv = parseFloat(row.INV) || 0;
                    const dobaL30 = parseFloat(row['doba L30']) || 0;
                    const dobaPrice = parseFloat(row.self_pick_price) || 0;
                    const amazonPrice = parseFloat(row.amazon_price) || 0;
                    
                    // Missing: Has inventory but no sales in L30 (items that need attention)
                    if (inv > 0 && dobaL30 === 0) missing++;
                    
                    // DISC VS AMZ: Count items with discount > -30% (red color items)
                    if (amazonPrice > 0 && dobaPrice > 0) {
                        const discountPercent = (dobaPrice / amazonPrice * 100) - 100;
                        if (discountPercent > -30) discVsAmzCount++;
                    }
                });

                // Calculate growth percentage
                let growthPercent = 0;
                let growthColor = '#28a745'; // Default green
                if (totalL60Sales > 0) {
                    growthPercent = Math.round(((totalL30Sales - totalL60Sales) / totalL60Sales) * 100);
                    if (growthPercent < 0) {
                        growthColor = '#dc3545'; // Red for negative
                    } else if (growthPercent === 0) {
                        growthColor = '#6c757d'; // Gray for zero
                    }
                } else if (totalL30Sales > 0) {
                    growthPercent = 100;
                    growthColor = '#28a745'; // Green
                }

                // Calculate L30 GPFT and L30 GPFT %
                const l30Profit = totalL30Sales - totalL30COGS - totalL30Shipping;
                let l30GpftPercent = 0;
                if (totalL30Sales > 0) {
                    l30GpftPercent = (l30Profit / totalL30Sales) * 100;
                }
                
                let l60GpftPercent = 0;
                let l60GpftTotal = 0;
                if (totalL60Sales > 0) {
                    const l60Profit = totalL60Sales - totalL60COGS - totalL60Shipping;
                    l60GpftTotal = l60Profit;
                    l60GpftPercent = (l60Profit / totalL60Sales) * 100;
                }

                const gpftPercentGrowth = l30GpftPercent - l60GpftPercent;
                let gpftPercentColor = '#6c757d';
                if (gpftPercentGrowth > 0) {
                    gpftPercentColor = '#28a745';
                } else if (gpftPercentGrowth < 0) {
                    gpftPercentColor = '#dc3545';
                }

                // Calculate GPFT Growth (in dollars)
                const gpftGrowth = l30Profit - l60GpftTotal;
                let gpftGrowthColor = '#6c757d'; // Gray for zero
                if (gpftGrowth > 0) {
                    gpftGrowthColor = '#28a745'; // Green for positive
                } else if (gpftGrowth < 0) {
                    gpftGrowthColor = '#dc3545'; // Red for negative
                }

                $('#total-skus').text('Total SKUs: ' + totalSkus);
                $('#zero-sold-count').text('L30 0 Sold: ' + l30ZeroSold);
                $('#sold-count').text('SOLD: ' + sold);
                $('#missing-count').html('<i class="fas fa-exclamation-triangle"></i> Missing: ' + missing);
                $('#disc-vs-amz-count').html('<i class="fas fa-chart-line"></i> VS AMZ: ' + discVsAmzCount);
                
                const growthSign = growthPercent > 0 ? '+' : '';
                $('#growth-sales-badge').text('GROWTH: ' + growthSign + growthPercent + '%');
                $('#growth-sales-badge').css('background-color', growthColor);
                
                $('#pft-percentage-badge').text('L30 GPFT %: ' + l30GpftPercent.toFixed(1) + '%');
                
                const gpftPercentSign = gpftPercentGrowth > 0 ? '+' : '';
                $('#growth-gpft-percent-badge').text('GROWTH GPFT %: ' + gpftPercentSign + gpftPercentGrowth.toFixed(1) + '%');
                $('#growth-gpft-percent-badge').css('background-color', gpftPercentColor);
                
                $('#pft-total-badge').text('L30 GPFT: $' + Math.round(l30Profit).toLocaleString());

                const l30RoiAgg = totalL30COGS > 0 ? (l30Profit / totalL30COGS) * 100 : 0;
                $('#roi-percentage-badge').text('L30 ROI %: ' + Math.round(l30RoiAgg) + '%');
                $('#total-cogs-badge').text('Total COGS: $' + Math.round(totalL30COGS).toLocaleString());
                
                const gpftGrowthSign = gpftGrowth > 0 ? '+$' : gpftGrowth < 0 ? '-$' : '$';
                const gpftGrowthAbs = Math.abs(Math.round(gpftGrowth));
                $('#growth-gpft-badge').text('GROWTH GPFT: ' + gpftGrowthSign + gpftGrowthAbs.toLocaleString());
                $('#growth-gpft-badge').css('background-color', gpftGrowthColor);
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const columns = table.getColumns();
                const menu = document.getElementById("column-dropdown-menu");
                
                // Clear existing column items
                const existingItems = menu.querySelectorAll('.column-toggle-item');
                existingItems.forEach(item => item.remove());
                
                columns.forEach(column => {
                    if (column.getField() === 'sl_no') return; // Skip sl_no column
                    
                    const item = document.createElement('label');
                    item.className = 'dropdown-item column-toggle-item d-flex align-items-center';
                    item.innerHTML = `
                        <input type="checkbox" class="form-check-input me-2" 
                               data-column="${column.getField()}" 
                               ${column.isVisible() ? 'checked' : ''}>
                        ${column.getDefinition().title}
                    `;
                    menu.appendChild(item);
                });
            }

            // Handle SPRICE cell edit
            table.on('cellEdited', function(cell) {
                const field = cell.getColumn().getField();
                
                if (field === 'sprice') {
                    const rowData = cell.getRow().getData();
                    const sprice = parseFloat(cell.getValue()) || 0;
                    const lp = parseFloat(rowData.LP_productmaster) || 0;
                    const ship = FORMULA_SHIP;
                    const sku = rowData['(Child) sku'];
                    
                    if (sprice > 0 && lp > 0) {
                        // Calculate SPFT% = ((sprice * 0.95) - ship - lp) / sprice * 100
                        const spft = ((sprice * 0.95) - ship - lp) / sprice * 100;
                        
                        // Calculate SROI% = ((sprice * 0.95) - ship - lp) / lp * 100
                        const sroi = ((sprice * 0.95) - ship - lp) / lp * 100;
                        
                        // Calculate S(PP) = SPRICE - SHIP
                        const sSelfPick = sprice - ship;
                        
                        // Update row data with all calculated values
                        // Reset push_status to null so push button shows again (like Amazon)
                        cell.getRow().update({
                            spft: spft,
                            sroi: sroi,
                            s_self_pick: sSelfPick,
                            push_status: null,
                            apply_status: null
                        });
                        
                        // Force refresh the _push cell to show upload button immediately
                        const pushCell = cell.getRow().getCell('_push');
                        if (pushCell) {
                            pushCell.getElement().innerHTML = `<button class="push-single-btn" data-sku="${sku}" data-price="${sprice}" style="border: none; background: none; color: #0d6efd; cursor: pointer;" title="Push to Doba">
                                <i class="fas fa-upload"></i>
                            </button>`;
                        }
                        
                        // Save to backend with S(PP) and reset push_status
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku,
                                sprice: sprice,
                                spft_percent: spft,
                                sroi_percent: sroi,
                                s_self_pick: sSelfPick,
                                push_status: null
                            },
                            success: function(response) {
                                showToast('success', 'SPRICE updated successfully');
                            },
                            error: function(xhr) {
                                showToast('danger', 'Failed to update SPRICE');
                                console.error(xhr);
                            }
                        });
                    }
                }
            });

            // Wait for table to be built
            table.on('tableBuilt', function() {
                ensureFooterVisibleRowsLabel();
                buildColumnDropdown();
                updateSummary();
                applyFilters(); // Default: > 0 inventory
                updateVisibleRowsCount();
            });

            table.on('dataLoaded', function() {
                setTimeout(() => {
                    applyFilters();
                    updateSummary();
                    updateVisibleRowsCount();
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    updatePushButtonVisibility();
                }, 100);
            });

            table.on('renderComplete', function() {
                setTimeout(() => {
                    ensureFooterVisibleRowsLabel();
                    updateSummary();
                    updateVisibleRowsCount();
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    updatePushButtonVisibility();
                }, 100);
            });

            // Update Push to Doba button visibility based on SELECTED SKUs with SPRICE (like Amazon)
            function updatePushButtonVisibility() {
                // Only count selected SKUs that have SPRICE > 0
                let spriceCount = 0;
                selectedSkus.forEach(sku => {
                    const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                    if (row) {
                        const data = row.getData();
                        if (!data.is_parent && data.sprice && data.sprice > 0) {
                            spriceCount++;
                        }
                    }
                });
                
                if (spriceCount > 0) {
                    $('#push-to-doba-btn').show().html(`<i class="fas fa-upload"></i> Push to Doba (${spriceCount})`);
                } else {
                    $('#push-to-doba-btn').hide();
                }
            }

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const columnField = e.target.getAttribute('data-column');
                    const column = table.getColumn(columnField);
                    
                    if (e.target.checked) {
                        column.show();
                    } else {
                        column.hide();
                    }
                }
            });

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(column => {
                    column.show();
                });
                buildColumnDropdown();
            });

            // Export CSV
            $('#export-csv-btn').on('click', function() {
                table.download("csv", "doba_pricing_cvr_export.csv");
            });

            // Reload Data
            $('#reload-data-btn').on('click', function() {
                table.replaceData("/doba-data-view-withoutship");
            });

            // Toast notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                setTimeout(() => toast.remove(), 5000);
            }

            // Missing filter toggle on badge click
            $('#missing-count').on('click', function() {
                missingBadgeFilterActive = !missingBadgeFilterActive;
                if (missingBadgeFilterActive) {
                    $(this).css({ 'background-color': '#ffc107', 'color': '#000' });
                    applyFilters();
                    const filteredCount = table.getData('active').filter(row => !row.is_parent).length;
                    showToast('warning', `Filtered to ${filteredCount} missing items`);
                } else {
                    $(this).css({ 'background-color': '#b02a37', 'color': '#ffffff' });
                    applyFilters();
                    showToast('info', 'Showing all items');
                }
            });

            // DISC VS AMZ filter toggle on badge click
            $('#disc-vs-amz-count').on('click', function() {
                discVsAmzFilterActive = !discVsAmzFilterActive;
                
                if (discVsAmzFilterActive) {
                    $(this).css({
                        'background-color': '#28a745',
                        'color': '#ffffff'
                    });
                    applyFilters();
                    const filteredCount = table.getData("active").filter(row => !row.is_parent).length;
                    showToast('info', `Filtered to ${filteredCount} non-competitive items (discount > -30%)`);
                } else {
                    $(this).css({
                        'background-color': '#dc3545',
                        'color': '#ffffff'
                    });
                    applyFilters();
                    showToast('info', 'Showing all items');
                }
            });
        });
    </script>
@endsection
