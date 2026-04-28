@extends('layouts.vertical', ['title' => 'Shein Pricing', 'sidenav' => 'condensed'])

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

        /* Summary badges — horizontal scroll (eBay 2 / TikTok style) */
        #summary-stats .d-flex.flex-wrap.gap-2 {
            display: flex; flex-wrap: nowrap; align-items: stretch; gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin;
        }
        #summary-stats .d-flex.flex-wrap.gap-2 > .badge {
            flex: 1 1 0; min-width: 0; font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shein Pricing',
        'sub_title'  => 'Separate pricing page (sales page unchanged — Shein)',
    ])

    <div class="row">
        <div class="col-12">
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

                        {{-- Shein Stock filter --}}
                        <select id="ae-stock-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">Shein Stock</option>
                            <option value="zero">0 Shein Stock</option>
                            <option value="more">More than 0</option>
                        </select>

                        {{-- GPFT% filter --}}
                        <select id="ae-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50-60">50–60%</option>
                            <option value="50plus">50%+</option>
                        </select>

                        {{-- ROI% filter --}}
                        <select id="ae-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="125-175">125–175%</option>
                            <option value="175-250">175–250%</option>
                            <option value="gt250">&gt; 250%</option>
                        </select>

                        {{-- AL30 filter --}}
                        <select id="ae-al30-filter" class="form-select form-select-sm" style="width:130px;" title="Excludes 0 inventory items">
                            <option value="all">Sh L30</option>
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
                        <a href="{{ route('shein.pricing.sample') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download"></i> Sample
                        </a>
                        <button type="button" class="btn btn-sm btn-warning"
                            data-bs-toggle="modal" data-bs-target="#uploadPriceSheetModal">
                            <i class="fas fa-upload"></i> Upload Price
                        </button>

                        {{-- Price Mode (Increase / Decrease) – identical to TikTok --}}
                        <button id="ae-price-mode-btn" class="btn btn-sm btn-secondary" title="Cycle: Off → Decrease → Increase">
                            <i class="fas fa-exchange-alt"></i> Price Mode
                        </button>
                    </div>

                    {{-- Discount input (shown when Price Mode is active) – identical to TikTok --}}
                    <div id="ae-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <span id="ae-selected-skus-count" class="fw-bold text-secondary"></span>
                            <select id="ae-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            <input type="number" id="ae-discount-input" class="form-control form-control-sm"
                                placeholder="Enter %" step="0.01" style="width:110px;">
                            <button id="ae-apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                            <button id="ae-clear-sprice-btn" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    {{-- ── Summary badges (click chart: ae-badge-chart; hover ½s chart: ae-hover-chart; filter badges: hover-only so click = filter) ── --}}
                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-secondary fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-sku-badge" data-metric="total_sku" style="font-weight:700;cursor:pointer;" title="Click or hover (½s) for daily trend">SKU: 0</span>
                            <span class="badge bg-primary fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-sales-badge" data-metric="total_sales" style="font-weight:700;cursor:pointer;" title="Click or hover (½s) for daily trend">Sales: $0</span>
                            <span class="badge bg-success fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-pft-badge" data-metric="total_pft" style="font-weight:700;cursor:pointer;color:#111;" title="Click or hover (½s) for daily trend">PFT: $0</span>
                            <span class="badge bg-warning fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-total-al30-badge" data-metric="total_al30" style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover (½s) for daily trend">Sh L30: 0</span>
                            <span class="badge bg-info fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-gpft-badge" data-metric="avg_gpft" style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover (½s) for daily trend">GPFT: 0%</span>
                            <span class="badge bg-danger fs-6 p-2 ae-hover-chart" id="ae-missing-badge" data-metric="missing_count" style="font-weight:700;cursor:pointer;" title="Click to filter · Hover ½s for daily trend">Missing: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart" id="ae-map-badge" data-metric="map_count" style="font-weight:700;cursor:pointer;background:#198754;color:#fff;" title="Click to filter · Hover ½s for daily trend">Map: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart" id="ae-nmap-badge" data-metric="nmap_count" style="font-weight:700;cursor:pointer;background:#a71d2a;color:#fff;" title="Click to filter · Hover ½s for daily trend">N Map: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart" id="ae-zero-sold-badge" data-metric="zero_sold" style="font-weight:700;cursor:pointer;background:#dc3545;color:#fff;" title="Click to filter · Hover ½s for daily trend">0 Sold: 0</span>
                            <span class="badge fs-6 p-2 ae-hover-chart" id="ae-more-sold-badge" data-metric="more_sold" style="font-weight:700;cursor:pointer;background:#28a745;color:#fff;" title="Click to filter · Hover ½s for daily trend">&gt;0 Sold: 0</span>
                            <span class="badge bg-warning fs-6 p-2 ae-badge-chart ae-hover-chart d-none" id="ae-avg-dil-badge" data-metric="avg_dil" style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover (½s) for daily trend">DIL%: 0%</span>
                            <span class="badge bg-secondary fs-6 p-2 ae-badge-chart ae-hover-chart" id="ae-avg-roi-badge" data-metric="avg_roi" style="font-weight:700;color:#fff;cursor:pointer;" title="Click or hover (½s) for daily trend">ROI: 0%</span>
                        </div>
                    </div>

                    <div id="shein-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge trend modal — eBay 3 style: wide top-aligned dialog, line chart + stats only --}}
    <div class="modal fade" id="aeBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl shadow-none modal-dialog-scrollable" style="max-width:98vw;width:98vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="aeBadgeChartTitle">Shein — Summary (Daily snapshot)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2 me-2">
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
                                <div id="aeBadgeMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="aeBadgeLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
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

    <div class="modal fade" id="uploadPriceSheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Pricing Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="priceSheetFile" accept=".xlsx,.xls,.csv,.txt">
                    <small class="text-muted">Headers: sku, price, stock</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="uploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let table = null;
        let summaryDataCache = [];

        // Badge-click filter flags (identical to TikTok pattern)
        let aeMissingActive  = false;
        let aeMapActive      = false;
        let aeNMapActive     = false;
        let aeZeroSoldActive = false;
        let aeMoreSoldActive = false;

        // Price Mode (mirrors TikTok exactly)
        let decreaseModeActive = false;
        let increaseModeActive = false;
        let selectedSkus = new Set();

        function roundToRetailPrice(price) {
            return Math.ceil(price) - 0.01;
        }

        /** When clamped SPRICE is under $20, use cents only; at $20+ apply .99 retail rounding. */
        function finalizeSprice(price) {
            const clamped = Math.max(0.99, parseFloat(price) || 0);
            if (clamped < 20) {
                return Math.round(clamped * 100) / 100;
            }
            return roundToRetailPrice(clamped);
        }

        function syncPriceModeUi() {
            const $btn = $('#ae-price-mode-btn');
            const selectCol = table ? table.getColumn('_ae_select') : null;
            if (decreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                if (selectCol) selectCol.show();
                return;
            }
            if (increaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger').addClass('btn-primary')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                if (selectCol) selectCol.show();
                return;
            }
            $btn.removeClass('btn-danger btn-primary').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price Mode');
            if (selectCol) selectCol.hide();
            selectedSkus.clear();
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const cnt = selectedSkus.size;
            $('#ae-selected-skus-count').text(`${cnt} SKU${cnt !== 1 ? 's' : ''} selected`);
            $('#ae-discount-container').toggle(cnt > 0 && (decreaseModeActive || increaseModeActive));
        }

        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '{{ route("shein.pricing.save.sprice") }}',
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
            if (isNaN(discountVal) || discountVal === 0 || selectedSkus.size === 0) return;

            let updatedCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row     = rows[0];
                const rowData = row.getData();
                // Base on Sp. Price (special_offer) first — same as GPFT/calc_price on server.
                // Using original_price first made "decrease" raise SPRICE when original ≫ listing.
                const currentPrice = parseFloat(rowData.special_offer) || parseFloat(rowData.original_price) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = increaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = increaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = finalizeSprice(newSprice);

                const margin = parseFloat(rowData._margin) || 1;
                const lp     = parseFloat(rowData.lp)   || 0;
                const ship   = parseFloat(rowData.ship)  || 0;
                // Same formulas as GPFT / GROI
                const sgpft  = newSprice > 0 ? Math.round(((newSprice * margin - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const sroi   = lp > 0        ? Math.round(((newSprice * margin - lp - ship)  / lp)       * 100 * 100) / 100 : 0;

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

        /** Shein map "N Map|{abs diff}" — N Map badge/filter only when diff > 3 (≤3 is Map). */
        function aeSheinStrictNMapFromMap(mapVal) {
            if (!mapVal || typeof mapVal !== 'string' || !mapVal.startsWith('N Map|')) return false;
            const part = mapVal.split('|')[1];
            const d = parseFloat(String(part == null ? '' : part).trim(), 10);
            return Number.isFinite(d) && Math.abs(d) > 3;
        }

        // ── applyFilters (mirrors TikTok applyFilters) ────────────────
        function applyFilters() {
            if (!table) return;
            table.clearFilter();

            const skuSearch  = ($('#pricing-sku-search').val() || '').toLowerCase().trim();
            const rowType    = $('#ae-row-type-filter').val();
            const invFilter  = $('#ae-inv-filter').val();
            const stockFilter= $('#ae-stock-filter').val();
            const gpftFilter = $('#ae-gpft-filter').val();
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

            // Shein Stock filter
            if (stockFilter === 'zero') {
                table.addFilter(d => (parseInt(d.shein_stock, 10) || 0) === 0);
            } else if (stockFilter === 'more') {
                table.addFilter(d => (parseInt(d.shein_stock, 10) || 0) > 0);
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

            // ROI% filter
            if (roiFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const roi = parseFloat(d.groi) || 0;
                    if (roiFilter === 'lt40')    return roi < 40;
                    if (roiFilter === 'gt250')   return roi > 250;
                    const [min, max] = roiFilter.split('-').map(Number);
                    return roi >= min && roi <= max;
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

            // Map filter
            if (mapFilter === 'map') {
                table.addFilter(d => (d.map || '') === 'Map');
            } else if (mapFilter === 'nmap') {
                table.addFilter(d => aeSheinStrictNMapFromMap(d.map || ''));
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
            if (aeMissingActive)  table.addFilter(d => (d.missing || '').trim().toUpperCase() === 'M');
            if (aeMapActive)      table.addFilter(d => (d.map     || '') === 'Map');
            if (aeNMapActive)     table.addFilter(d => aeSheinStrictNMapFromMap(d.map || ''));
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

            let totalSales = 0, totalPft = 0, totalAl30 = 0;
            let gpftSum = 0, gpftCount = 0;
            let roiSum  = 0, roiCount  = 0;
            let missingCount = 0, mapCount = 0, nmapCount = 0;
            let zeroSold = 0, moreSold = 0;
            let dilSum = 0, dilCount = 0;

            const childCount = rows.filter(r => !r.is_parent).length;

            rows.forEach(row => {
                if (row.is_parent) return;
                const isMissing = (row.missing || '').trim().toUpperCase() === 'M';
                const al30   = parseFloat(row.al30)   || 0;
                const inv    = parseFloat(row.inv)    || 0;
                const ovL30  = parseFloat(row.ov_l30) || 0;

                if (!isMissing) {
                    totalSales += parseFloat(row.sales) || 0;
                    totalPft += al30 * (parseFloat(row.profit) || 0);

                    const gpft = parseFloat(row.gpft);
                    if (Number.isFinite(gpft)) { gpftSum += gpft; gpftCount++; }

                    const groi = parseFloat(row.groi);
                    if (Number.isFinite(groi)) { roiSum += groi; roiCount++; }
                }

                totalAl30 += al30;
                if (al30 === 0) zeroSold++; else moreSold++;
                if (inv > 0) { dilSum += (ovL30 / inv) * 100; dilCount++; }
                if (isMissing) missingCount++;
                const mapVal = (row.map || '').trim();
                if (!isMissing && mapVal === 'Map') mapCount++;
                else if (aeSheinStrictNMapFromMap(mapVal)) nmapCount++;
            });

            const avgGpft = gpftCount > 0 ? gpftSum / gpftCount : 0;
            const avgDil  = dilCount  > 0 ? dilSum  / dilCount  : 0;
            const avgRoi  = roiCount  > 0 ? roiSum  / roiCount  : 0;

            $('#ae-total-sku-badge').text(`SKU: ${childCount.toLocaleString()}`);
            $('#ae-total-sales-badge').text(totalSales > 0 ? `Sales: $${Math.round(totalSales).toLocaleString()}` : 'Sales: –');
            $('#ae-total-pft-badge').text(totalPft !== 0 || totalSales > 0 ? `PFT: $${Math.round(totalPft).toLocaleString()}` : 'PFT: –');
            $('#ae-total-al30-badge').text(`Sh L30: ${totalAl30.toLocaleString()}`);
            $('#ae-avg-gpft-badge').text(gpftCount  > 0 ? `GPFT: ${Math.round(avgGpft)}%`  : 'GPFT: –');
            $('#ae-missing-badge').text(`Missing: ${missingCount.toLocaleString()}`);
            $('#ae-map-badge').text(`Map: ${mapCount.toLocaleString()}`);
            $('#ae-nmap-badge').text(`N Map: ${nmapCount.toLocaleString()}`);
            $('#ae-zero-sold-badge').text(`0 Sold: ${zeroSold.toLocaleString()}`);
            $('#ae-more-sold-badge').text(`>0 Sold: ${moreSold.toLocaleString()}`);
            $('#ae-avg-dil-badge').text(dilCount > 0 ? `DIL%: ${avgDil.toFixed(1)}%` : 'DIL%: –');
            if ($('#ae-avg-roi-badge').length) {
                $('#ae-avg-roi-badge').text(roiCount > 0 ? `ROI: ${Math.round(avgRoi)}%` : 'ROI: –');
            }
        }

        $(document).ready(function() {
            table = new Tabulator("#shein-pricing-table", {
                ajaxURL: "/shein/pricing-data",
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
                        title: "Shein Stock",
                        field: "shein_stock",
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
                        title: "Sh L30",
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
                        title: "Sp. Price",
                        field: "special_offer",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            if (v === 0) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#e83e8c;font-weight:600;">${money(v)}</span>`;
                        }
                    },
                    {
                        title: "Missing",
                        field: "missing",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const value = (cell.getValue() || '').toString().trim().toUpperCase();
                            if (value === 'M') return '<span class="badge bg-danger">M</span>';
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
                            const val = (cell.getValue() || '').trim();
                            if (val === 'Map') return '<span style="color:#198754;font-weight:bold;">Map</span>';
                            if (val.startsWith('N Map|')) {
                                const part = val.split('|')[1];
                                const ad = Math.abs(parseFloat(String(part || '').trim(), 10) || 0);
                                if (Number.isFinite(ad) && ad <= 3) {
                                    return '<span style="color:#198754;font-weight:bold;">Map</span>';
                                }
                                return `<span style="color:#dc3545;font-weight:bold;">N Map (${part})</span>`;
                            }
                            return '';
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
                            const r = Math.round(v);
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:${d.is_parent?'700':'600'};">${r}%</span>`;
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
                            if      (v < 40)  color = '#a00211';  // red    – < 40%
                            else if (v < 75)  color = '#ffc107';  // yellow – 40–75%
                            else if (v < 125) color = '#3591dc';  // blue   – 75–125%
                            else if (v < 250) color = '#28a745';  // green  – 125–250%
                            else              color = '#e83e8c';  // pink   – > 250%
                            const r = Math.round(v);
                            return `<span style="color:${color};font-weight:600;">${r}%</span>`;
                        }
                    },
                    {
                        title: "Profit",
                        field: "profit",
                        visible: false,
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
                    //     title: "Sh L30",
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
                            const r = Math.round(v);
                            // Same color coding as GPFT
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${r}%</span>`;
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
                            const r = Math.round(v);
                            // Same color ranges as GROI
                            let color;
                            if      (v < 40)  color = '#a00211';
                            else if (v < 75)  color = '#ffc107';
                            else if (v < 125) color = '#3591dc';
                            else if (v < 250) color = '#28a745';
                            else              color = '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${r}%</span>`;
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
            $('#ae-gpft-filter').on('change',   function() { applyFilters(); });
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

            // ── Price Mode (Increase / Decrease) ─────────────────────
            $('#ae-price-mode-btn').on('click', function() {
                if (!decreaseModeActive && !increaseModeActive) {
                    decreaseModeActive = true; increaseModeActive = false;
                } else if (decreaseModeActive) {
                    decreaseModeActive = false; increaseModeActive = true;
                } else {
                    decreaseModeActive = false; increaseModeActive = false;
                }
                syncPriceModeUi();
            });

            $('#ae-discount-type').on('change', function() {
                $('#ae-discount-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
            });
            $('#ae-apply-discount-btn').on('click', function() { applyAeDiscount(); });
            $('#ae-discount-input').on('keypress', function(e) { if (e.which === 13) applyAeDiscount(); });
            $('#ae-clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

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
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - ship - lp) / sprice) * 100 * 100) / 100 : 0;
                const sroi  = lp     > 0 ? Math.round(((sprice * margin - lp - ship)  / lp)    * 100 * 100) / 100 : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi });
                saveSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            // Badge click filters (identical to TikTok)
            $('#ae-missing-badge').on('click', function() {
                aeMissingActive = !aeMissingActive;
                aeMapActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
                applyFilters();
            });
            $('#ae-map-badge').on('click', function() {
                aeMapActive = !aeMapActive;
                aeMissingActive = aeNMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
                applyFilters();
            });
            $('#ae-nmap-badge').on('click', function() {
                aeNMapActive = !aeNMapActive;
                aeMissingActive = aeMapActive = aeZeroSoldActive = aeMoreSoldActive = false;
                applyFilters();
            });
            $('#ae-zero-sold-badge').on('click', function() {
                aeZeroSoldActive = !aeZeroSoldActive;
                aeMoreSoldActive = aeMissingActive = aeMapActive = aeNMapActive = false;
                applyFilters();
            });
            $('#ae-more-sold-badge').on('click', function() {
                aeMoreSoldActive = !aeMoreSoldActive;
                aeZeroSoldActive = aeMissingActive = aeMapActive = aeNMapActive = false;
                applyFilters();
            });

            $('#refresh-pricing-table').on('click', function() {
                table.setData("/shein/pricing-data");
            });

            $('#export-pricing-btn').on('click', function() {
                table.download("csv", "shein_pricing_data.csv");
            });

            $('#uploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('priceSheetFile').files[0];
                if (!file) {
                    alert('Please select a file first.');
                    return;
                }

                const $btn = $('#uploadPriceSheetBtn');
                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');

                $btn.prop('disabled', true);

                $.ajax({
                    url: '/shein/pricing-upload-price',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response && response.success === false) {
                            const errMsg = response.message || 'Price upload failed.';
                            if (window.toastr) toastr.error(errMsg);
                            else alert(errMsg);
                            return;
                        }
                        if (window.toastr) {
                            toastr.success((response && response.message) ? response.message : 'Price upload completed.');
                        } else {
                            alert((response && response.message) ? response.message : 'Price upload completed.');
                        }
                        $('#uploadPriceSheetModal').modal('hide');
                        $('#priceSheetFile').val('');
                        table.setData('/shein/pricing-data');
                    },
                    error: function(xhr) {
                        let message = 'Price upload failed.';
                        const j = xhr.responseJSON;
                        if (j) {
                            if (j.message) message = j.message;
                            else if (j.errors) {
                                message = Object.values(j.errors).flat().join(' ');
                            }
                        } else if (xhr.status === 419) {
                            message = 'Session expired. Refresh the page and try again.';
                        } else if (xhr.status === 0) {
                            message = 'Network error. Check your connection.';
                        }
                        if (window.toastr) toastr.error(message);
                        else alert(message);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // ── Badge trend chart (eBay 3 style: line, median line, point labels, hover ½s, Lifetime range) ──────
            let aeBadgeLineChart = null;
            let aeBadgeMetric = '';
            let aeBadgeDays = 30;
            let aeBadgeAjax = null;

            const aeDollarMetrics = ['total_sales', 'total_cogs', 'total_pft'];
            const aePercentMetrics = ['avg_gpft', 'avg_roi', 'avg_dil'];

            const aeBadgeLabels = {
                total_sales: 'Sales',
                total_pft: 'PFT',
                total_al30: 'Sh L30',
                avg_gpft: 'GPFT %',
                avg_roi: 'ROI %',
                avg_dil: 'DIL %',
                total_cogs: 'COGS',
                missing_count: 'Missing',
                map_count: 'Map',
                nmap_count: 'N Map',
                total_sku: 'SKU',
                zero_sold: '0 Sold',
                more_sold: '> 0 Sold',
            };

            function aeFormatChartVal(v) {
                const n = Number(v);
                if (aeDollarMetrics.includes(aeBadgeMetric)) {
                    const x = Number.isFinite(n) ? n : 0;
                    return '$' + Math.round(x).toLocaleString('en-US');
                }
                if (aePercentMetrics.includes(aeBadgeMetric)) {
                    const x = Number.isFinite(n) ? n : 0;
                    if (aeBadgeMetric === 'avg_dil') return x.toFixed(1) + '%';
                    return Math.round(x) + '%';
                }
                return Math.round(Number.isFinite(n) ? n : 0).toLocaleString('en-US');
            }

            function aeBadgeChartModalTitle() {
                const part = aeBadgeLabels[aeBadgeMetric] || aeBadgeMetric;
                return 'Shein — ' + part + ' (Daily snapshot)';
            }

            function aeOpenBadgeChartModal(metricKey) {
                aeBadgeMetric = metricKey;
                aeBadgeDays = 30;
                $('#aeBadgeChartRange').val('30');
                $('#aeBadgeChartTitle').text(aeBadgeChartModalTitle());
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aeBadgeChartModal')).show();
                aeLoadChart();
            }

            let aeBadgeHoverTimer = null;
            $(document).on('mouseenter', '.ae-hover-chart', function() {
                const metric = $(this).data('metric');
                if (!metric) return;
                aeBadgeHoverTimer = setTimeout(function() {
                    aeOpenBadgeChartModal(metric);
                }, 500);
            });
            $(document).on('mouseleave', '.ae-hover-chart', function() {
                if (aeBadgeHoverTimer) {
                    clearTimeout(aeBadgeHoverTimer);
                    aeBadgeHoverTimer = null;
                }
            });
            $(document).on('mousedown', '.ae-hover-chart', function() {
                if (aeBadgeHoverTimer) {
                    clearTimeout(aeBadgeHoverTimer);
                    aeBadgeHoverTimer = null;
                }
            });

            function aeRenderLineChart(points) {
                if (!Array.isArray(points) || !points.length) return false;

                const labels = points.map(p => p.date);
                const values = points.map(p => Number(p.value) || 0);
                const sorted = [...values].sort((a, b) => a - b);
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
                const yMin = dataMin - pad;
                const yMax = dataMax + pad;

                const dotColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#198754' : '#6c757d');
                });

                const labelColors = values.map(function(v, i) {
                    if (i < 7) return '#6c757d';
                    return v < values[i - 7] ? '#dc3545' : (v > values[i - 7] ? '#198754' : '#6c757d');
                });

                const medianLinePlugin = {
                    id: 'aeSheinMedianLine',
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
                    id: 'aeSheinValueLabels',
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
                            c.fillStyle = labelColors[i];
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
                    url: '{{ route("shein.badge.chart") }}',
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

            $(document).on('click', '.ae-badge-chart', function(e) {
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
