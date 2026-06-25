@extends('layouts.vertical', ['title' => 'Aliexpress - Analytics', 'sidenav' => 'condensed'])

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

        /* ── Parent row – identical to amazon_tabulator_view ── */
        .tabulator-row.ae-parent-row,
        .tabulator-row.ae-parent-row .tabulator-cell {
            background-color: #bde0ff !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        .tabulator-row.ae-parent-row .tabulator-cell {
            min-height: 48px !important; height: 48px !important;
            padding-top: 8px !important; padding-bottom: 8px !important;
            overflow: visible !important; vertical-align: middle !important;
            color: #1e3a5f;
        }
        .tabulator-row.ae-parent-row:hover,
        .tabulator-row.ae-parent-row:hover .tabulator-cell {
            background-color: #93c5fd !important;
        }

        /* ── Modern pagination – identical to amazon_tabulator_view ── */
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

        /* ── DIL dropdown (identical to TikTok) ── */
        .ae-manual-dropdown { position: relative; display: inline-block; }
        .ae-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .ae-manual-dropdown.show .dropdown-menu { display: block; }
        .ae-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .ae-dropdown-item:hover { background: #e9ecef; }

        /* ── Status circles ── */
        .ae-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .ae-sc.def    { background:#6c757d; }
        .ae-sc.red    { background:#dc3545; }
        .ae-sc.yellow { background:#ffc107; }
        .ae-sc.green  { background:#28a745; }
        .ae-sc.pink   { background:#e83e8c; }

        /* Summary badges — horizontal scroll on narrow viewports (Shein / eBay 2 style) */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        #summary-stats .ae-filter-badge.active-filter {
            outline: 3px solid #0d6efd;
            outline-offset: 2px;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.35);
        }

        #summary-stats .ebay2-summary-badge-row > .badge {
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
        'page_title' => 'Aliexpress - Analytics',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card border-warning mb-3">
                <div class="card-header bg-warning bg-opacity-25 py-2">
                    <strong><i class="fas fa-upload me-1"></i> List price upload</strong>
                    <span class="text-muted small ms-2">Columns: <strong>sku</strong>, <strong>price</strong>, <strong>stock</strong> (optional).</span>
                </div>
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('aliexpress.pricing.price.sample') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadAePriceModal">
                        <i class="fas fa-upload"></i> Upload price sheet
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">

                    {{-- ── Filter bar (TikTok style) ── --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                        {{-- Row type filter (All Rows / Parents / SKUs) – same as Amazon --}}
                    <select id="ae-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                        <option value="all" selected>All Rows</option>
                        <option value="parents">Parents</option>
                        <option value="skus">SKUs</option>
                    </select>

                    {{-- Inventory filter --}}
                        <select id="ae-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>

                        {{-- AE Stock filter --}}
                        <select id="ae-stock-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">AE Stock</option>
                            <option value="zero">0 AE Stock</option>
                            <option value="more">More than 0</option>
                        </select>

                        {{-- GPFT% + CVR% (Reverb-style; CVR = AL30 ÷ OV L30) --}}
                        <div class="d-flex flex-column gap-1" style="width:130px;">
                            <select id="ae-gpft-filter" class="form-select form-select-sm">
                                <option value="all">GPFT%</option>
                                <option value="negative">Negative</option>
                                <option value="0-10">0–10%</option>
                                <option value="10-20">10–20%</option>
                                <option value="20-30">20–30%</option>
                                <option value="30-40">30–40%</option>
                                <option value="40-50">40–50%</option>
                                <option value="50plus">Above 50%</option>
                            </select>
                            <select id="ae-cvr-filter" class="form-select form-select-sm">
                                <option value="all">All CVR%</option>
                                <option value="0-0">0%</option>
                                <option value="0-2">0-2%</option>
                                <option value="2-4">2-4%</option>
                                <option value="4-7">4-7%</option>
                                <option value="7-13">7-13%</option>
                                <option value="13plus">13%+</option>
                            </select>
                        </div>

                        {{-- ROI% filter --}}
                        <select id="ae-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>

                        {{-- AL30 filter --}}
                        <select id="ae-al30-filter" class="form-select form-select-sm" style="width:130px;" title="Excludes 0 inventory items">
                            <option value="all">AL30</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>

                        {{-- Map filter --}}
                        <select id="ae-map-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all">Map</option>
                            <option value="map">Map only</option>
                            <option value="nmap">N Map only</option>
                        </select>

                        {{-- DIL% dropdown (identical to TikTok) --}}
                        <div class="ae-manual-dropdown">
                            <button class="btn btn-light btn-sm ae-dil-toggle" type="button" id="ae-dil-btn">
                                <span class="ae-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="ae-dropdown-item ae-dil-item active" href="#" data-color="all">
                                    <span class="ae-sc def"></span>All DIL</a></li>
                                <li><a class="ae-dropdown-item ae-dil-item" href="#" data-color="red">
                                    <span class="ae-sc red"></span>Red (&lt;16.7%)</a></li>
                                <li><a class="ae-dropdown-item ae-dil-item" href="#" data-color="yellow">
                                    <span class="ae-sc yellow"></span>Yellow (16.7–25%)</a></li>
                                <li><a class="ae-dropdown-item ae-dil-item" href="#" data-color="green">
                                    <span class="ae-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="ae-dropdown-item ae-dil-item" href="#" data-color="pink">
                                    <span class="ae-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>

                        {{-- SKU search --}}
                        <input type="text" id="pricing-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU...">

                        <button type="button" id="refresh-pricing-table" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="export-pricing-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <a href="{{ route('aliexpress.lmp.sample') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download"></i> LMP sample
                        </a>
                        <a href="{{ route('aliexpress.lmp') }}" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-table"></i> LMP sheet
                        </a>

                        {{-- Price Mode (Increase / Decrease / Same Price) --}}
                        <button id="ae-price-mode-btn" class="btn btn-sm btn-secondary" title="Cycle: Off → Decrease → Increase → Same Price → Off">
                            <i class="fas fa-exchange-alt"></i> Price Mode
                        </button>

                        {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                             Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = per-row `_margin`, derived from MarketplacePercentage 'Aliexpress') --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="ae-target-roi-controls"
                            title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin on every checked row (back-solves so SROI column equals the target)">
                            <label for="ae-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                Target ROI%:
                            </label>
                            <input type="number" id="ae-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="e.g. 30" step="0.1" style="width: 80px;"
                                title="Target ROI% applied to all checked rows when you click 'Apply S PRC'">
                            <button id="ae-apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                                title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin for every checked row">
                                <i class="fas fa-calculator"></i> Apply S PRC
                            </button>
                        </div>

                        {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                             Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="ae-target-gpft-controls"
                            title="Target GPFT% — sets S PRC = (LP + Ship) / (margin − Target GPFT%/100) on every checked row (back-solves so SGPFT column equals the target)">
                            <label for="ae-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                                Target GPFT%:
                            </label>
                            <input type="number" id="ae-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="e.g. 30" step="0.1" style="width: 80px;"
                                title="Target GPFT% applied to all checked rows when you click 'Apply S PRC'. Must be less than each row's take-home margin.">
                            <button id="ae-apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                                title="Compute & save S PRC = (LP + Ship) / (margin − Target GPFT%/100) for every checked row">
                                <i class="fas fa-calculator"></i> Apply S PRC
                            </button>
                        </div>

                        <!-- Play / Pause parent navigation -->
                        <div class="btn-group align-items-center ms-2" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-sm btn-warning rounded-circle shadow-sm" style="display: none;" title="Stop navigation and show all">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Discount input (shown when Price Mode is active) --}}
                    <div id="ae-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span id="ae-selected-skus-count" class="fw-bold text-secondary"></span>
                            <span id="ae-discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                            <span id="ae-discount-type-wrap">
                            <select id="ae-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            </span>
                            <input type="number" id="ae-discount-input" class="form-control form-control-sm"
                                placeholder="Enter %" step="0.01" style="width:140px;">
                            <button id="ae-apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                            <button id="ae-clear-sprice-btn" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    {{-- ── Summary badges ── --}}
                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2 ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-primary  fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-sales-badge" data-metric="total_sales" style="font-weight:700;cursor:pointer;" title="Click or hover (½s) for daily trend">Sales: $0</span>
                            <span class="badge bg-warning  fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-al30-badge"  data-metric="total_al30"  style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover (½s) for daily trend">AL30: 0</span>
                            <span class="badge bg-success  fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-profit-badge" data-metric="total_pft"  style="font-weight:700;cursor:pointer;" title="Click or hover (½s) for daily trend">Profit: $0</span>
                            <span class="badge bg-info     fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-gpft-badge"    data-metric="avg_gpft"    style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover (½s) for daily trend">GPFT: 0%</span>
                            <span class="badge bg-danger   fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-missing-badge"     data-metric="missing_count" data-filter="missing" style="font-weight:700;cursor:pointer;" title="Click to filter table · Hover ½s for daily trend">Missing L: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-map-badge"         data-metric="map_count"     data-filter="map" style="font-weight:700;cursor:pointer;background:#198754;color:#fff;" title="Click to filter table · Hover ½s for daily trend">Map: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-nmap-badge"        data-metric="nmap_count"    data-filter="nmap" style="font-weight:700;cursor:pointer;background:#a71d2a;color:#fff;" title="Click to filter table · Hover ½s for daily trend">N Map: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-zero-sold-badge"   data-metric="zero_sold"     data-filter="zero_sold" style="font-weight:700;cursor:pointer;background:#dc3545;color:#fff;" title="Click to filter table · Hover ½s for daily trend">0 Sold: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-more-sold-badge"   data-metric="more_sold"     data-filter="more_sold" style="font-weight:700;cursor:pointer;background:#b6e0fe;color:#0f172a;" title="Click to filter table · Hover ½s for daily trend">&gt;0 Sold: 0</span>
                            <span class="badge bg-secondary fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-roi-badge"    data-metric="avg_roi"     style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover (½s) for daily trend">ROI: 0%</span>
                        </div>
                    </div>

                    <div id="aliexpress-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadAePriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload AliExpress price sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="aePriceSheetFile" accept=".xlsx,.xls,.csv,.txt">
                    <small class="text-muted d-block mt-2">Headers: <strong>sku</strong>, <strong>price</strong>, optional <strong>stock</strong> (or ae_stock).</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="aeUploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge Trend Chart Modal – matches Amazon tabulator view UI --}}
    <div class="modal fade" id="aeBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width:80vw;width:80vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="aeBadgeChartTitle">Aliexpress – Badge Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="aeBadgeChartRange" class="form-select form-select-sm bg-white"
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
                    <div id="aeBadgeLineWrap" style="display:none;height:38vh;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="aeBadgeLineCanvas"></canvas>
                        </div>
                        <div id="aeBadgeStatPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="aeBadgeHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="aeBadgeMedian"  style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="aeBadgeLowest"  style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <!-- Bar chart -->
                    <div id="aeBadgeBarWrap" style="display:none;height:160px;margin-top:8px;">
                        <canvas id="aeBadgeBarCanvas"></canvas>
                    </div>
                    <div id="aeBadgeLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="aeBadgeNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No trend data yet. Data is saved each time the page loads.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="aeLmpModal" tabindex="-1" aria-labelledby="aeLmpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="aeLmpModalLabel"><i class="fas fa-link me-2"></i>LMP for <span id="aeLmpModalSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-3"><i class="fas fa-plus text-success me-1"></i> Add New LMP</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="aeLmpNewPrice" placeholder="e.g. 29.99">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0">Product Link</label>
                                <input type="text" class="form-control form-control-sm" id="aeLmpNewLink" placeholder="https://...">
                            </div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="aeLmpAddRowBtn"><i class="fas fa-plus me-1"></i> Add LMP</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="aeLmpClearFormBtn" title="Clear form"><i class="fas fa-undo"></i></button>
                            </div>
                        </div>
                    </div>
                    <h6 class="mb-2">LMP List</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="aeLmpListTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Price</th>
                                    <th>Link</th>
                                    <th style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="aeLmpEntriesContainer"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="aeLmpModalSaveBtn"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="aeEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="aeEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="aeSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="aeBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="aeSaveLinksBtn">Save</button>
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
        let aeLmpModalSku = '';

        function aeNotify(msg, type) {
            if (window.toastr) {
                if (type === 'warning') toastr.warning(msg);
                else if (type === 'error') toastr.error(msg);
                else toastr.success(msg);
                return;
            }
            let c = document.getElementById('aeNotifyToastContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'aeNotifyToastContainer';
                c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(c);
            }
            const t = document.createElement('div');
            const bg = type === 'error' ? '#dc3545' : (type === 'warning' ? '#fd7e14' : '#198754');
            t.style.cssText = 'min-width:220px;max-width:340px;color:#fff;background:' + bg + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;opacity:0;transition:opacity .25s ease;';
            t.textContent = msg;
            c.appendChild(t);
            requestAnimationFrame(function() { t.style.opacity = '1'; });
            setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2600);
        }

        // Badge-click filter flags (identical to TikTok pattern)
        let aeMissingActive  = false;
        let aeMapActive      = false;
        let aeNMapActive     = false;
        let aeZeroSoldActive = false;
        let aeMoreSoldActive = false;
        let aeBadgeHoverTimer = null;

        function aeClearBadgeHoverTimer() {
            if (aeBadgeHoverTimer) {
                clearTimeout(aeBadgeHoverTimer);
                aeBadgeHoverTimer = null;
            }
        }

        function aeHideBadgeChartModal() {
            const el = document.getElementById('aeBadgeChartModal');
            if (!el || typeof bootstrap === 'undefined') return;
            const inst = bootstrap.Modal.getInstance(el);
            if (inst) inst.hide();
        }

        function aeSyncFilterBadgeActiveClasses() {
            if (typeof jQuery === 'undefined') return;
            $('#ae-missing-badge').toggleClass('active-filter', aeMissingActive);
            $('#ae-map-badge').toggleClass('active-filter', aeMapActive);
            $('#ae-nmap-badge').toggleClass('active-filter', aeNMapActive);
            $('#ae-zero-sold-badge').toggleClass('active-filter', aeZeroSoldActive);
            $('#ae-more-sold-badge').toggleClass('active-filter', aeMoreSoldActive);
        }

        function aeApplyBadgeFilterFromUrl() {
            const badge = (new URLSearchParams(window.location.search).get('badge') || '').toLowerCase();
            if (!badge || !table) return;
            aeMissingActive = aeMapActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
            if (badge === 'missing') aeMissingActive = true;
            else if (badge === 'map') aeMapActive = true;
            else if (badge === 'nmap') aeNMapActive = true;
            else if (badge === 'zero_sold') aeZeroSoldActive = true;
            else if (badge === 'more_sold') aeMoreSoldActive = true;
            else return;
            aeSyncFilterBadgeActiveClasses();
            applyFilters();
        }

        // Price Mode (Decrease / Increase / Same Price)
        let decreaseModeActive = false;
        let increaseModeActive = false;
        let samePriceModeActive = false;
        let selectedSkus = new Set();

        function roundToRetailPrice(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            return Math.ceil(price) - 0.01;
        }

        function syncAeDiscountInputUi() {
            const $input = $('#ae-discount-input');
            if (samePriceModeActive) {
                $('#ae-discount-type-wrap').hide();
                $('#ae-discount-input-label').removeClass('d-none');
                $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
                $('#ae-apply-discount-btn').text('Apply Same Price');
            } else {
                $('#ae-discount-type-wrap').show();
                $('#ae-discount-input-label').addClass('d-none');
                const t = $('#ae-discount-type').val();
                $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
                $('#ae-apply-discount-btn').text('Apply');
            }
        }

        function syncPriceModeUi() {
            const $btn = $('#ae-price-mode-btn');
            const selectCol = table ? table.getColumn('_ae_select') : null;
            if (decreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary btn-info').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                if (selectCol) selectCol.show();
                syncAeDiscountInputUi();
                return;
            }
            if (increaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-info').addClass('btn-primary')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                if (selectCol) selectCol.show();
                syncAeDiscountInputUi();
                return;
            }
            if (samePriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-primary').addClass('btn-info')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                if (selectCol) selectCol.show();
                syncAeDiscountInputUi();
                return;
            }
            $btn.removeClass('btn-danger btn-primary btn-info').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price Mode');
            if (selectCol) selectCol.hide();
            selectedSkus.clear();
            updateSelectedCount();
            syncAeDiscountInputUi();
        }

        function updateSelectedCount() {
            const cnt = selectedSkus.size;
            $('#ae-selected-skus-count').text(`${cnt} SKU${cnt !== 1 ? 's' : ''} selected`);
            $('#ae-discount-container').toggle(cnt > 0 && (decreaseModeActive || increaseModeActive || samePriceModeActive));
        }

        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '{{ route("aliexpress.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates: updates },
                success: function(res) {
                    if (res.success) console.log('AE SPRICE saved:', res.updated);
                },
                error: function(xhr) {
                    console.error('AE SPRICE save error:', xhr.responseJSON);
                }
            });
        }

        function applyAeDiscount() {
            const discountType = $('#ae-discount-type').val();
            const discountVal  = parseFloat($('#ae-discount-input').val());
            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) return;
            if (isNaN(discountVal) || discountVal <= 0 || selectedSkus.size === 0) return;

            let updatedCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row     = rows[0];
                const rowData = row.getData();
                const currentPrice = parseFloat(rowData.price) || 0;
                // Same Price applies even when current price is empty;
                // Decrease / Increase still need a positive price to compute against.
                if (!samePriceModeActive && currentPrice <= 0) return;

                let newSprice;
                if (samePriceModeActive) {
                    newSprice = Math.max(0.99, discountVal);
                } else if (discountType === 'percentage') {
                    newSprice = increaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = increaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = roundToRetailPrice(Math.max(0.99, newSprice));

                const margin = parseFloat(rowData._margin) || 1;
                const lp     = parseFloat(rowData.lp)   || 0;
                const ship   = parseFloat(rowData.ship)  || 0;
                // Same formulas as GPFT / GROI
                const sgpft  = newSprice > 0 ? Math.round(((newSprice * margin - ship - lp) / newSprice) * 100) : 0;
                const sroi   = lp > 0        ? Math.round(((newSprice * margin - lp - ship)  / lp)       * 100) : 0;

                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            $('#ae-discount-input').val('');
        }

        function clearSpriceForSelected() {
            if (!selectedSkus.size) return;
            if (!confirm(`Clear SPRICE for ${selectedSkus.size} SKU(s)?`)) return;
            const updates = [];
            table.getRows().forEach(row => {
                const d = row.getData();
                if (selectedSkus.has(d.sku) && !d.is_parent) {
                    row.update({ sprice: 0, sgpft: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                }
            });
            if (updates.length) saveSpriceUpdates(updates);
        }

        function money(value) {
            return `$${(parseFloat(value) || 0).toFixed(2)}`;
        }

        /** INV vs AE stock = Map if diff ≤ 3 OR ≤ 3% of INV (same as amazon_tabulator_view). */
        function aeInvWithinMapTolerance(inv, aeStock) {
            const invNum = parseFloat(inv) || 0;
            const aeNum = parseFloat(aeStock) || 0;
            if (invNum <= 0) return true;
            const diff = Math.abs(invNum - aeNum);
            if (diff <= 3 + 1e-9) return true;
            return diff <= invNum * 0.03 + 1e-9;
        }

        /** True when row counts as Missing L (Amazon: INV>0, NR=REQ, not on AE export). */
        function aeRowIsMissingL(row) {
            if (!row || row.is_parent) return false;
            const inv = parseFloat(row.inv) || 0;
            const nr = (row.NR || '').trim();
            const isMissingAe = !!row.is_missing_aliexpress || (String(row.missing || '').trim().toUpperCase() === 'M');
            const price = parseFloat(row.price) || 0;
            return inv > 0 && nr === 'REQ' && (isMissingAe || price <= 0);
        }

        // ── applyFilters (mirrors TikTok applyFilters) ────────────────
        // Play / Pause parent navigation state
        let aePlayUniqueParents = [];
        let isAePlayActive = false;
        let currentAePlayParentIndex = -1;

        function normalizeAeParentKey(val) {
            if (val == null || val === '') return '';
            return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
        }
        function buildAeUniqueParents() {
            if (!table) return [];
            const allRows = table.getData('all') || [];
            const seen = {};
            const list = [];
            allRows.forEach(function(r) {
                const p = normalizeAeParentKey(r.parent);
                if (p && !seen[p]) { seen[p] = true; list.push(p); }
            });
            list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
            return list;
        }
        function updateAePlayButtonStates() {
            $('#play-backward').prop('disabled', !isAePlayActive || currentAePlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isAePlayActive || currentAePlayParentIndex >= aePlayUniqueParents.length - 1);
        }
        function startAePlay() {
            aePlayUniqueParents = buildAeUniqueParents();
            if (aePlayUniqueParents.length === 0) return;
            isAePlayActive = true;
            currentAePlayParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateAePlayButtonStates();
        }
        function stopAePlay() {
            isAePlayActive = false;
            currentAePlayParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyFilters();
            updateAePlayButtonStates();
        }
        function nextAeParent() {
            if (!isAePlayActive || currentAePlayParentIndex >= aePlayUniqueParents.length - 1) return;
            currentAePlayParentIndex++;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateAePlayButtonStates();
        }
        function previousAeParent() {
            if (!isAePlayActive || currentAePlayParentIndex <= 0) return;
            currentAePlayParentIndex--;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateAePlayButtonStates();
        }
        $('#play-auto').on('click', startAePlay);
        $('#play-pause').on('click', stopAePlay);
        $('#play-forward').on('click', nextAeParent);
        $('#play-backward').on('click', previousAeParent);

        function applyFilters() {
            if (!table) return;
            table.clearFilter();

            // Play navigation: only show current parent's group
            if (isAePlayActive && aePlayUniqueParents.length > 0 && currentAePlayParentIndex >= 0) {
                const currentKey = aePlayUniqueParents[currentAePlayParentIndex];
                if (currentKey) {
                    table.addFilter(function(d) {
                        const p = normalizeAeParentKey(d.parent);
                        return p === currentKey || p === ('PARENT ' + currentKey);
                    });
                }
                return;
            }

            const skuSearch  = ($('#pricing-sku-search').val() || '').toLowerCase().trim();
            const rowType    = $('#ae-row-type-filter').val();
            const invFilter  = $('#ae-inv-filter').val();
            const stockFilter= $('#ae-stock-filter').val();
            const gpftFilter = $('#ae-gpft-filter').val();
            const cvrFilter = $('#ae-cvr-filter').val();
            const roiFilter  = $('#ae-roi-filter').val();
            const al30Filter = $('#ae-al30-filter').val();
            const mapFilter  = $('#ae-map-filter').val();
            const dilColor   = $('.ae-dil-item.active').data('color') || 'all';

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }

            // Row type filter (All / Parents / SKUs) – same as Amazon
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

            // AE Stock filter
            if (stockFilter === 'zero') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) === 0);
            } else if (stockFilter === 'more') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) > 0);
            }

            // GPFT filter
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '50plus')   return gpft >= 50;
                    const [min, max] = gpftFilter.split('-').map(Number);
                    return gpft >= min && gpft < max;
                });
            }

            if (cvrFilter !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.ov_l30) || 0;
                    const sold = parseFloat(d.al30) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-2') return cvrRounded > 0 && cvrRounded <= 2;
                    if (cvrFilter === '2-4') return cvrRounded > 2 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // ROI% filter
            if (roiFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const roi = parseFloat(d.groi) || 0;
                    if (roiFilter === 'lt40')    return roi < 40;
                    if (roiFilter === '40-75')   return roi >= 40 && roi < 75;
                    if (roiFilter === '75-125')  return roi >= 75 && roi < 125;
                    if (roiFilter === 'gt125')   return roi >= 125;
                    return true;
                });
            }

            // AL30 filter (excludes 0 inventory rows, same as TikTok T L30)
            if (al30Filter !== 'all') {
                table.addFilter(function(d) {
                    if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                    const al30 = parseFloat(d.al30) || 0;
                    if (al30Filter === '0')      return al30 === 0;
                    if (al30Filter === '0-10')   return al30 > 0 && al30 <= 10;
                    if (al30Filter === '10plus') return al30 > 10;
                    return true;
                });
            }

            // Map filter (Amazon: listed, INV>0, NR=REQ, INV vs AE stock tolerance)
            if (mapFilter === 'map') {
                table.addFilter(d => {
                    if (d.is_parent) return false;
                    const inv = parseFloat(d.inv) || 0;
                    const nr = (d.NR || '').trim();
                    if (inv <= 0 || nr !== 'REQ' || d.is_missing_aliexpress) return false;
                    return parseFloat(d.price) > 0 && aeInvWithinMapTolerance(inv, d.ae_stock);
                });
            } else if (mapFilter === 'nmap') {
                table.addFilter(d => {
                    if (d.is_parent) return false;
                    const inv = parseFloat(d.inv) || 0;
                    const nr = (d.NR || '').trim();
                    if (inv <= 0 || nr !== 'REQ' || d.is_missing_aliexpress) return false;
                    if (parseFloat(d.price) <= 0) return false;
                    return !aeInvWithinMapTolerance(inv, d.ae_stock);
                });
            }

            // DIL% filter (identical to TikTok)
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

            // Badge-click filters
            if (aeMissingActive) {
                table.addFilter(d => aeRowIsMissingL(d));
            }
            if (aeMapActive) {
                table.addFilter(d => {
                    if (d.is_parent) return false;
                    const inv = parseFloat(d.inv) || 0;
                    const nr = (d.NR || '').trim();
                    if (inv <= 0 || nr !== 'REQ' || d.is_missing_aliexpress) return false;
                    return parseFloat(d.price) > 0 && aeInvWithinMapTolerance(inv, d.ae_stock);
                });
            }
            if (aeNMapActive) {
                table.addFilter(d => {
                    if (d.is_parent) return false;
                    const inv = parseFloat(d.inv) || 0;
                    const nr = (d.NR || '').trim();
                    if (inv <= 0 || nr !== 'REQ' || d.is_missing_aliexpress) return false;
                    const price = parseFloat(d.price) || 0;
                    if (price <= 0) return false;
                    return !aeInvWithinMapTolerance(inv, d.ae_stock);
                });
            }
            if (aeZeroSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            if (aeMoreSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
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

            let totalSales = 0, totalAl30 = 0, totalProfit = 0;
            let gpftSum = 0, gpftCount = 0;
            let roiSum  = 0, roiCount  = 0;
            let missingCount = 0, mapCount = 0, nmapCount = 0;
            let zeroSold = 0, moreSold = 0;

            rows.forEach(row => {
                if (row.is_parent) return;
                const al30   = parseFloat(row.al30)   || 0;
                const profit = parseFloat(row.profit) || 0;
                const inv    = parseFloat(row.inv)    || 0;
                const nr     = (row.NR || '').trim();
                const isMissingAe = !!row.is_missing_aliexpress;
                const rowPrice = parseFloat(row.price) || 0;
                const isMissingL = aeRowIsMissingL(row);

                if (!isMissingL) {
                    totalProfit += al30 * profit;
                    totalSales  += parseFloat(row.sales) || 0;

                    const gpft = parseFloat(row.gpft);
                    if (Number.isFinite(gpft)) { gpftSum += gpft; gpftCount++; }

                    const groi = parseFloat(row.groi);
                    if (Number.isFinite(groi)) { roiSum  += groi; roiCount++; }
                }

                totalAl30 += al30;
                if (al30 === 0) zeroSold++; else moreSold++;

                if (inv > 0 && nr === 'REQ') {
                    if (isMissingAe || rowPrice <= 0) {
                        missingCount++;
                    } else if (!isMissingAe && rowPrice > 0) {
                        if (aeInvWithinMapTolerance(inv, row.ae_stock)) {
                            mapCount++;
                        } else {
                            nmapCount++;
                        }
                    }
                }
            });

            const avgGpft = gpftCount > 0 ? gpftSum / gpftCount : 0;
            const avgRoi  = roiCount  > 0 ? roiSum  / roiCount  : 0;

            $('#ae-total-sales-badge').text(`Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#ae-total-al30-badge').text(`AL30: ${totalAl30.toLocaleString()}`);
            $('#ae-total-profit-badge').text(`Profit: $${Math.round(totalProfit).toLocaleString()}`);
            $('#ae-avg-gpft-badge').text(`GPFT: ${Math.round(avgGpft)}%`);
            $('#ae-missing-badge').text(`Missing L: ${missingCount.toLocaleString()}`);
            $('#ae-map-badge').text(`Map: ${mapCount.toLocaleString()}`);
            $('#ae-nmap-badge').text(`N Map: ${nmapCount.toLocaleString()}`);
            $('#ae-zero-sold-badge').text(`0 Sold: ${zeroSold.toLocaleString()}`);
            $('#ae-more-sold-badge').text(`>0 Sold: ${moreSold.toLocaleString()}`);
            if ($('#ae-avg-roi-badge').length) {
                $('#ae-avg-roi-badge').text(`ROI: ${Math.round(avgRoi)}%`);
            }
        }

        // ---- Edit Links (Buyer / Seller) ----
        let aeEditLinksRow = null;
        window.openAeEditLinksModal = function(row) {
            aeEditLinksRow = row;
            const d = row.getData();
            $('#aeEditLinksSku').text(d.sku || '');
            $('#aeSellerLinkInput').val(d.seller_link || '');
            $('#aeBuyerLinkInput').val(d.buyer_link || '');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('aeEditLinksModal')).show();
        };

        $(document).on('click', '#aeSaveLinksBtn', function() {
            if (!aeEditLinksRow) return;
            const sku = aeEditLinksRow.getData().sku;
            const sellerLink = $('#aeSellerLinkInput').val().trim();
            const buyerLink = $('#aeBuyerLinkInput').val().trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: '/aliexpress/save-links',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sku: sku,
                    seller_link: sellerLink,
                    buyer_link: buyerLink
                },
                success: function(res) {
                    if (res && res.success) {
                        aeEditLinksRow.update({
                            seller_link: res.seller_link || '',
                            buyer_link: res.buyer_link || ''
                        }).then(function() {
                            aeEditLinksRow.reformat();
                        }).catch(function() {
                            aeEditLinksRow.reformat();
                        });
                        aeNotify('Links saved successfully', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('aeEditLinksModal')).hide();
                    } else {
                        aeNotify((res && res.message) || 'Failed to save links', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                    aeNotify(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $(document).ready(function() {
            table = new Tabulator("#aliexpress-pricing-table", {
                ajaxURL: "/aliexpress/pricing-data",
                ajaxResponse: function(url, params, response) {
                    summaryDataCache = normalizeRows(response);
                    updateSummary(summaryDataCache);
                    setTimeout(aeApplyBadgeFilterFromUrl, 0);
                    return response;
                },
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('ae-parent-row');
                    }
                },
                columns: [
                    // ── Select checkbox (Price Mode) ──────────────────────
                    {
                        title: "<input type='checkbox' id='ae-select-all'>",
                        field: "_ae_select",
                        hozAlign: "center",
                        headerSort: false,
                        width: 38,
                        visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sku = d.sku;
                            const chk = selectedSkus.has(sku) ? 'checked' : '';
                            return `<input type='checkbox' class='ae-sku-chk' data-sku='${sku.replace(/'/g,"\\'")}' ${chk}>`;
                        }
                    },
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
                        title: "Links",
                        field: "links_column",
                        width: 55,
                        frozen: true,
                        hozAlign: "center",
                        headerSort: false,
                        tooltip: "Double-click to add / edit links",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const buyerLink = d.buyer_link || '';
                            const sellerLink = d.seller_link || '';
                            let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                            if (sellerLink) {
                                html += '<a href="' + sellerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                            }
                            if (buyerLink) {
                                html += '<a href="' + buyerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                            }
                            if (!sellerLink && !buyerLink) {
                                html += '<span class="text-muted" style="font-size:12px;">-</span>';
                            }
                            html += '</div>';
                            return html;
                        },
                        cellDblClick: function(e, cell) {
                            if (cell.getRow().getData().is_parent) return;
                            openAeEditLinksModal(cell.getRow());
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
                        title: "AE Stock",
                        field: "ae_stock",
                        sorter: "number",
                        hozAlign: "center",
                        width: 65,
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
                        title: "AL30",
                        field: "al30",
                        sorter: "number",
                        hozAlign: "center",
                        width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseInt(cell.getValue(), 10) || 0;
                            return `<span style="font-weight:700;">${v}</span>`;
                        }
                    },
                    {
                        title: "Price",
                        field: "price",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    {
                        title: "LMP",
                        field: "lmp",
                        hozAlign: "center",
                        sorter: "number",
                        headerSort: true,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            if (row.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const entries = row.lmp_entries || [];
                            const prices = entries.map(function(e) {
                                const p = e.price;
                                return (p !== null && p !== undefined && p !== '' && !isNaN(parseFloat(p))) ? parseFloat(p) : null;
                            }).filter(function(p) { return p !== null; });
                            const lowest = prices.length > 0 ? Math.min.apply(null, prices) : null;
                            const hasLmp = lowest !== null;
                            const displayNum = hasLmp ? (lowest % 1 === 0 ? lowest.toLocaleString() : lowest.toFixed(2)) : '';
                            const count = entries.length;
                            const skuEsc = (row.sku || '').replace(/"/g, '&quot;');
                            const redDot = '<span class="ae-lmp-missing-dot d-inline-flex align-items-center justify-content-center" style="width:14px;height:14px;border-radius:50%;background:#dc3545;box-shadow:0 0 0 1px rgba(0,0,0,.08);"></span>';
                            if (hasLmp) {
                                const title = displayNum + ' (' + count + ' entries) — click to edit';
                                return '<span class="ae-lmp-display d-inline-flex align-items-center gap-1">' + displayNum + '</span> ' +
                                    '<button type="button" class="btn btn-sm btn-link p-0 ae-lmp-eye-btn" data-sku="' + skuEsc + '" title="' + title + '"><i class="fas fa-info-circle text-info"></i></button>';
                            }
                            return '<button type="button" class="btn btn-sm btn-link p-0 ae-lmp-eye-btn d-inline-flex align-items-center justify-content-center border-0" data-sku="' + skuEsc + '" title="No LMP — click to add" style="min-width:auto;line-height:1;">' + redDot + '</button>';
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest('.ae-lmp-eye-btn')) {
                                e.stopPropagation();
                                const row = cell.getRow().getData();
                                aeOpenLmpModal(row.sku, row.lmp_entries || []);
                            }
                        }
                    },
                    {
                        title: "Missing L",
                        field: "missing",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            if (aeRowIsMissingL(d)) {
                                return '<span class="badge bg-danger">L</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "Map",
                        field: "map",
                        hozAlign: "center",
                        width: 90,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const inv = parseFloat(d.inv) || 0;
                            const nr = (d.NR || '').trim();
                            if (inv <= 0 || nr !== 'REQ' || d.is_missing_aliexpress) return '';
                            const rowPrice = parseFloat(d.price) || 0;
                            if (rowPrice <= 0) return '';
                            const aeStock = parseFloat(d.ae_stock) || 0;
                            if (aeInvWithinMapTolerance(inv, aeStock)) {
                                return '<span style="color:#198754;font-weight:bold;">Map</span>';
                            }
                            const diff = Math.round(Math.abs(inv - aeStock));
                            return `<span style="color:#dc3545;font-weight:bold;">N Map (${diff})</span>`;
                        }
                    },
                    {
                        title: "GPFT",
                        field: "gpft",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0 && !d.is_parent) return '0%';
                            if (v === 0 &&  d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:${d.is_parent?'700':'600'};">${Math.round(v)}%</span>`;
                        }
                    },
                    {
                        title: "GROI",
                        field: "groi",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            // Color ranges matching the ROI% filter dropdown
                            let color;
                            if      (v < 40)  color = '#a00211';
                            else if (v < 75)  color = '#ffc107';
                            else if (v < 125) color = '#28a745';
                            else              color = '#d63384';
                            return `<span style="color:${color};font-weight:600;">${Math.round(v)}%</span>`;
                        }
                    },
                    {
                        title: "Profit",
                        field: "profit",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return `<span style="color:${color};font-weight:700;">${money(v)}</span>`;
                            }
                            return money(v);
                        }
                    },
                    {
                        title: "Sales",
                        field: "sales",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                return `<span style="font-weight:700;">${money(v)}</span>`;
                            }
                            return money(v);
                        }
                    },
                    // {
                    //     title: "AL30",
                    //     field: "al30",
                    //     sorter: "number",
                    //     hozAlign: "center",
                    //     formatter: function(cell) {
                    //         const d = cell.getRow().getData();
                    //         const v = parseInt(cell.getValue(), 10) || 0;
                    //         return `<span style="font-weight:${d.is_parent?'700':'400'};">${v}</span>`;
                    //     }
                    // },
                    {
                        title: "LP",
                        field: "lp",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    {
                        title: "Ship",
                        field: "ship",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    {
                        title: "Sprice",
                        field: "sprice",
                        sorter: "number",
                        hozAlign: "right",
                        editor: "number",
                        editorParams: { min: 0, step: 0.01 },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            return `<span style="font-weight:600;padding:2px 6px;border-radius:3px;">${money(v)}</span>`;
                        }
                    },
                    {
                        title: "SGPFT",
                        field: "sgpft",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            // Same color coding as GPFT
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(v)}%</span>`;
                        }
                    },
                    {
                        title: "SROI",
                        field: "sroi",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            // Same color ranges as GROI
                            let color;
                            if      (v < 40)  color = '#a00211';
                            else if (v < 75)  color = '#ffc107';
                            else if (v < 125) color = '#28a745';
                            else              color = '#d63384';
                            return `<span style="color:${color};font-weight:600;">${Math.round(v)}%</span>`;
                        }
                    },
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

            $('#pricing-sku-search').on('input', function() { applyFilters(); });
            $('#ae-row-type-filter').on('change', function() { applyFilters(); });
            $('#ae-inv-filter').on('change',    function() { applyFilters(); });
            $('#ae-stock-filter').on('change',  function() { applyFilters(); });
            $('#ae-gpft-filter, #ae-cvr-filter').on('change',   function() { applyFilters(); });
            $('#ae-roi-filter').on('change',    function() { applyFilters(); });
            $('#ae-al30-filter').on('change',   function() { applyFilters(); });
            $('#ae-map-filter').on('change',    function() { applyFilters(); });

            // DIL dropdown (identical to TikTok manual dropdown)
            $(document).on('click', '.ae-dil-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.ae-manual-dropdown').toggleClass('show');
            });
            $(document).on('click', '.ae-dil-item', function(e) {
                e.preventDefault(); e.stopPropagation();
                $('.ae-dil-item').removeClass('active');
                $(this).addClass('active');
                const circle = $(this).find('.ae-sc').clone();
                $('#ae-dil-btn').html('').append(circle).append('DIL%');
                $(this).closest('.ae-manual-dropdown').removeClass('show');
                applyFilters();
            });
            $(document).on('click', function() {
                $('.ae-manual-dropdown').removeClass('show');
            });

            // ── Price Mode (Decrease / Increase / Same Price) ─────────────
            $('#ae-price-mode-btn').on('click', function() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    decreaseModeActive = true; increaseModeActive = false; samePriceModeActive = false;
                } else if (decreaseModeActive) {
                    decreaseModeActive = false; increaseModeActive = true;  samePriceModeActive = false;
                } else if (increaseModeActive) {
                    decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = true;
                } else {
                    decreaseModeActive = false; increaseModeActive = false; samePriceModeActive = false;
                }
                syncPriceModeUi();
            });

            $('#ae-discount-type').on('change', function() { syncAeDiscountInputUi(); });
            $('#ae-apply-discount-btn').on('click', function() { applyAeDiscount(); });
            $('#ae-discount-input').on('keypress', function(e) { if (e.which === 13) applyAeDiscount(); });
            $('#ae-clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

            /*
             * Target ROI% / Target GPFT% bulk apply (AliExpress, margin = per-row `_margin`)
             * ----------------------------------------------------------------------------
             * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered
             * target. AliExpress's server-side SGPFT / SROI formulas
             * (AliexpressController::saveSpriceUpdates lines 1555-1556) include shipping:
             *     SGPFT% = ((sprice * margin − lp − ship) / sprice) * 100
             *     SROI%  = ((sprice * margin − lp − ship) / lp)     * 100
             *   → sprice = (lp * (1 + ROI%/100)  + ship) / margin
             *   → sprice = (lp + ship) / (margin − GPFT%/100)
             * Optimistic SGPFT / SROI written client-side using the row's `_margin`
             * (MarketplacePercentage 'Aliexpress' / 100, default 1.0), then the existing
             * /aliexpress/save-sprice endpoint reconciles them server-side. Plain 2-decimal
             * rounding — no .99 / .49 retail snapping — because snapping would shift the
             * achieved SROI / SGPFT off the user-typed target.
             */
            function aeApplyTargetBackSolve(computeFn, labelPrefix) {
                if (selectedSkus.size === 0) {
                    aeNotify('Please check at least one SKU first (turn on Price Mode to reveal checkboxes)', 'warning');
                    return;
                }

                const updates     = [];
                let updatedCount  = 0;
                let skippedNoLp   = 0;
                const skippedHigh = [];

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (!rows.length) return;
                    const row     = rows[0];
                    const rowData = row.getData();
                    if (rowData.is_parent) return;

                    const lp = parseFloat(rowData.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const ship = parseFloat(rowData.ship) || 0;
                    const marginRaw = parseFloat(rowData._margin);
                    const margin = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 1;

                    const computed = computeFn(lp, ship, margin);
                    if (computed == null) { skippedHigh.push(sku); return; }
                    const newSprice = +computed.toFixed(2);
                    if (!isFinite(newSprice) || newSprice <= 0) return;

                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp - ship) / newSprice) * 100) : 0;
                    const sroi  = lp > 0       ? Math.round(((newSprice * margin - lp - ship) / lp)     * 100) : 0;

                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                    updates.push({ sku: sku, sprice: newSprice });
                    updatedCount++;
                });

                if (updates.length === 0) {
                    if (skippedHigh.length > 0) {
                        aeNotify(`${labelPrefix} too high — must be less than each row's take-home margin.`, 'error');
                    } else {
                        aeNotify('No checked rows have a usable LP > 0', 'warning');
                    }
                    return;
                }

                saveSpriceUpdates(updates);
                let note = '';
                if (skippedNoLp > 0)    note += ` (${skippedNoLp} skipped — no LP)`;
                if (skippedHigh.length) note += ` (${skippedHigh.length} skipped — target ≥ margin)`;
                aeNotify(`${labelPrefix} applied to ${updatedCount} SKU(s)${note}`, 'success');
            }

            $('#ae-apply-target-roi-btn').on('click', function () {
                const rawInput = $('#ae-target-roi-input').val();
                const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    aeNotify('Please enter a Target ROI%', 'error');
                    return;
                }
                if (!isFinite(targetRoiPct)) {
                    aeNotify('Target ROI% must be a number', 'error');
                    return;
                }

                const roiMultiplier = 1 + (targetRoiPct / 100);
                aeApplyTargetBackSolve(function (lp, ship, margin) {
                    return (lp * roiMultiplier + ship) / margin;
                }, `Target ROI ${targetRoiPct}%`);
            });

            $('#ae-apply-target-gpft-btn').on('click', function () {
                const rawInput = $('#ae-target-gpft-input').val();
                const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    aeNotify('Please enter a Target GPFT%', 'error');
                    return;
                }
                if (!isFinite(targetGpftPct)) {
                    aeNotify('Target GPFT% must be a number', 'error');
                    return;
                }

                const targetFraction = targetGpftPct / 100;
                aeApplyTargetBackSolve(function (lp, ship, margin) {
                    const denom = margin - targetFraction;
                    if (denom <= 0) return null; // signals "target ≥ margin" skip
                    return (lp + ship) / denom;
                }, `Target GPFT ${targetGpftPct}%`);
            });

            $('#ae-target-roi-input').on('keypress', function (e) {
                if (e.which === 13) $('#ae-apply-target-roi-btn').click();
            });
            $('#ae-target-gpft-input').on('keypress', function (e) {
                if (e.which === 13) $('#ae-apply-target-gpft-btn').click();
            });

            // Select all checkbox
            $(document).on('change', '#ae-select-all', function() {
                const checked = $(this).prop('checked');
                const rows = table.getData('active').filter(d => !d.is_parent);
                rows.forEach(d => { if (checked) selectedSkus.add(d.sku); else selectedSkus.delete(d.sku); });
                $('.ae-sku-chk').prop('checked', checked);
                updateSelectedCount();
            });

            // Individual checkbox
            $(document).on('change', '.ae-sku-chk', function() {
                const sku = $(this).data('sku');
                if ($(this).prop('checked')) selectedSkus.add(sku); else selectedSkus.delete(sku);
                updateSelectedCount();
            });

            // SPRICE cell edited – save immediately, recalculate SGPFT + SROI with proper margin
            table.on('cellEdited', function(cell) {
                if (cell.getField() !== 'sprice') return;
                const d = cell.getRow().getData();
                if (d.is_parent) return;
                const sku    = d.sku;
                const sprice = parseFloat(cell.getValue()) || 0;
                const margin = parseFloat(d._margin) || 1;
                const lp     = parseFloat(d.lp)   || 0;
                const ship   = parseFloat(d.ship)  || 0;
                // Same formulas as GPFT / GROI
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - ship - lp) / sprice) * 100) : 0;
                const sroi  = lp     > 0 ? Math.round(((sprice * margin - lp - ship)  / lp)    * 100) : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi });
                saveSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            // Click filter badges → table filter only (never chart)
            $(document).on('click', '.ae-filter-badge', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeClearBadgeHoverTimer();
                aeHideBadgeChartModal();

                const filterKey = String($(this).data('filter') || '').toLowerCase();
                aeMissingActive = aeMapActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;

                if (filterKey === 'missing') {
                    aeMissingActive = !aeMissingActive;
                } else if (filterKey === 'map') {
                    aeMapActive = !aeMapActive;
                } else if (filterKey === 'nmap') {
                    aeNMapActive = !aeNMapActive;
                } else if (filterKey === 'zero_sold') {
                    aeZeroSoldActive = !aeZeroSoldActive;
                } else if (filterKey === 'more_sold') {
                    aeMoreSoldActive = !aeMoreSoldActive;
                }

                aeSyncFilterBadgeActiveClasses();
                applyFilters();
            });

            $('#refresh-pricing-table').on('click', function() {
                table.setData("/aliexpress/pricing-data");
            });

            $('#export-pricing-btn').on('click', function() {
                table.download("csv", "aliexpress_analytics_data.csv");
            });

            function aeOpenLmpModal(sku, entries) {
                aeLmpModalSku = sku || '';
                $('#aeLmpModalSku').text(aeLmpModalSku);
                $('#aeLmpNewPrice').val('');
                $('#aeLmpNewLink').val('');
                const tbody = $('#aeLmpEntriesContainer');
                tbody.empty();
                const list = Array.isArray(entries) && entries.length > 0 ? entries : [];
                list.forEach(function(entry) {
                    aeAppendLmpTableRow(tbody, entry.price !== undefined && entry.price !== null ? entry.price : '', entry.link || '');
                });
                aeUpdateLmpLowestHighlight();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aeLmpModal')).show();
            }
            function aeAppendLmpTableRow(tbody, price, link) {
                const tr = $('<tr class="ae-lmp-entry-row">' +
                    '<td class="ae-lmp-num text-center align-middle"></td>' +
                    '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm ae-lmp-price border-0 bg-transparent" style="max-width:100px" placeholder="Price"> <span class="ae-lmp-lowest-badge"></span></td>' +
                    '<td class="align-middle"><input type="text" class="form-control form-control-sm ae-lmp-link d-inline-block me-1" style="max-width:220px" placeholder="https://..."> <a href="#" class="btn btn-sm btn-outline-primary ae-lmp-open-link" target="_blank" rel="noopener" title="Open link"><i class="fas fa-external-link-alt"></i></a></td>' +
                    '<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger ae-lmp-remove-row" title="Remove"><i class="fas fa-trash-alt"></i></button></td></tr>');
                tr.find('.ae-lmp-price').val(price !== '' && price != null ? price : '');
                tr.find('.ae-lmp-link').val(link || '');
                tbody.append(tr);
                tr.find('.ae-lmp-remove-row').on('click', function(e) {
                    e.preventDefault();
                    tr.remove();
                    aeRenumberLmpRows();
                    aeUpdateLmpLowestHighlight();
                });
                tr.find('.ae-lmp-price, .ae-lmp-link').on('input', function() { aeUpdateLmpLowestHighlight(); });
                tr.find('.ae-lmp-open-link').on('click', function(e) {
                    e.preventDefault();
                    const href = (tr.find('.ae-lmp-link').val() || '').trim();
                    if (href && (href.startsWith('http://') || href.startsWith('https://'))) window.open(href, '_blank');
                });
                aeRenumberLmpRows();
            }
            function aeRenumberLmpRows() {
                $('#aeLmpEntriesContainer .ae-lmp-entry-row').each(function(i) {
                    $(this).find('.ae-lmp-num').text(i + 1);
                });
            }
            function aeUpdateLmpLowestHighlight() {
                let minVal = null;
                let minTr = null;
                $('#aeLmpEntriesContainer .ae-lmp-entry-row').each(function() {
                    const tr = $(this);
                    tr.removeClass('table-dark');
                    tr.find('.ae-lmp-lowest-badge').empty();
                    const val = tr.find('.ae-lmp-price').val();
                    const num = val !== '' && val != null ? parseFloat(val) : null;
                    if (num !== null && !isNaN(num)) {
                        if (minVal === null || num < minVal) { minVal = num; minTr = tr; }
                    }
                });
                if (minTr && minVal !== null) {
                    minTr.addClass('table-dark');
                    minTr.find('.ae-lmp-lowest-badge').html(' <span class="badge bg-info">LOWEST</span>');
                }
            }
            $('#aeLmpAddRowBtn').on('click', function() {
                const price = $('#aeLmpNewPrice').val();
                const link = $('#aeLmpNewLink').val();
                if (!price && !link) {
                    aeNotify('Enter Price or Link', 'warning');
                    return;
                }
                aeAppendLmpTableRow($('#aeLmpEntriesContainer'), price || '', link || '');
                $('#aeLmpNewPrice').val('');
                $('#aeLmpNewLink').val('');
            });
            $('#aeLmpClearFormBtn').on('click', function() {
                $('#aeLmpNewPrice').val('');
                $('#aeLmpNewLink').val('');
            });
            $('#aeLmpModalSaveBtn').on('click', function() {
                const entries = [];
                $('#aeLmpEntriesContainer .ae-lmp-entry-row').each(function() {
                    const price = $(this).find('.ae-lmp-price').val();
                    const link = $(this).find('.ae-lmp-link').val();
                    if (price || link) entries.push({ price: price ? parseFloat(price) : null, link: link ? link.trim() : null });
                });
                if (entries.length === 0) {
                    aeNotify('Add at least one price or link', 'warning');
                    return;
                }
                $(this).prop('disabled', true);
                $.ajax({
                    url: '{{ route("aliexpress.lmp.save") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: aeLmpModalSku,
                        lmp_entries: entries
                    },
                    success: function() {
                        aeNotify('LMP saved successfully', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('aeLmpModal')).hide();
                        if (table) table.setData('/aliexpress/pricing-data');
                    },
                    error: function() {
                        aeNotify('Failed to save LMP', 'error');
                    },
                    complete: function() {
                        $('#aeLmpModalSaveBtn').prop('disabled', false);
                    }
                });
            });

            $('#aeUploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('aePriceSheetFile').files[0];
                if (!file) {
                    aeNotify('Please select a file first.', 'warning');
                    return;
                }
                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');
                const $btn = $(this);
                $btn.prop('disabled', true);
                $.ajax({
                    url: '{{ route("aliexpress.pricing.upload.price") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        aeNotify(response.message || 'Upload completed.', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('uploadAePriceModal')).hide();
                        $('#aePriceSheetFile').val('');
                        if (table) {
                            table.setData('/aliexpress/pricing-data');
                        }
                    },
                    error: function(xhr) {
                        const message = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Upload failed.';
                        aeNotify(message, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // ── Badge Trend Chart (mirrors TikTok ttBadgeChart) ──────
            let aeBadgeLineChart = null;
            let aeBadgeBarChart  = null;
            let aeBadgeMetric    = '';
            let aeBadgeDays      = 30;
            let aeBadgeAjax      = null;

            const aeDollarMetrics  = ['total_pft','total_sales','total_cogs'];
            const aeCountMetrics   = ['total_sku','total_al30','missing_count','map_count','nmap_count','zero_sold','more_sold'];
            const aePercentMetrics = ['avg_gpft','avg_roi'];

            const aeBadgeLabels = {
                total_pft: 'Profit',   total_sales: 'Sales',   total_al30: 'AL30',
                avg_gpft: 'GPFT%',            avg_roi: 'ROI%',
                total_cogs: 'COGS',           missing_count: 'Missing L',     map_count: 'Map',
                nmap_count: 'N Map',         total_sku: 'Total SKU',       zero_sold: '0 Sold',          more_sold: '>0 Sold',
            };

            function aeFormatChartVal(v) {
                const n = Number(v) || 0;
                if (aeDollarMetrics.includes(aeBadgeMetric))  return '$' + Math.round(n).toLocaleString('en-US');
                if (aePercentMetrics.includes(aeBadgeMetric)) return Math.round(n) + '%';
                return Math.round(n).toLocaleString('en-US');
            }

            function aeRenderCharts(points) {
                if (!Array.isArray(points) || !points.length) return false;

                const labels = points.map(p => p.date);
                const values = points.map(p => Number(p.value) || 0);
                const sorted = [...values].sort((a, b) => a - b);
                const mid    = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 ? sorted[mid] : (sorted[mid-1] + sorted[mid]) / 2;
                const highest = sorted[sorted.length - 1];
                const lowest  = sorted[0];

                $('#aeBadgeHighest').text(aeFormatChartVal(highest));
                $('#aeBadgeMedian').text(aeFormatChartVal(median));
                $('#aeBadgeLowest').text(aeFormatChartVal(lowest));

                const lineCtx = document.getElementById('aeBadgeLineCanvas');
                const barCtx  = document.getElementById('aeBadgeBarCanvas');
                if (!lineCtx || typeof Chart === 'undefined') return false;

                if (aeBadgeLineChart) aeBadgeLineChart.destroy();
                if (aeBadgeBarChart)  aeBadgeBarChart.destroy();

                const label = aeBadgeLabels[aeBadgeMetric] || aeBadgeMetric;

                // Point colors: red if below median, green if above
                const pointColors = values.map(v => v >= median ? '#28a745' : '#dc3545');

                // Register datalabels plugin globally if available
                if (typeof ChartDataLabels !== 'undefined') {
                    Chart.register(ChartDataLabels);
                }

                // ── Line chart with value labels on each point ──────────
                aeBadgeLineChart = new Chart(lineCtx.getContext('2d'), {
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
                                ticks: { callback: v => aeFormatChartVal(v), font: { size: 11 } },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: { ticks: { font: { size: 10 }, maxRotation: 45 } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => label + ': ' + aeFormatChartVal(ctx.parsed.y) } },
                            datalabels: typeof ChartDataLabels !== 'undefined' ? {
                                align: 'top', anchor: 'end',
                                font: { size: 10, weight: '600' },
                                color: ctx => ctx.dataset.pointBackgroundColor[ctx.dataIndex],
                                formatter: v => aeFormatChartVal(v),
                                clip: false
                            } : false
                        }
                    }
                });

                // ── Bar chart ────────────────────────────────────────────
                aeBadgeBarChart = new Chart(barCtx.getContext('2d'), {
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
                            y: { ticks: { callback: v => aeFormatChartVal(v), font: { size: 10 } }, beginAtZero: false },
                            x: { ticks: { maxRotation: 45, font: { size: 9 } } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => label + ': ' + aeFormatChartVal(ctx.parsed.y) } },
                            datalabels: { display: false }
                        }
                    }
                });
                return true;
            }

            function aeLoadChart() {
                if (!aeBadgeMetric) return;
                if (aeBadgeAjax) aeBadgeAjax.abort();
                $('#aeBadgeNoData,#aeBadgeLineWrap,#aeBadgeBarWrap').hide();
                $('#aeBadgeLoading').show();

                aeBadgeAjax = $.ajax({
                    url: '{{ route("aliexpress.badge.chart") }}',
                    method: 'GET',
                    data: { metric: aeBadgeMetric, days: aeBadgeDays },
                    success: function(res) {
                        aeBadgeAjax = null;
                        $('#aeBadgeLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                if (aeRenderCharts(pts)) {
                            $('#aeBadgeLineWrap').css('display','flex');
                            $('#aeBadgeBarWrap').show();
                        } else {
                            $('#aeBadgeNoData').show();
                        }
                    },
                    error: function() {
                        aeBadgeAjax = null;
                        $('#aeBadgeLoading').hide();
                        $('#aeBadgeNoData').show();
                    }
                });
            }

            function aeOpenBadgeChartModal(metricKey) {
                aeBadgeMetric = metricKey;
                aeBadgeDays   = 30;
                $('#aeBadgeChartRange').val('30');
                $('#aeBadgeChartTitle').text('Aliexpress – ' + (aeBadgeLabels[aeBadgeMetric] || aeBadgeMetric) + ' Trend');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aeBadgeChartModal')).show();
                aeLoadChart();
            }

            $(document).on('mouseenter', '.ae-hover-chart', function() {
                const metric = $(this).data('metric');
                if (!metric) return;
                aeClearBadgeHoverTimer();
                aeBadgeHoverTimer = setTimeout(function() {
                    aeOpenBadgeChartModal(metric);
                }, 500);
            });
            $(document).on('mouseleave', '.ae-hover-chart', function() {
                aeClearBadgeHoverTimer();
            });
            $(document).on('mousedown', '.ae-hover-chart.ae-filter-badge', function() {
                aeClearBadgeHoverTimer();
            });

            $(document).on('click', '.ae-badge-chart', function(e) {
                if ($(this).hasClass('ae-filter-badge')) return;
                e.stopPropagation();
                const m = $(this).data('metric');
                if (m) aeOpenBadgeChartModal(m);
            });

            $(document).on('change', '#aeBadgeChartRange', function() {
                const d = parseInt($(this).val(), 10) || 30;
                if (d === aeBadgeDays) return;
                aeBadgeDays = d;
                aeLoadChart();
            });
        });
    </script>
@endsection
