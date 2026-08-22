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
            white-space: nowrap; height: 80px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }

        /* Parent summary rows — match /shopify-b2b-pricing */
        .tabulator-row.ae-parent-row,
        .tabulator-row.ae-parent-row .tabulator-cell {
            background-color: rgba(189, 224, 255, 0.55) !important;
            font-weight: 600 !important;
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
            background-color: rgba(189, 224, 255, 0.8) !important;
        }

        /* Pagination label — match /shopify-b2b-pricing */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        #aliexpress-pricing-table {
            width: 100% !important;
        }
        #aliexpress-pricing-table .tabulator-tableholder {
            overflow-x: auto !important;
        }
        #aliexpress-pricing-table .tabulator-cell {
            white-space: nowrap !important;
            text-overflow: clip !important;
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

        /* Summary badges — Amazon-style wrap + gap */
        #summary-stats .d-flex { gap: 8px !important; }
        #summary-stats .badge {
            font-size: 1rem;
            white-space: nowrap;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
        }
        #summary-stats .ae-filter-badge.active-filter {
            outline: 3px solid #0d6efd;
            outline-offset: 2px;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.35);
        }

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #aeBadgeChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #aeBadgeChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #aeBadgeChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'aliexpress'])
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Aliexpress - Analytics',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="col-12">
            @php
                $canAeApiSync = strtolower(trim((string) (auth()->user()->email ?? ''))) === 'software@5core.com';
            @endphp
            @if($canAeApiSync)
            <div class="card border-primary mb-3">
                <div class="card-header bg-primary bg-opacity-10 py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <strong><i class="fas fa-cloud-download-alt me-1"></i> AliExpress API sync</strong>
                        <span class="text-muted small ms-2">
                            <strong>Price</strong> → list price + AE stock into <code>aliexpress_pricing_prices</code>.
                            <strong>Orders</strong> → AL30 into <code>aliexpress_metric</code>.
                        </span>
                    </div>
                    <span class="badge bg-secondary" title="marketplace_percentages.percentage (marketplace = Aliexpress)">
                        Margin: {{ number_format((float) ($marginPercent ?? 89), 2) }}%
                    </span>
                </div>
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="ae-sync-price-btn" title="Pull listed product price + stock from AliExpress API">
                        <i class="fas fa-tag"></i> Sync Price
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="ae-sync-orders-btn" title="Pull last 60 days of orders (AL30) from AliExpress API">
                        <i class="fas fa-shopping-cart"></i> Sync Orders
                    </button>
                    <button type="button" class="btn btn-sm btn-info" id="ae-sync-views-btn" title="Pull L30 page views + CVR from AliExpress API">
                        <i class="fas fa-eye"></i> Sync Views
                    </button>
                    <span id="ae-sync-api-status" class="small text-muted"></span>
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-body">

                    {{-- ── Row 1: Summary badges (Amazon order) ── --}}
                    <div id="summary-stats" class="mb-2 p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Summary metrics">
                            <span class="badge bg-dark fs-6 p-2" id="ae-rows-count-badge"
                                style="color:#fff;font-weight:700;"
                                title="Number of rows currently shown (after filters)">Row: 0</span>

                            <span class="badge bg-primary fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-sales-badge"
                                data-metric="total_sales" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click or hover (½s) for daily trend">Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-gpft-badge"
                                data-metric="avg_gpft" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click or hover (½s) for daily trend">GPFT: 0%</span>
                            <span class="badge bg-success fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-profit-badge"
                                data-metric="total_pft" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click or hover (½s) for daily trend">PFT: $0</span>
                            <span class="badge fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-roi-badge"
                                data-metric="avg_roi"
                                style="background-color:#6f42c1;color:#fff;font-weight:700;cursor:pointer;"
                                title="Click or hover (½s) for daily trend">GROI: 0%</span>

                            <span class="badge bg-info fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-views-badge"
                                data-metric="total_views" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Σ AliExpress L30 page views (API viewedCount)">Views: 0</span>
                            <span class="badge bg-success fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-cvr-badge"
                                data-metric="cvr" style="color:#111;font-weight:700;cursor:pointer;"
                                title="CVR = Σ outputOrder ÷ Σ L30 page views × 100 (API)">CVR: 0%</span>

                            <span class="badge bg-success fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-sold-pct-badge"
                                data-metric="more_sold" data-filter="more_sold"
                                style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click to filter AL30 &gt; 0 (INV &gt; 0). Hover ½s for daily trend">
                                Sold &gt;0: <span id="ae-more-sold-count">0</span>
                            </span>
                            <span class="badge bg-danger fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-zero-sold-badge"
                                data-metric="zero_sold" data-filter="zero_sold"
                                style="color:#fff;font-weight:700;cursor:pointer;"
                                title="Click to filter AL30 = 0 (INV &gt; 0). Hover ½s for daily trend">
                                0 Sold: <span id="ae-zero-sold-count">0</span>
                            </span>

                            @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'aliexpress-lmp-missing-badge', 'lmpChannelKey' => 'aliexpress'])
                            @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'aliexpress-price-gt-lmp-badge', 'pglChannelKey' => 'aliexpress', 'pglPriceField' => 'price'])
                            @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'aliexpress-price-lt80-lmp-badge', 'pltChannelKey' => 'aliexpress', 'pltPriceField' => 'price'])
                            <span class="badge fs-6 p-2" id="aliexpress-blue-triangle-badge"
                                style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                                title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                                <i class="fas fa-exclamation-triangle"></i> 0</span>
                        </div>
                    </div>

                    {{-- ── Row 2: Filter bar ── --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                        <span class="badge bg-dark fs-6 p-2" id="ae-margin-badge" style="font-weight:700;"
                            title="Take-home keep-rate from marketplace_percentages (marketplace = Aliexpress). Used as _margin in GROI / GPFT / SROI / SGPFT.">
                            Margin: {{ number_format((float) ($marginPercent ?? 89), 2) }}%
                        </span>

                        {{-- Row type filter (All Rows / Parents / SKUs) – same as Amazon --}}
                    <select id="ae-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                        <option value="all">All Rows</option>
                        <option value="parents">Parents</option>
                        <option value="skus" selected>SKUs</option>
                    </select>

                    {{-- Inventory filter --}}
                        <select id="ae-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>

                        {{-- GPFT% + CVR% (AliExpress API: outputOrder ÷ viewedCount) --}}
                        <select id="ae-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="ae-cvr-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-2">0-2%</option>
                            <option value="2-4">2-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>

                        {{-- ROI% filter --}}
                        <select id="ae-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>

                        {{-- AL30 filter — hidden (badge removed; Sold % still uses AL30) --}}
                        <select id="ae-al30-filter" class="form-select form-select-sm" style="width:130px;display:none;" title="Excludes 0 inventory items">
                            <option value="all">AL30</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
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

                        <button type="button" id="refresh-pricing-table" class="btn btn-sm btn-outline-primary" title="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="ae-push-price-btn" class="btn btn-sm btn-dark"
                            title="Push each selected SKU's SPRICE (or Price if no SPRICE) live to AliExpress">
                            <i class="fas fa-cloud-upload-alt"></i> Ali
                        </button>
                        <button type="button" id="export-pricing-btn" class="btn btn-sm btn-success" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'aliexpress'])
                        <a href="{{ route('aliexpress.lmp.sample') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download"></i> LMP sample
                        </a>
                        <a href="{{ route('aliexpress.lmp') }}" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-table"></i> LMP sheet
                        </a>

                        {{-- Target ROI% / GPFT% — Amazon-style 🎯 + icon-only Apply --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="ae-target-roi-controls"
                            title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin on every checked row (back-solves so SROI column equals the target)">
                            <label for="ae-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                            </label>
                            <input type="number" id="ae-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;"
                                title="Target ROI% applied to all checked rows when you click Apply">
                            <button id="ae-apply-target-roi-btn" class="btn btn-sm btn-primary" type="button"
                                title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin for every checked row">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="ae-target-gpft-controls"
                            title="Target GPFT% — sets S PRC = (LP + Ship) / (margin − Target GPFT%/100) on every checked row (back-solves so SGPFT column equals the target)">
                            <label for="ae-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                            </label>
                            <input type="number" id="ae-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;"
                                title="Target GPFT% applied to all checked rows when you click Apply. Must be less than each row's take-home margin.">
                            <button id="ae-apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button"
                                title="Compute & save S PRC = (LP + Ship) / (margin − Target GPFT%/100) for every checked row">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        {{-- Column Visibility Dropdown (persists in channel_tabulator_column_settings, channel='aliexpress_pricing') --}}
                        <div class="dropdown d-inline-block ms-2">
                            <button class="btn btn-sm btn-secondary" type="button"
                                id="ae-column-visibility-dropdown" data-bs-toggle="dropdown" aria-expanded="false"
                                title="Columns">
                                <i class="fa fa-eye"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="ae-column-visibility-dropdown"
                                id="ae-column-dropdown-menu" style="max-height: 400px; overflow-y: auto;">
                                {{-- Populated by JS --}}
                            </ul>
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

                    <div id="aliexpress-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                        <div id="aliexpress-pricing-table" style="flex: 1;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge Trend Chart Modal – matches Amazon tabulator view UI --}}
    <div class="modal fade p-0" id="aeBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
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
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'aliexpress'])
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script>
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'aliexpress'])
        let table = null;
        let allTableData = [];
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
        let aeZeroSoldActive = false;
        let aeMoreSoldActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;
        let lmpMissingFilterActive = false;
        let blueTriangleFilterActive = false;
        let aeBadgeHoverTimer = null;

        function aeRowSpriceForAlert(data) {
            if (!data || data.is_parent) return 0;
            let sprice = parseFloat(data.sprice || data.SPRICE) || 0;
            if (typeof chPromoSpriceFromStdTPromo === 'function') {
                const calc = chPromoSpriceFromStdTPromo(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function aeHasBlueTriangle(data) {
            if (!data || data.is_parent) return false;
            const sprice = aeRowSpriceForAlert(data);
            const price = parseFloat(data.price) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function syncAeTriangleBadgeState() {
            $('#aliexpress-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

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
            $('#ae-sold-pct-badge').toggleClass('active-filter', aeMoreSoldActive);
            $('#ae-zero-sold-badge').toggleClass('active-filter', aeZeroSoldActive);
        }

        function aeApplyBadgeFilterFromUrl() {
            const badge = (new URLSearchParams(window.location.search).get('badge') || '').toLowerCase();
            if (!badge || !table) return;
            aeZeroSoldActive = aeMoreSoldActive = false;
            if (badge === 'zero_sold') aeZeroSoldActive = true;
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
            syncAeDiscountInputUi();
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const cnt = selectedSkus.size;
            $('#ae-selected-skus-count').text(`${cnt} SKU${cnt !== 1 ? 's' : ''} selected`);
            $('#ae-discount-container').toggle(decreaseModeActive || increaseModeActive || samePriceModeActive);
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

        function aeSamePriceTargetRows() {
            if (!table) return [];
            const visible = (typeof table.getRows === 'function' ? table.getRows('active') : []) || [];
            return visible.filter(function(row) {
                const d = row.getData() || {};
                return !d.is_parent && d.sku;
            });
        }

        function applyAeDiscount() {
            const discountType = $('#ae-discount-type').val();
            const discountVal  = parseFloat($('#ae-discount-input').val());
            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) return;
            if (isNaN(discountVal) || discountVal <= 0) return;

            const targetRows = aeSamePriceTargetRows();
            if (!targetRows.length) return;

            let updatedCount = 0;
            const updates = [];

            targetRows.forEach(row => {
                const rowData = row.getData();
                const sku = rowData.sku;
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

        /** Std Prc vs channel price: reduce / hold / increase → red / yellow / green. */
        function aeStdPrcChangeDotMeta(stdPrc, comparePrice) {
            const sp = parseFloat(stdPrc);
            const ap = parseFloat(comparePrice);
            if (!isFinite(sp) || sp <= 0 || !isFinite(ap) || ap <= 0) return null;
            const sp2 = sp.toFixed(2);
            const ap2 = ap.toFixed(2);
            if (parseFloat(sp2) < parseFloat(ap2)) {
                return { kind: 'reduce', color: '#dc3545', title: 'Reduce vs channel price' };
            }
            if (parseFloat(sp2) > parseFloat(ap2)) {
                return { kind: 'increase', color: '#28a745', title: 'Increase vs channel price' };
            }
            return null;
        }

        function aeStdPrcChangeDotHtml(stdPrc, comparePrice) {
            const meta = aeStdPrcChangeDotMeta(stdPrc, comparePrice);
            if (!meta) return '';
            return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
                'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
        }

        function applyAeStandardPriceToLinkedRows(sku, std, appliedSkus) {
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
                if (!d || d.is_parent) return;
                const rowSku = String(d.sku || d['(Child) sku'] || d.SKU || '').trim();
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
            applyAeStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
        });

        function money(value) {
            return `$${(parseFloat(value) || 0).toFixed(2)}`;
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

        function aeIsParentRow(d) {
            if (!d) return false;
            if (d.is_parent === true || d.is_parent === 1 || d.is_parent === '1' || d.is_parent === 'true') return true;
            if (d.is_parent_summary === true || d.is_parent_row === true) return true;
            // Hide summary rows whose SKU starts with PARENT (e.g. "PARENT 04 CS")
            const sku = String(d.sku || '').trim().toUpperCase();
            if (/^PARENT\b/.test(sku)) return true;
            const p = String(d.parent || '').trim().toUpperCase();
            return /^PARENT\b/.test(p);
        }

        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
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
            const rowType    = $('#ae-row-type-filter').val() || 'skus';
            const invFilter  = $('#ae-inv-filter').val();
            const gpftFilter = $('#ae-gpft-filter').val();
            const cvrFilter = $('#ae-cvr-filter').val();
            const roiFilter  = $('#ae-roi-filter').val();
            const al30Filter = $('#ae-al30-filter').val();
            const dilColor   = $('.ae-dil-item.active').data('color') || 'all';
            // Parents / All: parent summary rows bypass metric filters (same as Amazon).
            // SKUs: parent rows are removed by the dedicated filter below.
            const parentRowsBypass = (rowType === 'parents' || rowType === 'all');

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }

            // Inventory filter
            if (invFilter === 'zero') {
                table.addFilter(d => {
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    return (parseInt(d.inv, 10) || 0) === 0;
                });
            } else if (invFilter === 'more') {
                table.addFilter(d => {
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    return (parseInt(d.inv, 10) || 0) > 0;
                });
            }

            // GPFT filter
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '50plus')   return gpft >= 50;
                    const [min, max] = gpftFilter.split('-').map(Number);
                    return gpft >= min && gpft < max;
                });
            }

            if (cvrFilter !== 'all') {
                table.addFilter(function(d) {
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    const cvrRounded = Math.round((parseFloat(d.cvr) || 0) * 100) / 100;
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
                    if (aeIsParentRow(d)) return parentRowsBypass;
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
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                    const al30 = parseFloat(d.al30) || 0;
                    if (al30Filter === '0')      return al30 === 0;
                    if (al30Filter === '0-10')   return al30 > 0 && al30 <= 10;
                    if (al30Filter === '10plus') return al30 > 10;
                    return true;
                });
            }

            // DIL% filter (identical to TikTok)
            if (dilColor !== 'all') {
                table.addFilter(function(d) {
                    if (aeIsParentRow(d)) return parentRowsBypass;
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

            // Badge-click filters — Sold % ignores INV = 0
            if (aeZeroSoldActive) {
                table.addFilter(d => {
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    return (parseInt(d.inv, 10) || 0) > 0 && (parseFloat(d.al30) || 0) === 0;
                });
            }
            if (aeMoreSoldActive) {
                table.addFilter(d => {
                    if (aeIsParentRow(d)) return parentRowsBypass;
                    return (parseInt(d.inv, 10) || 0) > 0 && (parseFloat(d.al30) || 0) > 0;
                });
            }
            if (lmpMissingFilterActive && window.LmpMissingBadge) {
                table.addFilter(function(data) {
                    if (aeIsParentRow(data)) return parentRowsBypass;
                    return !LmpMissingBadge.isParentRow(data) && !LmpMissingBadge.hasLmp(data);
                });
            }
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    if (aeIsParentRow(data)) return parentRowsBypass;
                    return PriceGtLmpBadge.hasRedTriangle(data, 'price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    if (aeIsParentRow(data)) return parentRowsBypass;
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'price');
                });
            }
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    if (aeIsParentRow(data)) return parentRowsBypass;
                    return aeHasBlueTriangle(data);
                });
            }

            // Row type last (Amazon): default SKUs hides PARENT* summary rows
            if (rowType === 'parents') {
                table.addFilter(function(d) { return aeIsParentRow(d); });
            } else if (rowType === 'skus') {
                table.addFilter(function(d) { return !aeIsParentRow(d); });
            }

            try { table.setPage(1); } catch (e) {}
        }

        if (window.LmpMissingBadge) {
            LmpMissingBadge.bind({
                badge: '#aliexpress-lmp-missing-badge',
                getActive: function() { return lmpMissingFilterActive; },
                onToggle: function(on) {
                    lmpMissingFilterActive = on;
                    if (on) {
                        priceGtLmpFilterActive = false;
                        priceLt80LmpFilterActive = false;
                        blueTriangleFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#aliexpress-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) {
                        blueTriangleFilterActive = false;
                        lmpMissingFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#aliexpress-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) {
                        blueTriangleFilterActive = false;
                        lmpMissingFilterActive = false;
                    }
                    applyFilters();
                }
            });
        }
        $('#aliexpress-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                lmpMissingFilterActive = false;
                aeZeroSoldActive = aeMoreSoldActive = false;
            }
            applyFilters();
        });

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
            let zeroSold = 0, moreSold = 0;
            let totalViews = 0, totalOutputOrder = 0;
            const seenViewProducts = {};

            rows.forEach(row => {
                if (aeIsParentRow(row)) return;
                const views = parseInt(row.views, 10) || 0;
                const outputOrder = parseInt(row.output_order, 10) || 0;
                const pid = (row.ae_product_id || '').toString().trim();
                if (pid) {
                    if (!seenViewProducts[pid]) {
                        seenViewProducts[pid] = true;
                        totalViews += views;
                        totalOutputOrder += outputOrder;
                    }
                } else {
                    totalViews += views;
                    totalOutputOrder += outputOrder;
                }
                const al30   = parseFloat(row.al30)   || 0;
                const profit = parseFloat(row.profit) || 0;
                const inv    = parseInt(row.inv, 10) || 0;

                totalProfit += al30 * profit;
                totalSales  += parseFloat(row.sales) || 0;

                const gpft = parseFloat(row.gpft);
                if (Number.isFinite(gpft)) { gpftSum += gpft; gpftCount++; }

                const groi = parseFloat(row.groi);
                if (Number.isFinite(groi)) { roiSum  += groi; roiCount++; }

                totalAl30 += al30;
                if (inv <= 0) return;
                if (al30 === 0) zeroSold++; else moreSold++;
            });

            const avgGpft = gpftCount > 0 ? gpftSum / gpftCount : 0;
            const avgRoi  = roiCount  > 0 ? roiSum  / roiCount  : 0;

            let visibleCount = rows.length;
            if (table && typeof table.getData === 'function') {
                const active = table.getData('active') || [];
                if (active.length) visibleCount = active.length;
            }
            $('#ae-rows-count-badge').text('Row: ' + visibleCount.toLocaleString());

            $('#ae-total-sales-badge').text(`Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#ae-total-profit-badge').text(`PFT: $${Math.round(totalProfit).toLocaleString()}`);
            $('#ae-avg-gpft-badge').text(`GPFT: ${Math.round(avgGpft)}%`);
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#aliexpress-price-gt-lmp-badge', table.getData(), 'aliexpress', 'price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#aliexpress-price-lt80-lmp-badge', table.getData(), 'aliexpress', 'price');
                }
            }
            if (window.LmpMissingBadge && table) {
                LmpMissingBadge.update('#aliexpress-lmp-missing-badge', table.getData(), 'aliexpress');
            }
            $('#ae-more-sold-count').text(moreSold.toLocaleString());
            $('#ae-zero-sold-count').text(zeroSold.toLocaleString());
            let blueTriangleCount = 0;
            (table ? table.getData() : rows).forEach(function(row) {
                if (aeHasBlueTriangle(row)) blueTriangleCount++;
            });
            $('#aliexpress-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncAeTriangleBadgeState === 'function') syncAeTriangleBadgeState();
            if ($('#ae-avg-roi-badge').length) {
                $('#ae-avg-roi-badge').text(`GROI: ${Math.round(avgRoi)}%`);
            }
            $('#ae-total-views-badge').text('Views: ' + totalViews.toLocaleString());
            const cvrPct = totalViews > 0 ? (totalOutputOrder / totalViews) * 100 : 0;
            $('#ae-avg-cvr-badge').text('CVR: ' + Math.round(cvrPct) + '%');
            aeSyncFilterBadgeActiveClasses();
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
                    const rows = Array.isArray(response) ? response : [];
                    rows.forEach(function(r) {
                        if (!r || typeof r !== 'object') return;
                        const sku = String(r.sku || '').trim().toUpperCase();
                        if (r.is_parent === true || r.is_parent === 1 || r.is_parent === '1' || /^PARENT\b/.test(sku)) {
                            r.is_parent = true;
                        }
                    });
                    summaryDataCache = normalizeRows(rows);
                    updateSummary(summaryDataCache);
                    setTimeout(aeApplyBadgeFilterFromUrl, 0);
                    return rows;
                },
                layout: "fitData",
                layoutColumnsOnNewData: true,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: "rows",
                columnCalcs: "both",
                langs: {
                    "default": {
                        "pagination": {
                            "page_size": "SKU Count"
                        }
                    }
                },
                initialSort: [],
                columnDefaults: {
                    hozAlign: "center",
                    headerHozAlign: "center",
                    resizable: true,
                    minWidth: 64,
                    headerSort: true,
                },
                rowFormatter: function(row) {
                    if (typeof aeIsParentRow === 'function' ? aeIsParentRow(row.getData()) : row.getData().is_parent === true) {
                        row.getElement().classList.add('ae-parent-row');
                    }
                },
                columns: [
                    // ── Select checkbox (always visible) ──────────────────
                    {
                        title: "<input type='checkbox' id='ae-select-all'>",
                        field: "_ae_select",
                        hozAlign: "center",
                        headerSort: false,
                        width: 38,
                        visible: true,
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
                        headerSort: true,
                        cssClass: "text-muted",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#0d6efd;font-size:11px;font-weight:600;">${v}</span>`;
                        }
                    },
                    (function() {
                        const pe = ParentExpand.columnDef();
                        if (pe && pe.headerSort === undefined) pe.headerSort = true;
                        return pe;
                    })(),
                    {
                        title: "Image",
                        field: "image",
                        width: 60,
                        headerSort: true,
                        sorter: function(a, b) {
                            const av = a ? 1 : 0;
                            const bv = b ? 1 : 0;
                            return av - bv;
                        },
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
                        headerSort: true,
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
                        headerSort: true,
                        sorter: function(a, b, aRow, bRow) {
                            const score = function(d) {
                                return (d && d.seller_link ? 2 : 0) + (d && d.buyer_link ? 1 : 0);
                            };
                            return score(aRow.getData()) - score(bRow.getData());
                        },
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
                        headerSort: true,
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
                        title: "Views",
                        field: "views",
                        sorter: "number",
                        hozAlign: "center",
                        width: 65,
                        headerTooltip: "AliExpress L30 page views (queryproductviewedinfoeverydaybyid / viewedCount)",
                        formatter: function(cell) {
                            return `<span style="font-weight:700;">${parseInt(cell.getValue(), 10) || 0}</span>`;
                        }
                    },
                    {
                        title: "CVR",
                        field: "cvr",
                        sorter: "number",
                        hozAlign: "center",
                        width: 60,
                        headerTooltip: "AliExpress L30 CVR = AE orders ÷ AE views (from AliExpress API)",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            let cvr = parseFloat(cell.getValue());
                            if (!Number.isFinite(cvr) || cvr <= 0) {
                                const views = parseFloat(d.views) || 0;
                                const orders = parseFloat(d.output_order) || 0;
                                cvr = views > 0 ? (orders / views) * 100 : 0;
                            }
                            let color = '#a00211';
                            if (cvr > 4 && cvr <= 7) color = '#ffc107';
                            else if (cvr > 7 && cvr <= 13) color = '#28a745';
                            else if (cvr > 13) color = '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(cvr)}%</span>`;
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
                        visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseInt(cell.getValue(), 10) || 0;
                            return `<span style="font-weight:700;">${v}</span>`;
                        }
                    },
                    {
                        title: "Std Prc",
                        field: "STANDARD_PRICE",
                        hozAlign: "center",
                        headerTooltip: "Standard Price (Std Prc) — same shared value as /amazon-tabulator-view. Editable; saves to all Sku Link LMP siblings. Dot vs channel price.",
                        editor: "input",
                        width: 70,
                        sorter: "number",
                        editable: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return false;
                            const sku = String(d.sku || d['(Child) sku'] || d.SKU || '');
                            return !!sku;
                        },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const value = cell.getValue();
                            const std = parseFloat(value) || 0;
                            if (!value || std <= 0) return '';
                            const comparePrice = parseFloat(d.price || 0) || 0;
                            const dot = aeStdPrcChangeDotHtml(std, comparePrice);

                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' + dot + ('$' + std.toFixed(2)) + '</span>';
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
                            const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(cell.getValue(), d.lmp_price || d.lmp || d.LMP) : '');
                            const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(cell.getValue(), d.lmp_price || d.lmp || d.LMP) : '');
                            return money(cell.getValue()) + lmpTri + purpleTri;
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
                            if (window.ParentExpand) {
                                const avgHtml = ParentExpand.parentAvgLmpHtml(row, {
                                    dataset: typeof allTableData !== 'undefined' ? allTableData : undefined,
                                    field: 'lmp',
                                    getValue: function(r) {
                                        const entries = r.lmp_entries || [];
                                        const prices = entries.map(function(e) {
                                            const p = e.price;
                                            return (p !== null && p !== undefined && p !== '' && !isNaN(parseFloat(p))) ? parseFloat(p) : null;
                                        }).filter(function(p) { return p !== null && p > 0; });
                                        if (prices.length) return Math.min.apply(null, prices);
                                        const v = parseFloat(r.lmp);
                                        return isFinite(v) && v > 0 ? v : null;
                                    }
                                });
                                if (avgHtml !== null) return avgHtml;
                            }
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
                        visible: false,
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
                        visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                    {
                        title: "Sprice",
                        field: "sprice",
                        sorter: "number",
                        hozAlign: "right",
                        editor: "number",
                        editorParams: { min: 0, step: 0.01 },
                        headerTooltip: "S PRC = Std × (1 − (PRMT% + cvr%)/100). Blue triangle = S PRC ≠ Price. Red text = S PRC > LMP.",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            let value = parseFloat(cell.getValue()) || 0;
                            if (typeof chPromoSpriceFromStdTPromo === 'function') {
                                const calc = chPromoSpriceFromStdTPromo(d);
                                if (calc > 0) value = calc;
                            }
                            if (!(value > 0)) return '';
                            const live = parseFloat(d.price) || 0;
                            const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                            const formatted = money(value);
                            const overLmp = lmp > 0 && value > lmp;
                            const priceHtml = overLmp
                                ? `<span style="color:#dc3545;font-weight:600;">${formatted}</span>`
                                : `<span style="font-weight:600;">${formatted}</span>`;
                            const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            return `<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">${priceHtml}${blueTri}</span>`;
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
                ],
                dataLoaded: function(data) {
                    allTableData = Array.isArray(data) ? data : [];
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    updateSummary(data);
                    // Default SKUs mode — hide PARENT* rows (same as Amazon)
                    if (!$('#ae-row-type-filter').val()) {
                        $('#ae-row-type-filter').val('skus');
                    }
                    setTimeout(function() {
                        if (typeof applyFilters === 'function') applyFilters();
                    }, 0);
                    if (typeof window.chPromoAutofitColumns === 'function') {
                        window.chPromoAutofitColumns(table);
                    }
                },
                tableBuilt: function() {
                    if (!$('#ae-row-type-filter').val()) {
                        $('#ae-row-type-filter').val('skus');
                    }
                    setTimeout(function() {
                        if (typeof applyFilters === 'function') applyFilters();
                    }, 50);
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

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                    onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
                });
                ParentExpand.bind();
            }

            $('#pricing-sku-search').on('input', function() { applyFilters(); });
            $('#ae-row-type-filter').on('change', function() { applyFilters(); });
            $('#ae-inv-filter').on('change',    function() { applyFilters(); });
            $('#ae-gpft-filter, #ae-cvr-filter').on('change',   function() { applyFilters(); });
            $('#ae-roi-filter').on('change',    function() { applyFilters(); });
            $('#ae-al30-filter').on('change',   function() { applyFilters(); });

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

            $('#ae-discount-type').on('change', function() { syncAeDiscountInputUi(); });
            $('#ae-apply-discount-btn').on('click', function() { applyAeDiscount(); });
            $('#ae-discount-input').on('keypress', function(e) { if (e.which === 13) applyAeDiscount(); });

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
                    aeNotify('Please check at least one SKU first', 'warning');
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
                const field = cell.getField();
                const row = cell.getRow();
                const d = row.getData();
                const value = cell.getValue();

                if (field === 'STANDARD_PRICE') {
                    if (d.is_parent) return;
                    const sku = d.sku || d['(Child) sku'] || d.SKU;
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
                            applyAeStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                            const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                            const msg = n > 1 ? ('Std Prc saved for ' + n + ' linked SKUs') : 'Std Prc saved';
                            if (window.toastr) toastr.success(msg); else if (typeof showToast === 'function') showToast(msg, 'success');
                        },
                        error: function() {
                            if (window.toastr) toastr.error('Failed to save Std Prc'); else if (typeof showToast === 'function') showToast('Failed to save Std Prc', 'error');
                        }
                    });
                    return;
                }

                if (field !== 'sprice') return;
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

            /*
             * ============================================================================
             * Column visibility (mirrors shein-pricing-view / ebay-tabulator-view)
             * Persists in the shared DB table `channel_tabulator_column_settings` via the
             * /tabulator-column-visibility endpoint, channel = 'aliexpress_pricing'.
             * ============================================================================
             */
            const AE_COLUMN_VIS_URL = '/tabulator-column-visibility';
            const AE_COLUMN_VIS_CHANNEL = 'aliexpress_pricing';

            function aeBuildColumnDropdown() {
                if (window.AnalyticsColVis) {
                    window.AnalyticsColVis.install({
                        getTable: function() { return table; },
                        menuId: 'ae-column-dropdown-menu',
                        storageKey: 'aliexpress_col_cats_v1',
                        skipFields: ['_ae_select', '_select'],
                        alwaysHidden: ['lp', 'ship'],
                        onSave: function() {
                            if (typeof aeSaveColumnVisibilityToServer === 'function') aeSaveColumnVisibilityToServer();
                        }
                    });
                    window.AnalyticsColVis.rebuild(null, 'ae-column-dropdown-menu');
                    return;
                }
                const menu = document.getElementById('ae-column-dropdown-menu');
                if (!menu) return;
                menu.innerHTML = '';

                fetch(AE_COLUMN_VIS_URL + '?channel=' + encodeURIComponent(AE_COLUMN_VIS_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        const map = (savedVisibility && typeof savedVisibility === 'object') ? savedVisibility : {};
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field || def.field === '_ae_select' || def.field === 'lp' || def.field === 'ship') return;
                            const title = (def.title || '').replace(/<[^>]*>/g, '').trim() || def.field;

                            const li = document.createElement('li');
                            const label = document.createElement('label');
                            label.style.display = 'block';
                            label.style.padding = '5px 10px';
                            label.style.cursor = 'pointer';

                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.value = def.field;
                            checkbox.checked = map.hasOwnProperty(def.field) ? (map[def.field] !== false) : col.isVisible();
                            checkbox.style.marginRight = '8px';
                            checkbox.className = 'ae-column-toggle';

                            label.appendChild(checkbox);
                            label.appendChild(document.createTextNode(title));
                            li.appendChild(label);
                            menu.appendChild(li);
                        });
                    })
                    .catch(err => console.error('Error loading AliExpress column visibility:', err));
            }

            function aeSaveColumnVisibilityToServer() {
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field && def.field !== '_ae_select') {
                        visibility[def.field] = col.isVisible();
                    }
                });

                fetch(AE_COLUMN_VIS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        channel: AE_COLUMN_VIS_CHANNEL,
                        visibility: visibility
                    })
                }).catch(err => console.error('Error saving AliExpress column visibility:', err));
            }

            function aeApplyColumnVisibilityFromServer() {
                fetch(AE_COLUMN_VIS_URL + '?channel=' + encodeURIComponent(AE_COLUMN_VIS_CHANNEL), {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        if (savedVisibility && typeof savedVisibility === 'object') {
                            Object.keys(savedVisibility).forEach(field => {
                                if (field === '_ae_select' || field === 'lp' || field === 'ship') return;
                                const col = table.getColumn(field);
                                if (col) {
                                    if (savedVisibility[field]) {
                                        col.show();
                                    } else {
                                        col.hide();
                                    }
                                }
                            });
                        }
                        ['lp', 'ship'].forEach(function(field) {
                            const col = table.getColumn(field);
                            if (col) col.hide();
                        });
                    })
                    .catch(err => console.error('Error applying AliExpress column visibility:', err));
            }

            // Toggle column from dropdown
            document.getElementById('ae-column-dropdown-menu').addEventListener('change', function(e) {
                if (e.target.classList && e.target.classList.contains('ae-column-toggle')) {
                    const field = e.target.value;
                    const col = table.getColumn(field);
                    if (col) {
                        if (e.target.checked) col.show();
                        else col.hide();
                        aeSaveColumnVisibilityToServer();
                    }
                }
            });

            // Build dropdown and apply server visibility once the table is built
            table.on('tableBuilt', function() {
                aeApplyColumnVisibilityFromServer();
                aeBuildColumnDropdown();
                syncPriceModeUi();
                updateSelectedCount();
            });

            // Click filter badges → table filter only (never chart)
            $(document).on('click', '.ae-filter-badge', function(e) {
                e.preventDefault();
                e.stopPropagation();
                aeClearBadgeHoverTimer();
                aeHideBadgeChartModal();

                const filterKey = String($(this).data('filter') || '').toLowerCase();
                aeZeroSoldActive = aeMoreSoldActive = false;

                if (filterKey === 'zero_sold') {
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

            const AE_PUSH_CHUNK_SIZE = 40;

            function aePushUpdatesInChunks(updates, $btn) {
                if (!updates || updates.length === 0) {
                    aeNotify('Nothing to push', 'error');
                    return;
                }

                const origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing 0/' + updates.length + '…');

                const chunks = [];
                for (let i = 0; i < updates.length; i += AE_PUSH_CHUNK_SIZE) {
                    chunks.push(updates.slice(i, i + AE_PUSH_CHUNK_SIZE));
                }

                let totalPushed = 0;
                let totalFailed = 0;
                const allFails = [];

                function next(idx) {
                    if (idx >= chunks.length) {
                        $btn.prop('disabled', false).html(origHtml);
                        const msgType = totalFailed > 0 ? (totalPushed > 0 ? 'warning' : 'error') : 'success';
                        aeNotify(`AliExpress push: ${totalPushed} ok, ${totalFailed} failed`, msgType);
                        if (allFails.length) {
                            console.warn('AliExpress push failures:', allFails);
                            const sample = allFails.slice(0, 3).map(f => `• ${f.sku}: ${f.error || 'failed'}`).join('\n');
                            const more = allFails.length > 3 ? `\n…and ${allFails.length - 3} more (see console)` : '';
                            aeNotify(`Failed:\n${sample}${more}`, 'error');
                        }
                        return;
                    }

                    $btn.html('<i class="fas fa-spinner fa-spin"></i> Pushing ' + Math.min((idx + 1) * AE_PUSH_CHUNK_SIZE, updates.length) + '/' + updates.length + '…');

                    $.ajax({
                        url: '{{ route("aliexpress.pricing.push") }}',
                        type: 'POST',
                        timeout: 0,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        data: {
                            _token: '{{ csrf_token() }}',
                            updates: chunks[idx]
                        },
                        success: function(res) {
                            totalPushed += (res.pushed || 0);
                            totalFailed += (res.failed || 0);
                            (res.results || []).filter(r => r.success).forEach(r => {
                                const rows = table.searchRows('sku', '=', r.sku);
                                if (rows.length) {
                                    rows[0].update({ price: r.price });
                                }
                            });
                            (res.results || []).filter(r => !r.success).forEach(r => allFails.push(r));
                        },
                        error: function(xhr) {
                            const r = xhr.responseJSON || {};
                            const err = r.message || r.error || ('HTTP ' + xhr.status);
                            chunks[idx].forEach(u => allFails.push({ sku: u.sku, error: err }));
                            totalFailed += chunks[idx].length;
                        },
                        complete: function() {
                            next(idx + 1);
                        }
                    });
                }

                next(0);
            }

            function aePushSelectedPrices() {
                if (!selectedSkus || selectedSkus.size === 0) {
                    aeNotify('Select SKUs first', 'warning');
                    return;
                }

                const updates = [];
                const skipped = [];
                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (!rows.length) return;
                    const d = rows[0].getData();
                    if (d.is_parent) return;
                    const price = parseFloat(d.sprice) > 0 ? parseFloat(d.sprice)
                        : (parseFloat(d.price) > 0 ? parseFloat(d.price) : 0);
                    if (!(price > 0)) {
                        skipped.push(sku);
                        return;
                    }
                    updates.push({ sku: sku, price: +price.toFixed(2) });
                });

                if (updates.length === 0) {
                    aeNotify('No selected SKU has a positive SPRICE or Price to push', 'error');
                    return;
                }

                const summary = 'Push ' + updates.length + ' price' + (updates.length !== 1 ? 's' : '') + ' live to AliExpress?'
                    + (skipped.length ? '\n(' + skipped.length + ' skipped — no SPRICE/Price)' : '');
                if (!confirm(summary)) return;
                aePushUpdatesInChunks(updates, $('#ae-push-price-btn'));
            }

            $('#ae-push-price-btn').on('click', aePushSelectedPrices);

            function aeSyncPricingApi(mode, $btn) {
                const originalHtml = $btn.html();
                const $status = $('#ae-sync-api-status');
                const label = mode === 'orders' ? 'orders' : (mode === 'views' ? 'views' : 'price');
                $('#ae-sync-price-btn, #ae-sync-orders-btn, #ae-sync-views-btn').prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Syncing…');
                $status.removeClass('text-success text-danger').addClass('text-muted').text('Pulling ' + label + ' from AliExpress API…');

                $.ajax({
                    url: '{{ route("aliexpress.pricing.sync.api") }}',
                    type: 'POST',
                    timeout: 0,
                    data: {
                        _token: '{{ csrf_token() }}',
                        mode: mode,
                        days: 60
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        const msg = (response && response.message) ? response.message : ('AliExpress ' + label + ' synced.');
                        if (response && response.success === false) {
                            $status.removeClass('text-muted text-success').addClass('text-danger').text(msg);
                            if (window.toastr) toastr.error(msg); else alert(msg);
                            return;
                        }
                        $status.removeClass('text-muted text-danger').addClass('text-success').text(msg);
                        if (window.toastr) toastr.success(msg); else alert(msg);
                        table.setData('/aliexpress/pricing-data');
                    },
                    error: function(xhr) {
                        let message = 'AliExpress ' + label + ' sync failed.';
                        const j = xhr.responseJSON;
                        if (j && j.message) message = j.message;
                        else if (xhr.status === 419) message = 'Session expired. Refresh the page and try again.';
                        else if (xhr.status === 0) message = 'Request timed out or network error. Try again on the server.';
                        $status.removeClass('text-muted text-success').addClass('text-danger').text(message);
                        if (window.toastr) toastr.error(message); else alert(message);
                    },
                    complete: function() {
                        $('#ae-sync-price-btn, #ae-sync-orders-btn, #ae-sync-views-btn').prop('disabled', false);
                        $btn.html(originalHtml);
                    }
                });
            }

            $('#ae-sync-price-btn').on('click', function() {
                if (!confirm('Sync list price + AE stock from AliExpress API?\n\nThis may take several minutes.')) return;
                aeSyncPricingApi('price', $(this));
            });

            $('#ae-sync-orders-btn').on('click', function() {
                if (!confirm('Sync orders (AL30 / sales) from AliExpress API for the last 60 days?\n\nThis may take several minutes.')) return;
                aeSyncPricingApi('orders', $(this));
            });

            $('#ae-sync-views-btn').on('click', function() {
                if (!confirm('Sync L30 page views + CVR from AliExpress API?\n\nThis may take several minutes.')) return;
                aeSyncPricingApi('views', $(this));
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

            // ── Badge Trend Chart (mirrors TikTok ttBadgeChart) ──────
            let aeBadgeLineChart = null;
            let aeBadgeBarChart  = null;
            let aeBadgeMetric    = '';
            let aeBadgeDays      = 30;
            let aeBadgeAjax      = null;

            const aeDollarMetrics  = ['total_pft','total_sales','total_cogs'];
            const aeCountMetrics   = ['total_sku','total_al30','zero_sold','more_sold'];
            const aePercentMetrics = ['avg_gpft','avg_roi'];

            const aeBadgeLabels = {
                total_pft: 'Profit',   total_sales: 'Sales',   total_al30: 'AL30',
                avg_gpft: 'GPFT%',            avg_roi: 'GROI%',
                total_cogs: 'COGS',           total_sku: 'Total SKU',       zero_sold: '0 Sold',          more_sold: 'Sold %',
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

                // Point colors: green=UP red=DOWN vs previous day
                const dotColors = values.map((v, i) => {
                    if (i === 0) return '#6c757d';
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : '#6c757d';
                });
                const labelColors = values.map(v => v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d');

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
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
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
                                color: ctx => labelColors[ctx.dataIndex],
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
