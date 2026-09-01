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
        .tabulator .tabulator-tableholder { scrollbar-width: thin; scrollbar-color: #c1c1c1 transparent; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar { width: 8px; height: 8px; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-track { background: transparent; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

        /* Parent row – identical to shein-pricing / amazon_tabulator_view */
        .tabulator-row.ae-parent-row,
        .tabulator-row.ae-parent-row .tabulator-cell {
            background-color: #fffef2 !important;
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

        .tabulator-paginator label {
            margin-right: 5px;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #aliexpress-pricing-table .tabulator-calcs-top,
        #aliexpress-pricing-table .tabulator-calcs-holder {
            display: none !important;
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
        #aliexpress-push-cross-badge.active-filter {
            outline: 3px solid #ffc107;
            outline-offset: 2px;
        }
        #ae-stop-low-sgroi-btn.is-on {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }
        #ae-stop-low-sgroi-btn .ae-low-sgroi-count {
            background: #fff;
            color: #dc3545;
            font-weight: 700;
        }
        #ae-stop-low-sgroi-btn.is-on .ae-low-sgroi-count {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        #ae-stop-low-sgroi-btn #ae-min-sgroi-input {
            width: 44px;
            height: 22px;
            padding: 0 2px;
            margin: 0 2px;
            border: 1px solid rgba(0,0,0,0.15);
            border-radius: 4px;
            background: #fff;
            color: #212529;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            line-height: 20px;
        }
        #ae-stop-low-sgroi-btn.is-on #ae-min-sgroi-input {
            border-color: rgba(255,255,255,0.55);
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
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'css', 'lmpIgnoreModal' => '#aeLmpModal'])
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
                                title="Click for daily trend">Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-gpft-badge"
                                data-metric="avg_gpft" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click for daily trend">GPFT: 0%</span>
                            <span class="badge bg-success fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-profit-badge"
                                data-metric="total_pft" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click for daily trend">PFT: $0</span>
                            <span class="badge fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-roi-badge"
                                data-metric="avg_roi"
                                style="background-color:#6f42c1;color:#fff;font-weight:700;cursor:pointer;"
                                title="Click for daily trend">GROI: 0%</span>

                            <span class="badge bg-info fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-views-badge"
                                data-metric="total_views" style="color:#111;font-weight:700;cursor:pointer;"
                                title="Σ AliExpress L30 page views (API viewedCount)">Views: 0</span>
                            <span class="badge bg-success fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-cvr-badge"
                                data-metric="cvr" style="color:#111;font-weight:700;cursor:pointer;"
                                title="CVR = Σ outputOrder ÷ Σ L30 page views × 100 (API)">CVR: 0%</span>

                            <span class="badge bg-success fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-sold-pct-badge"
                                data-metric="more_sold" data-filter="more_sold"
                                style="color:#111;font-weight:700;cursor:pointer;"
                                title="Click to filter AL30 &gt; 0 (INV &gt; 0). Click again to clear.">
                                Sold &gt;0: <span id="ae-more-sold-count">0</span>
                            </span>
                            <span class="badge bg-danger fs-6 p-2 ae-hover-chart ae-filter-badge" id="ae-zero-sold-badge"
                                data-metric="zero_sold" data-filter="zero_sold"
                                style="color:#fff;font-weight:700;cursor:pointer;"
                                title="Click to filter AL30 = 0 (INV &gt; 0). Click again to clear.">
                                0 Sold: <span id="ae-zero-sold-count">0</span>
                            </span>

                            @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'aliexpress-lmp-missing-badge', 'lmpChannelKey' => 'aliexpress'])
                            @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'aliexpress-price-gt-lmp-badge', 'pglChannelKey' => 'aliexpress', 'pglPriceField' => 'price'])
                            @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'aliexpress-price-lt80-lmp-badge', 'pltChannelKey' => 'aliexpress', 'pltPriceField' => 'price'])
                            <span class="badge fs-6 p-2" id="aliexpress-blue-triangle-badge"
                                style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                                title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                                <i class="fas fa-exclamation-triangle"></i> 0</span>
                            <span class="badge fs-6 p-2" id="aliexpress-push-cross-badge"
                                style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
                                title="Cross rows: Sprice is not live and SGROI is at or above Stop %. INV 0 and SGROI below Stop are excluded. Click to filter.">
                                <i class="fa-solid fa-xmark"></i> 0</span>
                        </div>
                    </div>

                    {{-- ── Row 2: Filter bar ── --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

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

                        <select id="ae-sold-filter" class="form-select form-select-sm" style="width:120px;"
                            title="Filter by AliExpress sold (AL30). 0 sold / &gt; 0 sold require INV &gt; 0.">
                            <option value="all">All</option>
                            <option value="zero">0 sold</option>
                            <option value="more">&gt; 0 sold</option>
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
                                    <span class="ae-sc red"></span>Red (&lt;25%)</a></li>
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
                        <button type="button" id="ae-stop-low-sgroi-btn"
                            class="btn btn-sm btn-outline-danger {{ !empty($aeStopLowSgroi) ? 'is-on' : '' }}"
                            data-on="{{ !empty($aeStopLowSgroi) ? '1' : '0' }}"
                            title="Change the %. Count = current filters with SGROI below that. Ali button, row push, and the 17:15 IST cron all skip those SKUs.">
                            <i class="fas fa-ban"></i> Stop &lt;
                            <input type="number" id="ae-min-sgroi-input" min="1" max="300" step="1"
                                value="{{ (int) ($aeMinSgroi ?? 30) }}"
                                title="SGROI% cutoff — do not push price when SGROI is below this %">
                            %
                            <span class="badge rounded-pill ae-low-sgroi-count" id="ae-low-sgroi-count">0</span>
                        </button>
                        <button type="button" id="export-pricing-btn" class="btn btn-sm btn-success" title="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'aliexpress'])
                        <a href="{{ route('aliexpress.lmp') }}" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-table"></i> LMP sheet
                        </a>

                        {{-- Target ROI% / GPFT% — Amazon-style 🎯 + icon-only Apply --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                            id="ae-target-roi-controls"
                            title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / margin on every checked row (back-solves so SROI column equals the target)">
                            <label for="ae-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> SGROI:
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
                        <div id="aliexpress-pricing-table" data-ae-hide-parents="1" style="flex: 1;"></div>
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
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <!-- Line chart + stat panel -->
                    <div id="aeBadgeLineWrap" style="display:none;height:38vh;flex-direction:row;align-items:stretch;">
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
                                    <th class="text-center" style="width: 70px;" title="Ignore for L1">Ignore</th>
                                    <th style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="aeLmpEntriesContainer"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <span id="aeLmpAutosaveStatus" class="me-auto small text-muted">Changes save automatically</span>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    <script>
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'aliexpress'])
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'script'])
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
        let aePushCrossFilterActive = false;
        let aeStopLowSgroi = {{ !empty($aeStopLowSgroi) ? 'true' : 'false' }};
        let AE_MIN_SGROI = {{ (int) ($aeMinSgroi ?? 30) }};
        let aeBadgeHoverTimer = null;

        function aeReadMinSgroi() {
            const n = parseInt($('#ae-min-sgroi-input').val(), 10);
            if (!isFinite(n) || n < 1) return 30;
            return Math.min(300, n);
        }
        function aePushSgroiForPrice(data, price) {
            return aeSpriceMetrics(data, price).sroi;
        }
        function aePushBlockedBySgroi(data, price) {
            return aePushSgroiForPrice(data, price) < AE_MIN_SGROI;
        }
        function aeSyncStopSgroiUi() {
            const n = AE_MIN_SGROI;
            const $input = $('#ae-min-sgroi-input');
            if ($input.length && String($input.val()) !== String(n)) $input.val(n);
            $('#ae-stop-low-sgroi-btn').attr('title',
                'Do not push price when SGROI < ' + n
                + '%. Count = current filters below that. Applies to Ali button, row X, and 17:15 IST cron.');
        }
        function aeSaveStopSgroiGuard(opts) {
            opts = opts || {};
            return $.ajax({
                url: '{{ route("aliexpress.pricing.push.guard") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    stop_sgroi_lt_40: aeStopLowSgroi ? 1 : 0,
                    min_sgroi: AE_MIN_SGROI
                }
            }).done(function(res) {
                if (res && res.min_sgroi != null) AE_MIN_SGROI = parseInt(res.min_sgroi, 10) || AE_MIN_SGROI;
                aeSyncStopSgroiUi();
                if (typeof applyFilters === 'function') applyFilters();
                if (typeof updateSummary === 'function') updateSummary();
                if (opts.notify) {
                    aeNotify('Push blocked when SGROI < ' + AE_MIN_SGROI + '%', 'success');
                }
            });
        }

        function aeEntryIsIgnored(e) {
            if (window.LmpIgnore && typeof LmpIgnore.isIgnored === 'function') {
                return LmpIgnore.isIgnored(e);
            }
            if (!e) return false;
            const v = e.ignored;
            if (v === true || v === 1 || v === '1') return true;
            if (typeof v === 'string') {
                return ['true', 'yes', 'on'].indexOf(v.toLowerCase().trim()) !== -1;
            }
            return false;
        }
        function aeLmpLowestFromEntries(entries) {
            let min = null;
            let link = null;
            let ignoredMin = null;
            (entries || []).forEach(function(e) {
                const p = parseFloat(e && e.price);
                if (!(p > 0)) return;
                if (aeEntryIsIgnored(e)) {
                    if (ignoredMin === null || p < ignoredMin) ignoredMin = p;
                    return;
                }
                if (min === null || p < min) {
                    min = p;
                    link = e.link || link;
                }
            });
            return { lmp: min, lmp_link: link, ignored_price: ignoredMin };
        }
        function aeEffectiveLmp(row) {
            if (!row) return 0;
            const entries = Array.isArray(row.lmp_entries) ? row.lmp_entries : [];
            if (entries.length) {
                const extra = aeLmpLowestFromEntries(entries);
                return extra.lmp > 0 ? extra.lmp : 0;
            }
            return parseFloat(row.lmp_price || row.lmp || row.LMP) || 0;
        }

        function aeRuleSpriceRaw(data) {
            if (!data || data.is_parent) return 0;
            let sprice = parseFloat(data.sprice || data.SPRICE) || 0;
            if (typeof chPromoLiveSprice === 'function') {
                const calc = chPromoLiveSprice(data);
                if (calc > 0) sprice = calc;
            }
            if (!(sprice > 0)) sprice = parseFloat(data.price) || 0;
            return sprice > 0 ? Math.round(sprice * 100) / 100 : 0;
        }
        function aeShouldCapSpriceToLmp() {
            return true;
        }
        /** Visible S PRC: 0-sold Target GROI% / marketplace_percentages, then LMP-capped. */
        function aeVisibleSprice(data) {
            const raw = aeRuleSpriceRaw(data);
            if (!(raw > 0)) return 0;
            if (window.SpriceLmpCap) {
                const cap = SpriceLmpCap.apply(data, raw, aeEffectiveLmp);
                if (cap && cap.shown > 0) return Math.round(cap.shown * 100) / 100;
            }
            const lmp = aeEffectiveLmp(data);
            if (lmp > 0 && raw + 0.0001 >= lmp) return Math.round(lmp * 100) / 100;
            return raw;
        }
        function aeSpriceMetrics(data, spriceOpt) {
            const sprice = spriceOpt != null ? Number(spriceOpt) : aeVisibleSprice(data);
            if (!(sprice > 0)) return { sgpft: 0, sroi: 0 };
            const zeroSold = typeof chPromoIsZeroSoldRow === 'function' && chPromoIsZeroSoldRow(data);
            const margin = zeroSold && typeof chPromoZeroSoldTakehomeMargin === 'function'
                ? chPromoZeroSoldTakehomeMargin(data)
                : ((typeof chPromoTakehomeMargin === 'function')
                    ? chPromoTakehomeMargin(data)
                    : (parseFloat(data && data._margin) || 1));
            const lp = (typeof chPromoLp === 'function')
                ? chPromoLp(data)
                : (parseFloat(data && data.lp) || 0);
            const ship = (typeof chPromoShipCost === 'function')
                ? chPromoShipCost(data)
                : (parseFloat(data && data.ship) || 0);
            const sgpft = Math.round(((sprice * margin - ship - lp) / sprice) * 100);
            const sroi = lp > 0 ? Math.round(((sprice * margin - lp - ship) / lp) * 100) : 0;
            return { sgpft: sgpft, sroi: sroi };
        }
        function aePrepareSpriceToSave(data, sprice) {
            let shown = Number(sprice) || 0;
            if (!(shown > 0)) shown = aeVisibleSprice(data) || parseFloat(data && data.price) || 0;
            if (shown > 0 && window.SpriceLmpCap && aeShouldCapSpriceToLmp(data)) {
                const cap = SpriceLmpCap.apply(data, shown, aeEffectiveLmp);
                if (cap && cap.shown > 0) shown = Math.round(cap.shown * 100) / 100;
            } else if (shown > 0) {
                shown = Math.round(shown * 100) / 100;
            }
            const m = aeSpriceMetrics(data, shown);
            return { sprice: shown, sgpft: m.sgpft, sroi: m.sroi };
        }
        function aeRowSpriceForAlert(data) {
            return aeVisibleSprice(data);
        }
        function aePersistVisibleSprices() {
            if (typeof chPromoOverwriteStoredSpriceFromRules === 'function') {
                chPromoOverwriteStoredSpriceFromRules({ silent: true, skip_push: true });
            }
            if (!table || typeof table.getRows !== 'function') return;
            const updates = [];
            table.getRows().forEach(function(row) {
                const d = row.getData() || {};
                if (d.is_parent || !d.sku) return;
                const shown = aeVisibleSprice(d);
                if (!(shown > 0)) return;
                const stored = Math.round((parseFloat(d.sprice || d.SPRICE) || 0) * 100) / 100;
                if (stored === shown) return;
                const m = aeSpriceMetrics(d, shown);
                if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
                row.update({ sprice: shown, sgpft: m.sgpft, sroi: m.sroi, SPRICE_STATUS: null, SPRICE_PUSHED_VALUE: null });
                updates.push({ sku: d.sku, sprice: shown });
            });
            if (updates.length) saveSpriceUpdates(updates, { clearFirst: false });
        }
        function aeStoredSprice(data) {
            return Math.round((parseFloat(data && (data.sprice || data.SPRICE)) || 0) * 100) / 100;
        }
        function aeSpricePushed(data) {
            if (!data || data.is_parent) return false;
            const stored = aeStoredSprice(data);
            const live = parseFloat(data.price) || 0;
            if (stored > 0 && live > 0 && Math.round(stored * 100) === Math.round(live * 100)) return true;
            const visible = aeVisibleSprice(data);
            if (visible > 0 && live > 0 && Math.round(visible * 100) === Math.round(live * 100)) return true;
            const st = String(data.SPRICE_STATUS || '').toLowerCase();
            const pv = parseFloat(data.SPRICE_PUSHED_VALUE);
            if ((st === 'pushed' || st === 'applied') && pv > 0 && stored > 0
                && Math.round(pv * 100) === Math.round(stored * 100)) {
                return true;
            }
            return false;
        }
        function aePushablePrice(data) {
            return aeStoredSprice(data) || aeVisibleSprice(data) || 0;
        }
        function aeHasPushCross(data) {
            if (!data || data.is_parent) return false;
            if (aeSpricePushed(data)) return false;
            const price = aePushablePrice(data);
            if (!(price > 0)) return false;
            if (aePushBlockedBySgroi(data, price)) return false;
            return true;
        }
        function aeRowSgroi(data) {
            return aeSpriceMetrics(data).sroi;
        }
        function aeHasBlueTriangle(data) {
            if (!data || data.is_parent) return false;
            const sprice = aeRowSpriceForAlert(data);
            const price = parseFloat(data.price) || 0;
            if (!(sprice > 0) || !(price > 0) || Math.round(sprice * 100) === Math.round(price * 100)) return false;
            const lmp = aeEffectiveLmp(data);
            if (lmp > 0 && sprice + 0.0001 >= lmp) return false;
            return true;
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
            const $sold = $('#ae-sold-filter');
            if ($sold.length) {
                if (aeZeroSoldActive) $sold.val('zero');
                else if (aeMoreSoldActive) $sold.val('more');
                else $sold.val('all');
            }
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

        function saveSpriceUpdates(updates, opts) {
            opts = opts || {};
            updates = (updates || []).map(function(u) {
                const next = Object.assign({}, u);
                if (!(Number(next.sprice) > 0) || !next.sku || !table) return next;
                let row = null;
                try {
                    const rows = table.searchRows('sku', '=', next.sku);
                    if (rows && rows.length) row = rows[0];
                } catch (e) { /* ignore */ }
                const d = row && row.getData ? (row.getData() || {}) : {};
                const prepared = aePrepareSpriceToSave(d, next.sprice);
                next.sprice = prepared.sprice;
                if (row && prepared.sprice > 0) {
                    if (typeof chPromoWipeSpriceRow === 'function') chPromoWipeSpriceRow(row);
                    row.update({
                        sprice: prepared.sprice,
                        sgpft: prepared.sgpft,
                        sroi: prepared.sroi,
                        SPRICE_STATUS: null,
                        SPRICE_PUSHED_VALUE: null
                    });
                }
                return next;
            }).filter(function(u) {
                return u && u.sku && Number(u.sprice) > 0;
            });
            if (!updates.length) return;
            if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst === true) {
                chPromoBatchClearThenSave(updates, function(next) {
                    saveSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
                }, {
                    wipeFn: function(zeros) {
                        return $.ajax({
                            url: '{{ route("aliexpress.pricing.save.sprice") }}',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                            data: { updates: zeros }
                        });
                    }
                });
                return;
            }
            const post = function() {
                return $.ajax({
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
            };
            if (typeof chPromoEnqueueSpriceSave === 'function') chPromoEnqueueSpriceSave(post);
            else post();
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
                const prepared = aePrepareSpriceToSave(rowData, newSprice);
                row.update({ sprice: prepared.sprice, sgpft: prepared.sgpft, sroi: prepared.sroi });
                updates.push({ sku: sku, sprice: prepared.sprice });
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

        function aeDbgLog(hypothesisId, location, message, data) {
            // #region agent log
            fetch('http://127.0.0.1:7459/ingest/02b94a65-ae60-4d7e-bef0-17c519b7f744',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'1b30dc'},body:JSON.stringify({sessionId:'1b30dc',runId:'post-fix',hypothesisId:hypothesisId,location:location,message:message,data:data||{},timestamp:Date.now()})}).catch(()=>{});
            // #endregion
        }

        let aeFullTableData = [];
        let aeSwapData = false;
        function aeRowsForType(rowType, rows) {
            const src = Array.isArray(rows) ? rows : [];
            if (rowType === 'parents') return src.filter(function(r) { return aeIsParentRow(r); });
            if (rowType === 'all') return src;
            return src.filter(function(r) { return !aeIsParentRow(r); });
        }

        function aeIsParentRow(d) {
            if (!d) return false;
            if (typeof d.getData === 'function') {
                try { d = d.getData() || {}; } catch (e) { /* use original */ }
            }
            if (typeof window.isPmParentRowData === 'function' && window.isPmParentRowData(d)) return true;
            if (d.is_parent === true || d.is_parent === 1 || d.is_parent === '1' || d.is_parent === 'true') return true;
            if (d.is_parent_summary === true || d.is_parent_summary === 1 || d.is_parent_row === true) return true;
            const sku = String(d.sku || d.SKU || d['(Child) sku'] || '').trim().toUpperCase();
            return sku.includes('PARENT');
        }

        function aeRowMatchesFilters(d, opts) {
            opts = opts || {};
            if (!d) return false;
            if (typeof d.getData === 'function') {
                try { d = d.getData() || {}; } catch (e) { /* use original */ }
            }
            if (!d) return false;
            const isParent = aeIsParentRow(d);
            const skuSearch  = ($('#pricing-sku-search').val() || '').toLowerCase().trim();
            const rowType    = $('#ae-row-type-filter').val() || 'skus';
            const invFilter  = $('#ae-inv-filter').val() || 'all';
            const gpftFilter = $('#ae-gpft-filter').val() || 'all';
            const cvrFilter = $('#ae-cvr-filter').val() || 'all';
            const roiFilter  = $('#ae-roi-filter').val() || 'all';
            const sgroiFilter = opts.sgroiOverride != null ? opts.sgroiOverride : 'all';
            const al30Filter = $('#ae-al30-filter').val() || 'all';
            const soldFilter = $('#ae-sold-filter').val() || 'all';
            const dilColor   = $('.ae-dil-item.active').data('color') || 'all';
            const parentRowsBypass = (rowType === 'parents' || rowType === 'all');

            if (opts.skipPlay !== true && typeof isAePlayActive !== 'undefined' && isAePlayActive
                && typeof aePlayUniqueParents !== 'undefined' && aePlayUniqueParents.length > 0
                && typeof currentAePlayParentIndex !== 'undefined' && currentAePlayParentIndex >= 0) {
                const currentKey = aePlayUniqueParents[currentAePlayParentIndex];
                if (!currentKey) return true;
                const p = normalizeAeParentKey(d.parent);
                return p === currentKey || p === ('PARENT ' + currentKey);
            }

            if (rowType === 'parents' && !isParent) return false;
            if (rowType !== 'parents' && rowType !== 'all' && isParent) return false;
            if (skuSearch && !(String(d.sku || '').toLowerCase().includes(skuSearch))) return false;
            if (isParent && parentRowsBypass) return true;

            if (invFilter === 'zero' && (parseInt(d.inv, 10) || 0) !== 0) return false;
            if (invFilter === 'more' && (parseInt(d.inv, 10) || 0) <= 0) return false;

            if (gpftFilter !== 'all') {
                const gpft = parseFloat(d.gpft) || 0;
                if (gpftFilter === 'negative') { if (!(gpft < 0)) return false; }
                else if (gpftFilter === '50plus') { if (!(gpft >= 50)) return false; }
                else if (String(gpftFilter).includes('-')) {
                    const [min, max] = gpftFilter.split('-').map(Number);
                    if (!(gpft >= min && gpft < max)) return false;
                }
            }

            if (cvrFilter !== 'all') {
                const cvrRounded = Math.round((parseFloat(d.cvr) || 0) * 100) / 100;
                if (cvrFilter === '0-0' && cvrRounded !== 0) return false;
                if (cvrFilter === '0-2' && !(cvrRounded > 0 && cvrRounded <= 2)) return false;
                if (cvrFilter === '2-4' && !(cvrRounded > 2 && cvrRounded <= 4)) return false;
                if (cvrFilter === '4-7' && !(cvrRounded > 4 && cvrRounded <= 7)) return false;
                if (cvrFilter === '7-13' && !(cvrRounded > 7 && cvrRounded <= 13)) return false;
                if (cvrFilter === '13plus' && !(cvrRounded > 13)) return false;
            }

            if (roiFilter !== 'all') {
                const roi = parseFloat(d.groi) || 0;
                if (roiFilter === 'lt40' && !(roi < 40)) return false;
                if (roiFilter === '40-75' && !(roi >= 40 && roi < 75)) return false;
                if (roiFilter === '75-125' && !(roi >= 75 && roi < 125)) return false;
                if (roiFilter === 'gt125' && !(roi >= 125)) return false;
            }

            if (sgroiFilter !== 'all') {
                const sgroi = aeRowSgroi(d);
                if (sgroiFilter === 'lt40' && !(sgroi < AE_MIN_SGROI)) return false;
                if (sgroiFilter === '40-75' && !(sgroi >= AE_MIN_SGROI && sgroi < 75)) return false;
                if (sgroiFilter === '75-125' && !(sgroi >= 75 && sgroi < 125)) return false;
                if (sgroiFilter === 'gt125' && !(sgroi >= 125)) return false;
            }

            if (al30Filter !== 'all') {
                if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                const al30 = parseFloat(d.al30) || 0;
                if (al30Filter === '0' && al30 !== 0) return false;
                if (al30Filter === '0-10' && !(al30 > 0 && al30 <= 10)) return false;
                if (al30Filter === '10plus' && !(al30 > 10)) return false;
            }

            if (dilColor !== 'all') {
                const inv = parseFloat(d.inv) || 0;
                const ovL30 = parseFloat(d.ov_l30) || 0;
                const dil = inv === 0 ? 0 : (ovL30 / inv) * 100;
                if (dilColor === 'red' && !(dil < 25)) return false;
                if (dilColor === 'green' && !(dil >= 25 && dil < 50)) return false;
                if (dilColor === 'pink' && !(dil >= 50)) return false;
            }

            if (soldFilter === 'zero') {
                if (!((parseInt(d.inv, 10) || 0) > 0 && (parseFloat(d.al30) || 0) === 0)) return false;
            } else if (soldFilter === 'more') {
                if (!((parseInt(d.inv, 10) || 0) > 0 && (parseFloat(d.al30) || 0) > 0)) return false;
            }

            if (opts.skipBadges !== true) {
                if (lmpMissingFilterActive && window.LmpMissingBadge) {
                    if (LmpMissingBadge.isParentRow(d) || LmpMissingBadge.hasLmp(d)) return false;
                }
                if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                    if (!PriceGtLmpBadge.hasRedTriangle(d, 'price')) return false;
                }
                if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                    if (!PriceLt80LmpBadge.hasPurpleTriangle(d, 'price')) return false;
                }
                if (blueTriangleFilterActive && !aeHasBlueTriangle(d)) return false;
                if (aePushCrossFilterActive && !aeHasPushCross(d)) return false;
            }

            return true;
        }

        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                aeDbgLog('A', 'aliexpress_pricing_view.blade.php:applyFilters', 'early return ParentExpand expanded', {});
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            if (!table) {
                aeDbgLog('A', 'aliexpress_pricing_view.blade.php:applyFilters', 'early return table missing', {});
                return;
            }

            const rowType    = $('#ae-row-type-filter').val() || 'skus';
            const full = aeFullTableData.length ? aeFullTableData : allTableData;
            const desired = aeRowsForType(rowType, full);
            const current = table.getData() || [];
            const currentParents = current.filter(function(r) { return aeIsParentRow(r); }).length;
            const desiredParents = desired.filter(function(r) { return aeIsParentRow(r); }).length;
            if (!aeSwapData && (rowType === 'all' || rowType === 'parents') && full.length && !full.some(function(r) { return aeIsParentRow(r); })) {
                aeDbgLog('D', 'aliexpress_pricing_view.blade.php:applyFilters', 'reloading pricing-data with parents', { rowType: rowType });
                aeSwapData = true;
                table.setData('/aliexpress/pricing-data?include_parents=1').then(function() {
                    aeSwapData = false;
                    applyFilters();
                }).catch(function() { aeSwapData = false; });
                return;
            }
            if (!aeSwapData && currentParents !== desiredParents) {
                aeDbgLog('D', 'aliexpress_pricing_view.blade.php:applyFilters', 'swapping table data for rowType', {
                    rowType: rowType, currentParents: currentParents, desiredParents: desiredParents, desiredCount: desired.length
                });
                aeSwapData = true;
                table.setData(desired).then(function() {
                    aeSwapData = false;
                    applyFilters();
                }).catch(function() { aeSwapData = false; });
                return;
            }
            aeDbgLog('B', 'aliexpress_pricing_view.blade.php:applyFilters', 'rowType at filter time', {
                rowType: rowType, rawVal: $('#ae-row-type-filter').val(), currentParents: currentParents, desiredParents: desiredParents
            });

            table.setFilter(function(d) {
                return aeRowMatchesFilters(d);
            });

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
                        aePushCrossFilterActive = false;
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
                        aePushCrossFilterActive = false;
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
                        aePushCrossFilterActive = false;
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
                aePushCrossFilterActive = false;
                aeZeroSoldActive = aeMoreSoldActive = false;
                aeSyncFilterBadgeActiveClasses();
            }
            applyFilters();
        });
        $('#aliexpress-push-cross-badge').on('click', function() {
            aePushCrossFilterActive = !aePushCrossFilterActive;
            if (aePushCrossFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
                lmpMissingFilterActive = false;
                blueTriangleFilterActive = false;
                aeZeroSoldActive = aeMoreSoldActive = false;
                aeSyncFilterBadgeActiveClasses();
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
            let pushCrossCount = 0;
            let lowSgroiCount = 0;
            const countSrc = (typeof aeFullTableData !== 'undefined' && aeFullTableData.length)
                ? aeFullTableData
                : (table ? table.getData() : rows);
            countSrc.forEach(function(row) {
                if (!aeRowMatchesFilters(row, { skipBadges: true })) return;
                if (aeHasPushCross(row)) pushCrossCount++;
                if (aeRowMatchesFilters(row, { sgroiOverride: 'lt40', skipBadges: true })) lowSgroiCount++;
            });
            $('#aliexpress-push-cross-badge').html(
                '<i class="fa-solid fa-xmark"></i> ' + pushCrossCount.toLocaleString()
            );
            $('#aliexpress-push-cross-badge').toggleClass('active-filter', aePushCrossFilterActive);
            $('#ae-low-sgroi-count').text(lowSgroiCount.toLocaleString());
            $('#ae-stop-low-sgroi-btn').toggleClass('is-on', !!aeStopLowSgroi)
                .attr('data-on', aeStopLowSgroi ? '1' : '0');
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
                filterMode: "local",
                paginationMode: "local",
                initialFilter: [
                    function(data) { return !aeIsParentRow(data); }
                ],
                ajaxResponse: function(url, params, response) {
                    const rows = Array.isArray(response) ? response : [];
                    rows.forEach(function(r) {
                        if (!r || typeof r !== 'object') return;
                        const sku = String(r.sku || r.SKU || '').trim().toUpperCase();
                        if (r.is_parent === true || r.is_parent === 1 || r.is_parent === '1' || r.is_parent === 'true'
                            || r.is_parent_summary || r.is_parent_row || /\bPARENT\b/.test(sku)) {
                            r.is_parent = true;
                            r.is_parent_summary = true;
                            r.is_parent_row = true;
                        }
                    });
                    aeFullTableData = rows;
                    allTableData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    summaryDataCache = normalizeRows(rows);
                    try { updateSummary(summaryDataCache); } catch (e) { console.error(e); }
                    const rowType = $('#ae-row-type-filter').val() || 'skus';
                    const visibleRows = aeRowsForType(rowType, rows);
                    aeDbgLog('E', 'aliexpress_pricing_view.blade.php:ajaxResponse', 'stripped parents from table payload', {
                        total: rows.length,
                        parentNamed: rows.filter(function(r) { return aeIsParentRow(r); }).length,
                        returned: visibleRows.length,
                        rowType: rowType
                    });
                    setTimeout(aeApplyBadgeFilterFromUrl, 0);
                    return visibleRows;
                },
                layout: "fitDataStretch",
                height: "calc(100vh - 260px)",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200, true],
                langs: {
                    "default": {
                        "pagination": {
                            "page_size": "SKU Count"
                        }
                    }
                },
                initialSort: [],
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
                        frozen: true,
                        width: 38,
                        minWidth: 38,
                        maxWidth: 42,
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
                            const pid = String(d.ae_product_id || '').trim();
                            let buyerLink = d.buyer_link || '';
                            let sellerLink = d.seller_link || '';
                            if (pid) {
                                if (!buyerLink) buyerLink = 'https://www.aliexpress.com/item/' + encodeURIComponent(pid) + '.html';
                                if (!sellerLink) sellerLink = 'https://gsp.aliexpress.com/m_apps/product-publish/publish?productId=' + encodeURIComponent(pid);
                            }
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
                            let color = dil < 25 ? '#a00211' : dil < 50 ? '#28a745' : '#e83e8c';
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
                            const lmpForTri = aeEffectiveLmp(d);
                            const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(cell.getValue(), lmpForTri) : '');
                            const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(cell.getValue(), lmpForTri) : '');
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
                                        if (entries.length) {
                                            const extra = aeLmpLowestFromEntries(entries);
                                            return extra.lmp > 0 ? extra.lmp : null;
                                        }
                                        const v = parseFloat(r.lmp_price || r.lmp);
                                        return isFinite(v) && v > 0 ? v : null;
                                    }
                                });
                                if (avgHtml !== null) return avgHtml;
                            }
                            if (row.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const entries = row.lmp_entries || [];
                            const extra = aeLmpLowestFromEntries(entries);
                            const lowest = extra.lmp;
                            const hasLmp = lowest !== null && lowest > 0;
                            const displayNum = hasLmp ? (lowest % 1 === 0 ? lowest.toLocaleString() : lowest.toFixed(2)) : '';
                            const activeCount = entries.filter(function(e) { return !aeEntryIsIgnored(e); }).length;
                            const skuEsc = (row.sku || '').replace(/"/g, '&quot;');
                            const redDot = '<span class="ae-lmp-missing-dot d-inline-flex align-items-center justify-content-center" style="width:14px;height:14px;border-radius:50%;background:#dc3545;box-shadow:0 0 0 1px rgba(0,0,0,.08);"></span>';
                            if (hasLmp) {
                                const title = displayNum + ' (' + activeCount + ' active) — click to edit';
                                return '<span class="ae-lmp-display d-inline-flex align-items-center gap-1">' + displayNum + '</span> ' +
                                    '<button type="button" class="btn btn-sm btn-link p-0 ae-lmp-eye-btn" data-sku="' + skuEsc + '" title="' + title + '"><i class="fas fa-info-circle text-info"></i></button>';
                            }
                            if (extra.ignored_price > 0) {
                                const ign = extra.ignored_price % 1 === 0 ? extra.ignored_price.toLocaleString() : extra.ignored_price.toFixed(2);
                                return '<span class="ae-lmp-display d-inline-flex align-items-center gap-1" style="text-decoration:line-through;color:#94a3b8;">' + ign + '</span> ' +
                                    '<button type="button" class="btn btn-sm btn-link p-0 ae-lmp-eye-btn" data-sku="' + skuEsc + '" title="Ignored — not counted. Click to edit"><i class="fas fa-times text-danger"></i></button>';
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
                        editable: false,
                        headerTooltip: "Rule price (0-sold = Target GROI% / marketplace_percentages, else Std − PRMT − cvr%), then LMP-capped. Always shows the $ even when it matches live Price. Red $ + red triangle = capped at LMP. Blue triangle = S PRC ≠ Price (only when below LMP).",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const raw = aeRuleSpriceRaw(d);
                            if (!(raw > 0)) return '';
                            const lmpNow = aeEffectiveLmp(d);
                            const cap = window.SpriceLmpCap
                                ? SpriceLmpCap.apply(d, raw, aeEffectiveLmp)
                                : null;
                            let sprice = raw;
                            const atOrAboveLmp = cap
                                ? cap.alert
                                : (lmpNow > 0 && sprice + 0.0001 >= lmpNow);
                            if (cap && cap.shown > 0) sprice = cap.shown;
                            else if (atOrAboveLmp && lmpNow > 0) sprice = Math.round(lmpNow * 100) / 100;
                            if (!(sprice > 0)) return '';
                            const live = parseFloat(d.price) || 0;
                            const redTri = atOrAboveLmp
                                ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP $'
                                    + (lmpNow > 0 ? lmpNow.toFixed(2) : '') + '"></i>')
                                : '';
                            const blueTri = (!atOrAboveLmp && live > 0 && sprice > 0
                                && Math.round(live * 100) !== Math.round(sprice * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + sprice.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            const formatted = '$' + sprice.toFixed(2);
                            const priceHtml = atOrAboveLmp
                                ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                                : '<span style="font-weight:600;">' + formatted + '</span>';
                            return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                                + priceHtml + blueTri + redTri + '</span>';
                        }
                    },
                    {
                        title: "Push",
                        field: "_push",
                        hozAlign: "center",
                        headerSort: false,
                        width: 52,
                        headerTooltip: "Double tick = Sprice is live on AliExpress. Cross = not pushed. Click a cross to push that SKU.",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const sku = String(d.sku || '').replace(/"/g, '&quot;');
                            const status = String(d.SPRICE_STATUS || '').toLowerCase();
                            if (status === 'processing') {
                                return '<i class="fas fa-spinner fa-spin" style="color:#ffc107;" title="Pushing…"></i>';
                            }
                            if (aeSpricePushed(d)) {
                                return '<i class="fa-solid fa-check-double" style="color:#28a745;font-size:16px;" title="Price pushed — live Price matches Sprice"></i>';
                            }
                            const shown = aePushablePrice(d);
                            if (shown > 0 && aePushBlockedBySgroi(d, shown)) {
                                return '<i class="fas fa-ban" style="color:#adb5bd;font-size:14px;" title="Not pushed — SGROI '
                                    + aePushSgroiForPrice(d, shown) + '% is below Stop < ' + AE_MIN_SGROI + '%"></i>';
                            }
                            if (!(shown > 0)) {
                                return '<span style="color:#adb5bd;" title="Not pushed — set Sprice first">-</span>';
                            }
                            const title = 'Not pushed — click to push $' + shown.toFixed(2);
                            return '<button type="button" class="btn btn-sm ae-push-row-btn" data-sku="' + sku
                                + '" title="' + title.replace(/"/g, '&quot;')
                                + '" style="border:none;background:none;color:#dc3545;padding:0;cursor:pointer;font-size:16px;">'
                                + '<i class="fa-solid fa-xmark"></i></button>';
                        },
                        cellClick: function(e, cell) {
                            const $t = $(e.target);
                            if (!$t.closest('.ae-push-row-btn').length) return;
                            e.stopPropagation();
                            const d = cell.getRow().getData() || {};
                            if (d.is_parent || aeSpricePushed(d)) return;
                            const sku = d.sku;
                            const price = aeStoredSprice(d) || aeVisibleSprice(d);
                            if (!sku || !(price > 0)) {
                                aeNotify('Set Sprice first', 'warning');
                                return;
                            }
                            if (aePushBlockedBySgroi(d, price)) {
                                aeNotify('Not pushed — SGROI ' + aePushSgroiForPrice(d, price)
                                    + '% is below Stop < ' + AE_MIN_SGROI + '%', 'warning');
                                return;
                            }
                            cell.getRow().update({ SPRICE_STATUS: 'processing' });
                            aePushUpdatesInChunks([{ sku: sku, price: price }], $t.closest('.ae-push-row-btn'));
                        }
                    },
                    {
                        title: "SGROI",
                        field: "sroi",
                        sorter: function(a, b, aRow, bRow) {
                            const av = aeSpriceMetrics(aRow && aRow.getData ? aRow.getData() : {}).sroi;
                            const bv = aeSpriceMetrics(bRow && bRow.getData ? bRow.getData() : {}).sroi;
                            return av - bv;
                        },
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = aeSpriceMetrics(d).sroi;
                            if (isNaN(v) || v === 0) return '0%';
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
                        sorter: function(a, b, aRow, bRow) {
                            const av = aeSpriceMetrics(aRow && aRow.getData ? aRow.getData() : {}).sgpft;
                            const bv = aeSpriceMetrics(bRow && bRow.getData ? bRow.getData() : {}).sgpft;
                            return av - bv;
                        },
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = aeSpriceMetrics(d).sgpft;
                            if (isNaN(v) || v === 0) return '0%';
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(v)}%</span>`;
                        }
                    },
                ],
                dataLoaded: function(data) {
                    if (aeFullTableData.length) {
                        allTableData = aeFullTableData;
                    } else {
                        allTableData = Array.isArray(data) ? data : [];
                    }
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    if (!$('#ae-row-type-filter').val()) {
                        $('#ae-row-type-filter').val('skus');
                    }
                    setTimeout(function() {
                        try {
                            if (typeof applyFilters === 'function') applyFilters();
                        } catch (e) { console.error(e); }
                        try { updateSummary(); } catch (e) { console.error(e); }
                        if (typeof window.chPromoAutofitColumns === 'function') {
                            window.chPromoAutofitColumns(table);
                        }
                    }, 0);
                    setTimeout(function() {
                        try { aePersistVisibleSprices(); } catch (e) { console.error(e); }
                    }, 400);
                    setTimeout(function() {
                        try { aePersistVisibleSprices(); } catch (e) { console.error(e); }
                    }, 2000);
                    setTimeout(function() {
                        try { aePersistVisibleSprices(); } catch (e) { console.error(e); }
                    }, 5000);
                },
                tableBuilt: function() {
                    if (!$('#ae-row-type-filter').val()) {
                        $('#ae-row-type-filter').val('skus');
                    }
                    setTimeout(function() {
                        try {
                            if (typeof applyFilters === 'function') applyFilters();
                        } catch (e) { console.error(e); }
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
                    // #region agent log
                    try {
                        const visible = table.getData('active') || [];
                        const visibleParents = visible.filter(function(r) { return aeIsParentRow(r); }).map(function(r) { return r.sku; }).slice(0, 8);
                        aeDbgLog('D', 'aliexpress_pricing_view.blade.php:renderComplete', 'visible parent rows after render', {
                            visibleCount: visible.length,
                            visibleParentCount: visibleParents.length,
                            visibleParentSkus: visibleParents,
                            rowType: $('#ae-row-type-filter').val()
                        });
                    } catch (e) {}
                    // #endregion
                }
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    isParentRow: aeIsParentRow,
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
            $('#ae-sold-filter').on('change', function() {
                const v = $(this).val() || 'all';
                aeZeroSoldActive = (v === 'zero');
                aeMoreSoldActive = (v === 'more');
                aeSyncFilterBadgeActiveClasses();
                applyFilters();
            });

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

                    const prepared = aePrepareSpriceToSave(rowData, newSprice);
                    row.update({ sprice: prepared.sprice, sgpft: prepared.sgpft, sroi: prepared.sroi });
                    updates.push({ sku: sku, sprice: prepared.sprice });
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

            // Std Prc cell edited — Sprice is rule-only (not editable).
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
                        alwaysHidden: ['lp'],
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
                            if (!def.field || def.field === '_ae_select' || def.field === 'lp') return;
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
                        ['lp'].forEach(function(field) {
                            const col = table.getColumn(field);
                            if (col) col.hide();
                        });
                        const shipCol = table.getColumn('ship');
                        if (shipCol) shipCol.show();
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
                const wasZero = aeZeroSoldActive;
                const wasMore = aeMoreSoldActive;
                aeZeroSoldActive = aeMoreSoldActive = false;

                if (filterKey === 'zero_sold') {
                    aeZeroSoldActive = !wasZero;
                } else if (filterKey === 'more_sold') {
                    aeMoreSoldActive = !wasMore;
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
                                    rows[0].update({
                                        price: r.price,
                                        SPRICE_STATUS: 'pushed',
                                        SPRICE_PUSHED_VALUE: r.price
                                    });
                                }
                            });
                            (res.results || []).filter(r => !r.success).forEach(r => {
                                allFails.push(r);
                                const rows = table.searchRows('sku', '=', r.sku);
                                if (rows.length) rows[0].update({ SPRICE_STATUS: 'error' });
                            });
                        },
                        error: function(xhr) {
                            const r = xhr.responseJSON || {};
                            const err = r.message || r.error || ('HTTP ' + xhr.status);
                            chunks[idx].forEach(u => allFails.push({ sku: u.sku, error: err }));
                            totalFailed += chunks[idx].length;
                        },
                        complete: function() {
                            if (typeof updateSummary === 'function') {
                                try { updateSummary(); } catch (e) { /* ignore */ }
                            }
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
                const skippedLow = [];
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
                    if (aePushBlockedBySgroi(d, price)) {
                        skippedLow.push(sku);
                        return;
                    }
                    updates.push({ sku: sku, price: +price.toFixed(2) });
                });

                if (updates.length === 0) {
                    if (skippedLow.length) {
                        aeNotify('Not pushed — ' + skippedLow.length + ' SKU(s) have SGROI < ' + AE_MIN_SGROI + '%', 'warning');
                        return;
                    }
                    aeNotify('No selected SKU has a positive SPRICE or Price to push', 'error');
                    return;
                }

                const extra = [];
                if (skipped.length) extra.push(skipped.length + ' skipped — no SPRICE/Price');
                if (skippedLow.length) extra.push(skippedLow.length + ' skipped — SGROI < ' + AE_MIN_SGROI + '%');
                const summary = 'Push ' + updates.length + ' price' + (updates.length !== 1 ? 's' : '') + ' live to AliExpress?'
                    + (extra.length ? '\n(' + extra.join('; ') + ')' : '');
                if (!confirm(summary)) return;
                aePushUpdatesInChunks(updates, $('#ae-push-price-btn'));
            }

            $('#ae-push-price-btn').on('click', aePushSelectedPrices);

            $('#ae-stop-low-sgroi-btn').on('click', function(e) {
                if ($(e.target).is('#ae-min-sgroi-input')) return;
                const next = !aeStopLowSgroi;
                const $btn = $(this);
                $btn.prop('disabled', true);
                aeStopLowSgroi = next;
                aeSaveStopSgroiGuard({ notify: true }).fail(function() {
                    aeStopLowSgroi = !next;
                    aeNotify('Could not save stop-push setting', 'error');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
            $('#ae-min-sgroi-input').on('click', function(e) {
                e.stopPropagation();
            }).on('change input', function() {
                AE_MIN_SGROI = aeReadMinSgroi();
                aeSyncStopSgroiUi();
                if (typeof applyFilters === 'function') applyFilters();
                if (typeof updateSummary === 'function') updateSummary();
            }).on('change', function() {
                AE_MIN_SGROI = aeReadMinSgroi();
                aeSaveStopSgroiGuard({ notify: true }).fail(function() {
                    aeNotify('Could not save SGROI cutoff', 'error');
                });
            });
            aeSyncStopSgroiUi();

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
                    aeAppendLmpTableRow(tbody, entry.price !== undefined && entry.price !== null ? entry.price : '', entry.link || '', !!entry.ignored);
                });
                aeUpdateLmpLowestHighlight();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aeLmpModal')).show();
            }
            function aeAppendLmpTableRow(tbody, price, link, ignored) {
                const tr = $('<tr class="ae-lmp-entry-row">' +
                    '<td class="ae-lmp-num text-center align-middle"></td>' +
                    '<td class="align-middle"><input type="number" step="0.01" min="0" class="form-control form-control-sm ae-lmp-price border-0 bg-transparent" style="max-width:100px" placeholder="Price"> <span class="ae-lmp-lowest-badge"></span></td>' +
                    '<td class="align-middle"><input type="text" class="form-control form-control-sm ae-lmp-link d-inline-block me-1" style="max-width:220px" placeholder="https://..."> <a href="#" class="btn btn-sm btn-outline-primary ae-lmp-open-link" target="_blank" rel="noopener" title="Open link"><i class="fas fa-external-link-alt"></i></a></td>' +
                    '<td class="align-middle text-center"><input type="checkbox" class="form-check-input lmp-ignore-cb" title="Ignore for L1"></td>' +
                    '<td class="align-middle"><button type="button" class="btn btn-sm btn-outline-danger ae-lmp-remove-row" title="Remove"><i class="fas fa-trash-alt"></i></button></td></tr>');
                tr.find('.ae-lmp-price').val(price !== '' && price != null ? price : '');
                tr.find('.ae-lmp-link').val(link || '');
                if (ignored) {
                    tr.addClass('lmp-ignored-row');
                    tr.find('.lmp-ignore-cb').prop('checked', true);
                }
                tbody.append(tr);
                tr.find('.ae-lmp-remove-row').on('click', function(e) {
                    e.preventDefault();
                    tr.remove();
                    aeRenumberLmpRows();
                    aeUpdateLmpLowestHighlight();
                    aeSaveLmpEntriesNow();
                });
                tr.find('.ae-lmp-price, .ae-lmp-link').on('input', function() {
                    aeUpdateLmpLowestHighlight();
                    aeScheduleLmpAutosave();
                });
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
                    $(this).find('.lmp-ignore-cb')
                        .attr('data-id', 'aliexpress-' + (i + 1))
                        .attr('data-marketplace', 'aliexpress')
                        .attr('data-sku', aeLmpModalSku || '');
                });
            }
            function aeUpdateLmpLowestHighlight() {
                let minVal = null;
                let minTr = null;
                $('#aeLmpEntriesContainer .ae-lmp-entry-row').each(function() {
                    const tr = $(this);
                    const ignored = tr.find('.lmp-ignore-cb').is(':checked');
                    tr.toggleClass('lmp-ignored-row', ignored);
                    tr.removeClass('table-dark');
                    tr.find('.ae-lmp-lowest-badge').empty();
                    if (ignored) return;
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
            let aeLmpSaveTimer = null;
            let aeLmpSaveInFlight = false;
            let aeLmpSaveQueued = false;

            function aeCollectLmpEntries() {
                const entries = [];
                $('#aeLmpEntriesContainer .ae-lmp-entry-row').each(function() {
                    const price = $(this).find('.ae-lmp-price').val();
                    const link = $(this).find('.ae-lmp-link').val();
                    const ignored = $(this).find('.lmp-ignore-cb').is(':checked');
                    if (price || link) {
                        entries.push({
                            price: price ? parseFloat(price) : null,
                            link: link ? String(link).trim() : null,
                            ignored: ignored
                        });
                    }
                });
                return entries;
            }
            function aeSyncLmpToTable(entries) {
                if (!table || !aeLmpModalSku) return;
                const extra = aeLmpLowestFromEntries(entries);
                try {
                    const rows = table.searchRows('sku', '=', aeLmpModalSku);
                    (rows || []).forEach(function(row) {
                        row.update({
                            lmp_entries: entries,
                            lmp: extra.lmp,
                            lmp_price: extra.lmp,
                            lmp_link: extra.lmp_link,
                            lmp_ignored_price: extra.lmp ? null : extra.ignored_price
                        });
                        try { row.reformat(); } catch (e) { /* ignore */ }
                        if (typeof applyChannelSpriceFromStdChange === 'function') {
                            applyChannelSpriceFromStdChange(row);
                        }
                    });
                } catch (e) { /* ignore */ }
                if (typeof updateSummary === 'function') {
                    try { updateSummary(); } catch (e) { /* ignore */ }
                }
            }
            function aeSetLmpAutosaveStatus(text, kind) {
                const $el = $('#aeLmpAutosaveStatus');
                if (!$el.length) return;
                $el.removeClass('text-muted text-success text-danger text-warning');
                $el.addClass(kind === 'error' ? 'text-danger' : (kind === 'ok' ? 'text-success' : 'text-muted'));
                $el.text(text);
            }
            function aeSaveLmpEntriesNow() {
                if (!aeLmpModalSku) return;
                if (aeLmpSaveInFlight) {
                    aeLmpSaveQueued = true;
                    return;
                }
                const entries = aeCollectLmpEntries();
                aeLmpSaveInFlight = true;
                aeSetLmpAutosaveStatus('Saving…', 'muted');
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
                        aeSetLmpAutosaveStatus('Saved', 'ok');
                        aeSyncLmpToTable(entries);
                    },
                    error: function() {
                        aeSetLmpAutosaveStatus('Save failed', 'error');
                        aeNotify('Failed to save LMP', 'error');
                    },
                    complete: function() {
                        aeLmpSaveInFlight = false;
                        if (aeLmpSaveQueued) {
                            aeLmpSaveQueued = false;
                            aeSaveLmpEntriesNow();
                        }
                    }
                });
            }
            function aeScheduleLmpAutosave() {
                clearTimeout(aeLmpSaveTimer);
                aeLmpSaveTimer = setTimeout(aeSaveLmpEntriesNow, 400);
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
                aeUpdateLmpLowestHighlight();
                aeSaveLmpEntriesNow();
            });
            $('#aeLmpClearFormBtn').on('click', function() {
                $('#aeLmpNewPrice').val('');
                $('#aeLmpNewLink').val('');
            });
            LmpIgnore.bind({
                modal: '#aeLmpModal',
                marketplace: 'aliexpress',
                sku: function() { return aeLmpModalSku; },
                onToggled: function() {
                    aeUpdateLmpLowestHighlight();
                    aeSaveLmpEntriesNow();
                }
            });
            $(document).on('change', '#aeLmpModal .lmp-ignore-cb', function() {
                aeUpdateLmpLowestHighlight();
                aeSaveLmpEntriesNow();
            });

            // ── Badge trend chart (line only — same as Shein / eBay 3) ──────
            let aeBadgeLineChart = null;
            let aeBadgeMetric    = '';
            let aeBadgeDays      = 30;
            let aeBadgeAjax      = null;

            const aeDollarMetrics  = ['total_pft', 'total_sales', 'total_cogs'];
            const aePercentMetrics = ['avg_gpft', 'avg_roi', 'avg_dil', 'cvr'];

            const aeBadgeLabels = {
                total_pft: 'Profit',
                total_sales: 'Sales',
                total_al30: 'AL30',
                avg_gpft: 'GPFT%',
                avg_roi: 'GROI%',
                avg_dil: 'DIL%',
                total_cogs: 'COGS',
                total_sku: 'SKU',
                zero_sold: '0 Sold',
                more_sold: 'Sold >0',
                total_views: 'Views',
                cvr: 'CVR%',
                missing_count: 'Missing',
                map_count: 'Map',
                nmap_count: 'N Map',
            };

            function aeFormatChartVal(v) {
                const n = Number(v);
                const x = Number.isFinite(n) ? n : 0;
                if (aeDollarMetrics.includes(aeBadgeMetric)) {
                    return '$' + Math.round(x).toLocaleString('en-US');
                }
                if (aePercentMetrics.includes(aeBadgeMetric)) {
                    if (aeBadgeMetric === 'avg_dil' || aeBadgeMetric === 'cvr') {
                        return (Math.round(x * 10) / 10) + '%';
                    }
                    return Math.round(x) + '%';
                }
                return Math.round(x).toLocaleString('en-US');
            }

            function aeBadgeChartModalTitle() {
                const part = aeBadgeLabels[aeBadgeMetric] || aeBadgeMetric;
                const daily = (aeBadgeMetric === 'total_sales' || aeBadgeMetric === 'total_al30' || aeBadgeMetric === 'total_pft')
                    ? 'Daily orders'
                    : 'Daily snapshot';
                return 'Aliexpress – ' + part + ' Trend (' + daily + ')';
            }

            function aeRenderLineChart(points) {
                if (!Array.isArray(points) || !points.length) return false;

                const labels = points.map(function(p) { return p.date; });
                const values = points.map(function(p) { return Number(p.value) || 0; });
                const sorted = values.slice().sort(function(a, b) { return a - b; });
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                const dataMin = sorted[0];
                const dataMax = sorted[sorted.length - 1];

                $('#aeBadgeHighest').text(aeFormatChartVal(dataMax));
                $('#aeBadgeMedian').text(aeFormatChartVal(median));
                $('#aeBadgeLowest').text(aeFormatChartVal(dataMin));

                const lineCtx = document.getElementById('aeBadgeLineCanvas');
                if (!lineCtx || typeof Chart === 'undefined') return false;

                if (aeBadgeLineChart) aeBadgeLineChart.destroy();

                const range = dataMax - dataMin || 1;
                const pad = range * 0.1 || 1;
                const yMin = Math.min(0, dataMin - pad);
                const yMax = dataMax + pad;

                const dotColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#28a745' : '#6c757d');
                });
                // Same as dots: green = up vs prior day, red = down. Never paint every positive value red.
                const labelColors = dotColors.slice();

                const medianLinePlugin = {
                    id: 'aeBadgeMedianLine',
                    afterDraw: function(chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const c = chart.ctx;
                        const yPixel = yScale.getPixelForValue(median);
                        c.save();
                        c.setLineDash([6, 4]);
                        c.strokeStyle = '#6c757d';
                        c.lineWidth = 1.2;
                        c.beginPath();
                        c.moveTo(xScale.left, yPixel);
                        c.lineTo(xScale.right, yPixel);
                        c.stroke();
                        c.restore();
                    }
                };

                const valueLabelsPlugin = {
                    id: 'aeBadgeValueLabels',
                    afterDatasetsDraw: function(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const c = chart.ctx;
                        if (!dataset || !meta || !meta.data) return;
                        c.save();
                        c.font = 'bold 9px Inter, system-ui, sans-serif';
                        c.textAlign = 'center';
                        c.textBaseline = 'bottom';
                        meta.data.forEach(function(point, i) {
                            if (point == null || point.skip) return;
                            const txt = aeFormatChartVal(dataset.data[i]);
                            const offsetY = (i % 2 === 0) ? -8 : -16;
                            const py = point.y + offsetY;
                            c.lineJoin = 'round';
                            c.lineWidth = 3;
                            c.strokeStyle = 'rgba(255,255,255,0.92)';
                            c.strokeText(txt, point.x, py);
                            c.fillStyle = labelColors[i] || '#6c757d';
                            c.fillText(txt, point.x, py);
                        });
                        c.restore();
                    }
                };

                aeBadgeLineChart = new Chart(lineCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: 'rgba(0, 168, 168, 0.08)',
                            borderColor: '#00a8a8',
                            borderWidth: 1.5,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                            pointBorderWidth: 1.5
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 22, left: 2, right: 2, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const idx = ctx.dataIndex;
                                        const va = ctx.raw;
                                        const parts = [(aeBadgeLabels[aeBadgeMetric] || 'Value') + ': ' + aeFormatChartVal(va)];
                                        if (idx > 0) {
                                            const diff = va - values[idx - 1];
                                            parts.push('vs prior: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + aeFormatChartVal(Math.abs(diff)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: { font: { size: 9 }, callback: function(v) { return aeFormatChartVal(v); } }
                            },
                            x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } }
                        }
                    }
                });
                return true;
            }

            function aeLoadChart() {
                if (!aeBadgeMetric) return;
                if (aeBadgeAjax) aeBadgeAjax.abort();
                $('#aeBadgeNoData,#aeBadgeLineWrap').hide();
                $('#aeBadgeLoading').show();

                aeBadgeAjax = $.ajax({
                    url: '{{ route("aliexpress.badge.chart") }}',
                    method: 'GET',
                    data: { metric: aeBadgeMetric, days: aeBadgeDays },
                    success: function(res) {
                        aeBadgeAjax = null;
                        $('#aeBadgeLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (aeRenderLineChart(pts)) {
                            $('#aeBadgeLineWrap').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
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
                $('#aeBadgeChartTitle').text(aeBadgeChartModalTitle());
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aeBadgeChartModal')).show();
                aeLoadChart();
            }

            $(document).on('click', '.ae-badge-chart', function(e) {
                if ($(this).hasClass('ae-filter-badge')) return;
                e.stopPropagation();
                const m = $(this).data('metric');
                if (m) aeOpenBadgeChartModal(m);
            });

            $(document).on('change', '#aeBadgeChartRange', function() {
                const raw = $(this).val();
                const d = raw === '0' ? 0 : (parseInt(raw, 10) || 30);
                if (d === aeBadgeDays) return;
                aeBadgeDays = d;
                $('#aeBadgeChartTitle').text(aeBadgeChartModalTitle());
                aeLoadChart();
            });
        });
    </script>
@endsection
