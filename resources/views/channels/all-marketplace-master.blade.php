@extends('layouts.vertical', ['title' => 'Active Channel', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa !important;
        }

        /* Channel logo thumbnail (Img column) */
        .channel-logo-thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }

        .channel-logo-link {
            display: inline-block;
            line-height: 0;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .channel-logo-link:hover .channel-logo-thumb {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
        }

        .channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f1f3f5;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 12px;
        }

        /* Logo preview inside Add / Edit channel modals */
        .channel-logo-preview {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            overflow: hidden;
        }

        .channel-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .channel-logo-preview .placeholder-text {
            color: #adb5bd;
            font-size: 11px;
            text-align: center;
            line-height: 1.1;
            padding: 4px;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Freeze header + left columns inside the table viewport */
        #marketplace-table-wrapper {
            height: calc(100vh - 290px);
            min-height: 360px;
            width: 100%;
        }
        #marketplace-table.tabulator {
            height: 100%;
        }
        #marketplace-table.tabulator .tabulator-header {
            z-index: 24;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        #marketplace-table.tabulator .tabulator-header .tabulator-frozen {
            z-index: 26;
        }

        #marketplace-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
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
            font-size: 11px;
            font-weight: 600;
            color: black !important;
            overflow: visible;
            text-overflow: clip;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
            overflow: visible;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        /* Bottom calc row styling - ensure visibility */
        .tabulator-row.tabulator-calcs {
            background: #f8f9fa !important;
            font-weight: bold !important;
            border-top: 2px solid #4361ee !important;
        }

        .tabulator-row.tabulator-calcs .tabulator-cell {
            background: #f8f9fa !important;
            font-weight: bold !important;
            color: #333 !important;
        }

        .tabulator-row.tabulator-calcs-bottom {
            display: table-row !important;
            visibility: visible !important;
        }

        /* Ensure bottom calc cells are visible */
        .tabulator .tabulator-footer .tabulator-calcs-holder .tabulator-row {
            display: table-row !important;
        }

        .tabulator .tabulator-footer .tabulator-calcs-holder {
            display: block !important;
        }

        /* Type badges */
        .type-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .type-b2c {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }

        .type-b2b {
            background: linear-gradient(135deg, #4568dc 0%, #b06ab3 100%);
            color: white;
        }

        .type-dropship {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        /* Toast container */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Modal z-index fix */
        .modal {
            z-index: 9999 !important;
        }

        .modal-backdrop {
            z-index: 9998 !important;
        }

        /* Ensure modals are visible */
        .modal.show {
            display: block !important;
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            max-height: 400px;
            overflow-y: auto;
        }

        .dropdown-item label {
            cursor: pointer;
            margin: 0;
        }

        .dropdown-item input[type="checkbox"] {
            margin-right: 8px;
        }

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin, not --bs-modal-*) */
        #adBreakdownChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #adBreakdownChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #adBreakdownChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }

        #yesterdayMpModal .modal-dialog {
            max-width: 1100px;
        }
        #yesterdayMpTable th {
            font-size: 11px;
            white-space: nowrap;
            text-align: center;
            background: #e6e6e6;
        }
        #yesterdayMpTable td {
            font-size: 12px;
            vertical-align: middle;
        }
        #yesterdayMpTable tfoot td {
            font-weight: 700;
            background: #f8f9fa;
            border-top: 2px solid #4361ee;
        }
        #yesterdayMpTable .ymp-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            white-space: nowrap;
        }
        #yesterdayMpTable td.text-end .ymp-cell {
            justify-content: flex-end;
            width: 100%;
        }
        #yesterdayMpTable .ymp-chart-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex: 0 0 auto;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.12);
            vertical-align: 0.05em;
        }
        #yesterdayMpTable .ymp-chart-dot:hover {
            transform: scale(1.35);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.18);
        }
        #yesterdayMpTable .ymp-hover-cell {
            cursor: pointer;
        }

        /* Summary badges — horizontal scroll; each badge keeps full width (no flex-shrink overlap) */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.4rem;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 4px; /* room for scrollbar */
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 0 0 auto;
            min-width: max-content;
            max-width: none;
            font-size: 0.8125rem;
            padding: 0.4rem 0.55rem;
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }
        #summary-stats .summary-trend-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 0.22rem;
            margin-left: 0;
            flex-shrink: 0;
            cursor: pointer;
            vertical-align: 0.08em;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
            position: relative;
            z-index: 2;
        }
        #summary-stats .summary-trend-dot:hover {
            transform: scale(1.25);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.25);
        }
        #summary-stats .summary-trend-dot.up { background: #22c55e; }
        #summary-stats .summary-trend-dot.down { background: #ef4444; }
        #summary-stats .summary-trend-dot.flat,
        #summary-stats .summary-trend-dot.none { background: #9ca3af; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Active Channel Master',
        'sub_title' => '',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Search -->
                    <input type="text" id="channel-search" class="form-control form-control-sm"
                        placeholder="Search Channel..." style="width: 150px; display: inline-block;">

                    <!-- Type Filter (hidden from UI) -->
                    <select id="type-filter" class="form-select form-select-sm" style="width: auto; display: none;">
                        <option value="all">All Types</option>
                        <option value="B2C">🛒 B2C</option>
                        <option value="B2B">🏢 B2B</option>
                        <option value="Dropship">📦 Dropship</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-dark" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                            data-bs-auto-close="outside" title="Columns" aria-label="Columns">
                            <i class="fas fa-columns" style="color: #000;"></i>
                        </button>
                        <div class="dropdown-menu p-0" id="column-dropdown-menu"
                            aria-labelledby="columnVisibilityDropdown"
                            style="max-height:none; overflow:visible; min-width:720px; width:max-content;">
                            <ul id="column-dropdown-list" class="list-unstyled mb-0 px-2 py-1"
                                style="display:grid; grid-template-columns:repeat(5, minmax(120px, 1fr)); gap:0 0.25rem; max-height:360px; overflow-y:auto;">
                                <!-- Populated dynamically -->
                            </ul>
                        </div>
                    </div>

                    <button id="addChannelBtn" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal"
                        data-bs-target="#addChannelModal" title="Add Channel" aria-label="Add Channel">
                        <i class="fas fa-plus-circle" style="color: #000;"></i>
                    </button>
                    <button type="button" id="yesterdayMpViewBtn" class="btn btn-sm btn-outline-dark"
                        title="Yesterday by marketplace" aria-label="Yesterday by marketplace">
                        <i class="fas fa-eye" style="color: #000;"></i>
                    </button>
                    <a href="{{ route('l7.marketplace.master') }}" class="btn btn-sm btn-outline-dark"
                        title="7-Day Channel Master — same metrics as Yesterday over 7 complete days" aria-label="7-Day Channel Master">
                        <i class="fas fa-calendar-week" style="color: #000;"></i>
                    </a>

                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2 ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            <span class="summary-trend-dot none" title="Channel count"></span>Channels: <span id="total-channels">0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="l30_sales" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of Sales column. Amz = last {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific (same window &amp; AMAZON_SALES_TOTAL_MODE as Amz Daily Sales). Other channels vary.">
                            <span class="summary-trend-dot none" data-metric="l30_sales" title="Rolling history"></span>Sales: <span id="total-l30-sales">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_sales" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;" title="Sum of Y Sales column (Yesterday's sales across all channels). Trend is built from daily snapshots: older days that pre-date Y Sales being captured will be skipped.">
                            <span class="summary-trend-dot none" data-metric="y_sales" title="Rolling history"></span>Y Sales: <span id="total-y-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="l30_orders" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of Orders column. Amz = {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }}-day Pacific rolling (same as Amz Daily Sales); other channels vary.">
                            <span class="summary-trend-dot none" data-metric="l30_orders" title="Rolling history"></span>Orders: <span id="total-l30-orders">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link d-none" data-metric="qty" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="qty" title="Rolling history"></span>Qty items: <span id="total-qty">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="gprofit" style="color: black; font-weight: bold; cursor:pointer;" title="Blended Gprofit% = sum(Sales×G%) / sum(Sales) using each channel’s rolling Sales column; matches GPFT column footer">
                            <span class="summary-trend-dot none" data-metric="gprofit" title="Rolling history"></span>GPFT: <span id="avg-gprofit">0.0%</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 d-none" style="color: black; font-weight: bold; border: 1px solid rgba(0,0,0,.25);" title="Gross profit $ = sum of (rolling Sales × Gprofit%) per channel; matches Gross PFT column (show column to verify)">
                            <span class="summary-trend-dot none" data-metric="gprofit" title="Rolling history"></span>GPFT: <span id="total-gross-pft">$0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="groi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="groi" title="Rolling history"></span>G ROI: <span id="avg-groi">0.0%</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="ad_spend" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="ad_spend" title="Rolling history"></span>Spend: <span id="total-ad-spend">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="ads_pct" style="background-color: #d63384; color: white; font-weight: bold; cursor:pointer;" title="Ads % = Total Ad Spend / Total L30 Sales × 100 (blended across channels — same as the Ads % column). Reverb bump fees count as spend.">
                            <span class="summary-trend-dot none" data-metric="ads_pct" title="Rolling history"></span>Ads: <span id="ads-percent-badge">0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="total_views" style="color: black; font-weight: bold; cursor:pointer;" title="View trend - Total Views (listing/Map traffic)">
                            <span class="summary-trend-dot none" data-metric="total_views" title="Rolling history"></span>Clicks: <span id="total-views-badge">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="cvr" style="color: white; font-weight: bold; cursor:pointer;" title="Listing CVR (all channels): weighted from each channel's listing CVR × views (same as the CVR column). Falls back to Qty ÷ Views when a channel has no listing CVR.">
                            <span class="summary-trend-dot none" data-metric="cvr" title="Rolling history"></span>CVR: <span id="cvr-pct-badge">0.00%</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="pft" style="color: black; font-weight: bold; cursor:pointer;" title="Net profit $ = sum(rolling Sales×Gprofit% − Ad spend); same as Sales × (G% − Ad Spend/Sales) per channel">
                            <span class="summary-trend-dot none" data-metric="pft" title="Rolling history"></span>NPFT: <span id="total-pft">$0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="npft" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="npft" title="Rolling history"></span>NPFT: <span id="avg-npft">0.0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="nroi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            <span class="summary-trend-dot none" data-metric="nroi" title="Rolling history"></span>NROI: <span id="avg-nroi">0.0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="inventory" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of (Inventory × Amz Price)">
                            <span class="summary-trend-dot none" data-metric="inventory" title="Rolling history"></span>inv: <span id="inventory-value-amazon">0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="inv_at_sp" style="color: black; font-weight: bold; cursor:pointer;" title="Inventory Sum = Shopify INV × Standard Price (amazon_data_view.STANDARD_PRICE) for active SKUs">
                            <span class="summary-trend-dot none" data-metric="inv_at_sp" title="Rolling history"></span>Inv@SP: <span id="inv-at-sp">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="inv_at_lp" style="color: black; font-weight: bold; cursor:pointer;" title="View trend - Sum of (Shopify inventory × LP)">
                            <span class="summary-trend-dot none" data-metric="inv_at_lp" title="Rolling history"></span>Inv@LP: <span id="inv-at-lp">0</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="tat" style="color: white; font-weight: bold; cursor:pointer;" title="TAT = inv ÷ L30 Sales (months of stock at current sales)">
                            <span class="summary-trend-dot none" data-metric="tat" title="Rolling history"></span>TAT: <span id="tat-badge">0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="reviews" style="color: black; font-weight: bold; cursor:pointer;" title="Weighted average product rating and total review count across channels (amazon_product_reviews).">
                            <span class="summary-trend-dot none" data-metric="reviews" title="Rolling history"></span>Reviews: <span id="ratings-reviews-badge">0 ★ | 0</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="marketplace-table-wrapper" style="width: 100%;">
                    <div id="marketplace-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Channel Modal -->
    <div class="modal fade" id="addChannelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Channel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="channelForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="channelName" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="channelName" required>
                        </div>
                        <div class="mb-3">
                            <label for="channelAlias" class="form-label">Alias</label>
                            <input type="text" class="form-control" id="channelAlias" maxlength="190"
                                placeholder="Short display name">
                            <small class="text-muted">Shown in the Alias column; click it to open this channel's view.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelPromotions" class="form-label">Promotions (%)</label>
                            <input type="number" class="form-control" id="channelPromotions" step="0.01"
                                min="0" placeholder="e.g. 10">
                            <small class="text-muted">Manual promotions percentage for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelComplianceCount" class="form-label">Compliance Count</label>
                            <input type="number" class="form-control" id="channelComplianceCount" step="1"
                                min="0" placeholder="e.g. 5">
                            <small class="text-muted">Manual compliance count for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelLogo" class="form-label">Channel Logo</label>
                            <div class="d-flex align-items-center gap-2">
                                <div id="channelLogoPreview" class="channel-logo-preview">
                                    <span class="placeholder-text">No logo</span>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="channelLogo"
                                        accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml,image/webp">
                                    <small class="text-muted">PNG, JPG, GIF, SVG, or WEBP. Max 2 MB.</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="channelSellerLink" class="form-label">Seller Link</label>
                            <input type="url" class="form-control" id="channelSellerLink"
                                placeholder="https://..." maxlength="1000">
                            <small class="text-muted">When provided, the channel logo will link to this URL.</small>
                        </div>
                        <div class="mb-3">
                            <label for="channelUrl" class="form-label">Sheet Link</label>
                            <input type="url" class="form-control" id="channelUrl">
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-control" id="type">
                                <option value="">Select Type</option>
                                <option value="B2B">B2B</option>
                                <option value="B2C">B2C</option>
                                <option value="Dropship">Dropship</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="channelUpdate" class="form-label">Data Source</label>
                            <select class="form-control" id="channelUpdate">
                                <option value="">Select</option>
                                <option value="A">API</option>
                                <option value="S">GS</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveChannelBtn">Save Channel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Channel Modal -->
    <div class="modal fade" id="editChannelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit Channel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editChannelForm" enctype="multipart/form-data">
                        <input type="hidden" id="originalChannel" name="original_channel">
                        <input type="hidden" id="editExistingLogo">
                        @php
                            $canEditChannelName = in_array(strtolower(trim((string) (auth()->user()->email ?? ''))), [
                                'support@5core.com',
                                'president@5core.com',
                                'software5@5core.com',
                            ], true);
                        @endphp
                        <div class="mb-3">
                            <label for="editChannelName" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="editChannelName" {{ $canEditChannelName ? '' : 'readonly' }}>
                            @unless ($canEditChannelName)
                                <small class="text-muted">Channel name editing is restricted.</small>
                            @endunless
                        </div>
                        <div class="mb-3">
                            <label for="editChannelAlias" class="form-label">Alias</label>
                            <input type="text" class="form-control" id="editChannelAlias" maxlength="190"
                                placeholder="Short display name">
                            <small class="text-muted">Shown in the Alias column; click it to open this channel's view.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelPromotions" class="form-label">Promotions (%)</label>
                            <input type="number" class="form-control" id="editChannelPromotions" step="0.01"
                                min="0" placeholder="e.g. 10">
                            <small class="text-muted">Manual promotions percentage for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelComplianceCount" class="form-label">Compliance Count</label>
                            <input type="number" class="form-control" id="editChannelComplianceCount" step="1"
                                min="0" placeholder="e.g. 5">
                            <small class="text-muted">Manual compliance count for this channel.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelLogo" class="form-label">Channel Logo</label>
                            <div class="d-flex align-items-center gap-2">
                                <div id="editChannelLogoPreview" class="channel-logo-preview">
                                    <span class="placeholder-text">No logo</span>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="editChannelLogo"
                                        accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml,image/webp">
                                    <small class="text-muted">Choose a file to replace the current logo. Max 2 MB.</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelSellerLink" class="form-label">Seller Link</label>
                            <input type="url" class="form-control" id="editChannelSellerLink"
                                placeholder="https://..." maxlength="1000">
                            <small class="text-muted">When provided, the channel logo will link to this URL.</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelUrl" class="form-label">Sheet URL</label>
                            <input type="text" class="form-control" id="editChannelUrl" required>
                        </div>
                        <div class="mb-3">
                            <label for="editType" class="form-label">Type</label>
                            <select class="form-control" id="editType" required>
                                <option value="">Select Type</option>
                                <option value="B2B">B2B</option>
                                <option value="B2C">B2C</option>
                                <option value="Dropship">Dropship</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editMissingLink" class="form-label">Blade page link</label>
                            <input type="url" class="form-control" id="editMissingLink" placeholder="https://...">
                            <small class="text-muted">This link will open when clicking channel name</small>
                        </div>
                        <div class="mb-3">
                            <label for="editChannelUpdate" class="form-label">Data Source</label>
                            <select class="form-control" id="editChannelUpdate">
                                <option value="">Select</option>
                                <option value="A">API</option>
                                <option value="S">GS</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="archiveChannelBtn" title="Archive channel">
                        <i class="fa fa-archive"></i> Archive
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateChannelBtn">Update Channel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Yesterday by marketplace -->
    <div class="modal fade" id="yesterdayMpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2" style="background: linear-gradient(135deg, #17a2b8, #0d6efd);">
                    <h6 class="modal-title text-white mb-0">
                        <i class="fas fa-eye me-2"></i>
                        Yesterday by marketplace
                        <span class="fw-normal" style="opacity:.9;">— {{ now('America/Los_Angeles')->subDay()->format('M j, Y') }} (Pacific, 1-day — not L30)</span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="yesterdayMpTable">
                            <thead>
                                <tr>
                                    <th class="text-start">Marketplace</th>
                                    <th>Y Sales</th>
                                    <th>GPFT</th>
                                    <th>GROI</th>
                                    <th>NROI</th>
                                    <th>NPFT</th>
                                    <th>Views</th>
                                    <th>CVR</th>
                                    <th>Orders</th>
                                </tr>
                            </thead>
                            <tbody id="yesterdayMpTableBody"></tbody>
                            <tfoot id="yesterdayMpTableFoot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel History Table Modal -->
    <div class="modal fade" id="channelHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-history me-2"></i>
                        <span id="modalChannelName">Channel</span> - Historical Data (31-day Pacific, through yesterday)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead style="background: #f5f7fa;">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Sales</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end">Qty items</th>
                                    <th class="text-end">Clicks</th>
                                    <th class="text-end">Gprofit%</th>
                                    <th class="text-end">NPFT%</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Channel History Graph Modal -->
    <div class="modal fade" id="channelHistoryGraphModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-chart-area me-2"></i>
                        <span id="modalGraphChannelName">Channel</span> - Historical Graph (31-day Pacific, through yesterday)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingGraphMessage" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading chart data...</p>
                    </div>
                    <div id="dataInfoMessage" class="alert alert-info" style="display: none; margin-bottom: 15px;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="dataPointsInfo"></span>
                    </div>
                    <div id="historyGraphContainer" style="width: 100%; height: 550px; display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clicks Breakdown Modal -->
    <div class="modal fade" id="clicksBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-mouse-pointer me-2"></i>
                        <span id="clicksModalChannelName">Channel</span> - Advertising Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Ad Clicks</th>
                                </tr>
                            </thead>
                            <tbody id="clicksBreakdownTableBody">
                                <tr>
                                    <td colspan="2" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="clicksBreakdownTotalClicks">0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ad Sales Breakdown Modal -->
    <div class="modal fade" id="adSalesBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-dollar-sign me-2"></i>
                        <span id="salesModalChannelName">Channel</span> - Ad Sales Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Ad Sales</th>
                                    <th class="text-end">ACOS</th>
                                    <th class="text-end">TACOS</th>
                                </tr>
                            </thead>
                            <tbody id="adSalesBreakdownTableBody">
                                <tr>
                                    <td colspan="4" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="adSalesBreakdownTotalSales">$0</th>
                                    <th class="text-end" id="adSalesBreakdownTotalAcos">-</th>
                                    <th class="text-end" id="adSalesBreakdownTotalTacos">-</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CVR Breakdown Modal -->
    <div class="modal fade" id="cvrBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-line me-2"></i>
                        <span id="cvrModalChannelName">Channel</span> - Ads CVR Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">Ads CVR</th>
                                </tr>
                            </thead>
                            <tbody id="cvrBreakdownTableBody">
                                <tr>
                                    <td colspan="2" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="cvrBreakdownTotalCvr">-</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ad Breakdown Chart Modal -->
    <div class="modal fade p-0" id="adBreakdownChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="adChartModalTitle">Ad Breakdown - Rolling window</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="adChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31" >31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="adBreakdownChartContainer" style="height: 28vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="adBreakdownChart"></canvas>
                        </div>
                        <div id="adChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="adChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="adChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="adChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="adChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="adChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">Daily data is not available for this channel/ad type.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing Ads Breakdown Modal -->
    <div class="modal fade" id="missingAdsBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="missingModalChannelName">Channel</span> - L Missing Ads Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-end">L Missing Ads</th>
                                </tr>
                            </thead>
                            <tbody id="missingAdsBreakdownTableBody">
                                <tr>
                                    <td colspan="2" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-end" id="missingAdsBreakdownTotal">0</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endsection

@section('script-bottom')
    <script>
        let table = null;
        var channelMetricDotTrendsUrl = "{{ url('channel-metric-dot-trends') }}";
        var dotTrendsLoadedOnce = false;
        var DEFAULT_DOT_GRAY = '#6c757d';

        function snapshotChannelKey(name) {
            var k = (name || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '');
            var aliases = {
                ebay2: 'ebaytwo',
                ebay3: 'ebaythree',
                shopify: 'shopifyb2c',
                tiktok: 'tiktokshop',
                tiktok2: 'tiktokshop2',
                bestbuy: 'bestbuyusa',
                facebookmarketplace: 'fbmarketplace'
            };
            return aliases[k] || k;
        }

        // Chart dots AND outer badge/table dots follow the previous day
        // (tooltip "vs Yesterday"). ACOS & TAcos % invert (lower is better).
        function metricChartDotColors(values, isInverted) {
            var gray = '#6c757d';
            var green = '#28a745';
            var red = '#dc3545';
            return values.map(function(v, i) {
                if (i === 0) return gray;
                var prev = values[i - 1];
                if (v === prev) return gray;
                if (isInverted) return v < prev ? green : red;
                return v > prev ? green : red;
            });
        }

        // Toast notification helper
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

        // Parse number helper
        function parseNumber(value) {
            if (value === null || value === undefined || value === '' || value === 'N/A') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        // Yesterday's date formatted in America/Los_Angeles (e.g. "Jun 14"). The current PT
        // day is still in progress so its rolling-window data isn't final yet — every "data
        // through …" label we surface points at the last COMPLETED Pacific day instead.
        function caLastCompletedMdy() {
            try {
                const fmt = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'America/Los_Angeles',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                const parts = fmt.formatToParts(new Date());
                const y = parseInt(parts.find(p => p.type === 'year').value, 10);
                const mShort = parts.find(p => p.type === 'month').value;
                const d = parseInt(parts.find(p => p.type === 'day').value, 10);
                // Build a UTC date at PT midnight today, subtract one day, then format short.
                const monthIdx = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'].indexOf(mShort);
                const dt = new Date(Date.UTC(y, monthIdx, d));
                dt.setUTCDate(dt.getUTCDate() - 1);
                return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(dt);
            } catch (e) {
                const d = new Date();
                d.setDate(d.getDate() - 1);
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
        }
        // Normalize percentage for display: backend may send 0.15 or 15; always show as 0-100 scale with %
        function asPercent(value) {
            const n = parseNumber(value);
            if (!n) return 0;
            if (n > 0 && n <= 1) return n * 100; // decimal form
            return n;
        }

        // Track main Ad columns visibility (from Total Ad Spend to Missing Ads)
        let adColumnsVisible = false;
        const mainAdColumnFields = ['Total Ad Spend'];

        function toggleMainAdColumns() {
            adColumnsVisible = !adColumnsVisible;
            mainAdColumnFields.forEach(field => {
                if (adColumnsVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });

            // Update button text
            const btn = document.getElementById('toggleAdColumnsBtn');
            if (adColumnsVisible) {
                btn.innerHTML = '<i class="fas fa-ad"></i> Hide Ads Data';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-secondary');
            } else {
                btn.innerHTML = '<i class="fas fa-ad"></i> Show Ads Data';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-outline-secondary');
            }
        }

        // Track Ad Spend breakdown columns visibility
        let adSpendBreakdownVisible = false;
        const adSpendBreakdownFields = [];

        function toggleAdSpendBreakdownColumns() {
            adSpendBreakdownVisible = !adSpendBreakdownVisible;
            if (adSpendBreakdownVisible) {
                adSpendBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'Total Ad Spend' : adSpendBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                adSpendBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        // Track AD CLICKS breakdown columns visibility
        let clicksBreakdownVisible = false;
        const clicksBreakdownFields = [];

        function toggleClicksBreakdownColumns() {
            clicksBreakdownVisible = !clicksBreakdownVisible;
            if (clicksBreakdownVisible) {
                clicksBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'clicks' : clicksBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                clicksBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        // Track AD SALES breakdown columns visibility
        let adSalesBreakdownVisible = false;
        const adSalesBreakdownFields = [];

        function toggleAdSalesBreakdownColumns() {
            adSalesBreakdownVisible = !adSalesBreakdownVisible;
            adSalesBreakdownFields.forEach(field => {
                if (adSalesBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        // Track AD SOLD breakdown columns visibility
        let adSoldBreakdownVisible = false;
        const adSoldBreakdownFields = [];

        function toggleAdSoldBreakdownColumns() {
            adSoldBreakdownVisible = !adSoldBreakdownVisible;
            adSoldBreakdownFields.forEach(field => {
                if (adSoldBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        // Track ACOS breakdown columns visibility
        let acosBreakdownVisible = false;
        const acosBreakdownFields = [];

        function toggleAcosBreakdownColumns() {
            acosBreakdownVisible = !acosBreakdownVisible;
            if (acosBreakdownVisible) {
                acosBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'ACOS' : acosBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                acosBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        // Track AD CVR breakdown columns visibility
        let cvrBreakdownVisible = false;
        const cvrBreakdownFields = [];

        function toggleCvrBreakdownColumns() {
            cvrBreakdownVisible = !cvrBreakdownVisible;
            if (cvrBreakdownVisible) {
                cvrBreakdownFields.forEach((field, i) => {
                    table.showColumn(field);
                    const afterField = i === 0 ? 'Ads CVR' : cvrBreakdownFields[i - 1];
                    table.moveColumn(field, afterField, true);
                });
            } else {
                cvrBreakdownFields.forEach(field => table.hideColumn(field));
            }
        }

        const AC_PRICING_FILTER_CHANNELS = ['Shein', 'Aliexpress', 'Faire', 'Reverb', 'TopDawg'];
        let acPricingCellHoverTimer = null;

        function acOpenPricingPageWithBadge(missingLink, badge) {
            if (!missingLink || !badge) return;
            const sep = missingLink.indexOf('?') >= 0 ? '&' : '?';
            window.open(missingLink + sep + 'badge=' + encodeURIComponent(badge), '_blank');
        }

        function acPricingHoverCellHtml(channel, chartMetric, filterBadge, valueHtml, extraStyle) {
            if (!AC_PRICING_FILTER_CHANNELS.includes(channel)) {
                return `<span style="${extraStyle || ''}">${valueHtml}</span>`;
            }
            return `<span class="ac-pricing-hover-cell" data-channel="${channel}" data-chart-metric="${chartMetric}" data-filter-badge="${filterBadge}" style="${extraStyle || ''}cursor:pointer;" title="Hover for trend · Click to filter on pricing page">${valueHtml}</span>`;
        }

        $(document).ready(function() {
            var lastDotColorByKey = {};
            var lastDotPairByKey = {};
            var invertedDotMetrics = ['acos', 'ads_pct'];
            var metricDotMetricKeys = ['missing_l','map','nmap','l60_sales','l60_orders','l30_sales','y_sales','ad_spend','l30_orders','qty','groi','gprofit','ads_pct','nroi','npft','pft','clicks','ad_sales','ad_sold','acos','ads_cvr','cvr','total_views','inv_at_lp','inv_at_sp','inventory','tat','reviews'];
            var dotTrendsPrefetch = null;

            function getMetricDotColor(channelName, metricKey) {
                var k = snapshotChannelKey(channelName) + '_' + (metricKey || '');
                return lastDotColorByKey[k] || DEFAULT_DOT_GRAY;
            }
            function saveDotColorsToStorage() {
                try {
                    localStorage.setItem('channelMasterDotColors', JSON.stringify(lastDotColorByKey));
                } catch (e) { /* ignore */ }
            }
            function hydrateDotColorsFromStorage() {
                try {
                    var raw = localStorage.getItem('channelMasterDotColors');
                    if (!raw) return;
                    var parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        lastDotColorByKey = parsed;
                    }
                } catch (e) { /* ignore */ }
            }
            function applyDotTrendsMap(channels) {
                if (!channels) return;
                Object.keys(channels).forEach(function(channel) {
                    var metrics = channels[channel] || {};
                    Object.keys(metrics).forEach(function(metric) {
                        var pair = metrics[metric];
                        var v1 = pair && pair[0] != null ? parseFloat(pair[0]) : null;
                        var v2 = pair && pair[1] != null ? parseFloat(pair[1]) : null;
                        lastDotPairByKey[channel + '_' + metric] = [v1, v2];
                        if (v1 == null || v2 == null || isNaN(v1) || isNaN(v2)) {
                            lastDotColorByKey[channel + '_' + metric] = DEFAULT_DOT_GRAY;
                            return;
                        }
                        var isInverted = invertedDotMetrics.indexOf(metric) >= 0;
                        lastDotColorByKey[channel + '_' + metric] = v1 === v2 ? DEFAULT_DOT_GRAY
                            : isInverted ? (v2 < v1 ? '#28a745' : '#dc3545')
                            : (v2 > v1 ? '#28a745' : '#dc3545');
                    });
                });
                saveDotColorsToStorage();
            }
            function channelKeysFromTableData(tableData) {
                var data = tableData && Array.isArray(tableData) ? tableData : (table && table.getData ? table.getData() : []);
                var channelKeys = [];
                for (var i = 0; i < data.length; i++) {
                    var ch = snapshotChannelKey(data[i]['Channel '] || data[i]['Channel'] || '');
                    if (ch) channelKeys.push(ch);
                }
                return channelKeys;
            }
            function paintMetricDots(channelKeys) {
                document.querySelectorAll('.metric-chart-icon, .ad-chart-icon').forEach(function(el) {
                    var ch = el.getAttribute('data-channel');
                    var metric = el.getAttribute('data-metric') || 'ad_spend';
                    var color = getMetricDotColor(ch, metric);
                    el.style.color = color;
                    var prev = el.previousElementSibling;
                    if (prev && prev.tagName === 'SPAN' && prev.style && String(prev.style.fontWeight) === '600') {
                        prev.style.color = color;
                    }
                });
                if (typeof colorSummaryBadgeDots === 'function') {
                    colorSummaryBadgeDots(channelKeys || []);
                }
            }

            // Last-visit colors first so the table never paints a full gray row, then
            // refresh from /channel-metric-dot-trends in parallel with the table AJAX.
            hydrateDotColorsFromStorage();
            dotTrendsPrefetch = $.ajax({
                url: channelMetricDotTrendsUrl || '/channel-metric-dot-trends',
                type: 'GET',
                dataType: 'json'
            }).done(function(response) {
                if (response && response.success && response.channels) {
                    applyDotTrendsMap(response.channels);
                    if (table) {
                        paintMetricDots(Object.keys(response.channels));
                    }
                }
            });

            // Initialize Tabulator
            function marketplaceTableHeight() {
                const wrap = document.getElementById('marketplace-table-wrapper');
                if (!wrap) return 400;
                const top = wrap.getBoundingClientRect().top;
                return Math.max(360, Math.floor(window.innerHeight - top - 12));
            }
            (function sizeMarketplaceTableWrap() {
                const wrap = document.getElementById('marketplace-table-wrapper');
                if (wrap) wrap.style.height = marketplaceTableHeight() + 'px';
            })();

            table = new Tabulator("#marketplace-table", {
                ajaxURL: "/channels-master-data",
                ajaxParams: { size: 10000, page: 1 },
                ajaxSorting: false,
                layout: "fitDataStretch",
                height: "100%",
                pagination: false,
                columnCalcs: "both",
                initialSort: [{
                    column: "L30 Sales",
                    dir: "desc"
                }],
                ajaxResponse: function(url, params, response) {
                    if (response && response.data) {
                        if (response.data.length === 0 && response.message) {
                            showToast('info', response.message || 'No channels to display.');
                        }
                        updateSummaryStats(response.data);
                        function setCompactInvBadge(elId, rawVal, titlePrefix) {
                            const el = document.getElementById(elId);
                            if (!el || rawVal == null) return 0;
                            const val = Math.round(parseFloat(rawVal) || 0);
                            let compact;
                            if (Math.abs(val) >= 1000000) {
                                compact = (val / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                            } else if (Math.abs(val) >= 1000) {
                                compact = Math.round(val / 1000) + 'K';
                            } else {
                                compact = String(val);
                            }
                            el.textContent = compact;
                            const badge = el.closest('.badge');
                            if (badge) {
                                badge.title = titlePrefix + ': $' + val.toLocaleString('en-US');
                                badge.setAttribute('data-exact-value', String(val));
                            }
                            return val;
                        }
                        const invVal = setCompactInvBadge(
                            'inventory-value-amazon',
                            response.inventory_value_amazon,
                            'Sum of (Inventory × Amz Price)'
                        );
                        setCompactInvBadge(
                            'inv-at-lp',
                            response.inv_at_lp,
                            'View trend - Sum of (Shopify inventory × LP)'
                        );
                        setCompactInvBadge(
                            'inv-at-sp',
                            response.inv_at_sp,
                            'Inventory Sum — Shopify INV × Standard Price'
                        );
                        // TAT = inv ÷ L30 Sales (months of cover)
                        const tatEl = document.getElementById('tat-badge');
                        if (tatEl && response.data && response.data.length) {
                            let totalSales = 0;
                            response.data.forEach(function(row) {
                                const s = (row['L30 Sales'] || 0);
                                totalSales += (typeof parseNumber === 'function' ? parseNumber(s) : parseFloat(String(s).replace(/[^0-9.-]/g, ''))) || 0;
                            });
                            const tat = totalSales > 0 ? invVal / totalSales : 0;
                            const tatRounded = tat > 0 ? parseFloat(tat.toFixed(2)) : 0;
                            tatEl.textContent = tat > 0 ? tat.toFixed(2) : '0.00';
                            const tatBadge = tatEl.closest('.badge');
                            if (tatBadge) {
                                tatBadge.setAttribute('data-exact-value', String(tatRounded));
                                tatBadge.title = 'TAT = inv ÷ L30 Sales (months of stock): ' + tatRounded.toFixed(2);
                            }
                        }
                        if (response.dot_trends) {
                            applyDotTrendsMap(response.dot_trends);
                        }
                        if (!dotTrendsLoadedOnce) {
                            dotTrendsLoadedOnce = true;
                            loadMetricDotTrends(response.data);
                        }
                        return response.data;
                    }
                    return [];
                },
                ajaxRequestError: function(error) {
                    const msg = (error && error.responseJSON && error.responseJSON.message) ? error.responseJSON.message : 'Failed to load channel data. Check console for details.';
                    if (typeof showToast === 'function') showToast('error', msg);
                },
                columns: [{
                        title: "Img",
                        field: "logo",
                        frozen: true,
                        width: 60,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const logo = cell.getValue();
                            const channel = (rowData['Channel '] || '').trim();
                            const sellerLink = (rowData['seller_link'] || '').trim();

                            const imgHtml = logo
                                ? `<img src="/storage/${logo}" alt="${channel}" class="channel-logo-thumb" onerror="this.style.display='none'"/>`
                                : `<span class="channel-logo-placeholder" title="No logo">
                                       <i class="fas fa-image text-muted"></i>
                                   </span>`;

                            if (sellerLink) {
                                const safeLink = sellerLink.replace(/"/g, '&quot;');
                                return `<a href="${safeLink}" target="_blank" rel="noopener noreferrer" title="Open seller page" class="channel-logo-link">${imgHtml}</a>`;
                            }
                            return imgHtml;
                        }
                    },
                    {
                        title: "MP",
                        field: "Channel ",
                        frozen: true,
                        formatter: function(cell) {
                            const channel = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const missingLink = rowData['missing_link'] || '';

                            const channelDisplay = missingLink
                                ? `<a href="${missingLink}" target="_blank" class="missing-l-link channel-name-link" style="color:inherit;font-weight:inherit;text-decoration:none;" title="View missing items">${channel}</a>`
                                : `<span>${channel}</span>`;

                            return `<div>${channelDisplay}</div>`;
                        }
                    },
                    {
                        // Alias: short display label set per channel in the Edit modal.
                        // Clicking it opens the channel's tabulator view (the same
                        // "Blade page link" / missing_link used by the channel name).
                        title: "Channel",
                        field: "alias",
                        hozAlign: "center",
                        headerTooltip: "Channel alias — click to open this channel's view.",
                        formatter: function(cell) {
                            const alias = (cell.getValue() || '').toString().trim();
                            if (!alias) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            const rowData = cell.getRow().getData();
                            const viewLink = rowData['missing_link'] || '';
                            if (viewLink) {
                                return `<a href="${viewLink}" target="_blank" class="channel-alias-link" style="color:#0d6efd;font-weight:600;text-decoration:none;cursor:pointer;" title="Open ${alias} view">${alias}</a>`;
                            }
                            return `<span style="font-weight:600;">${alias}</span>`;
                        }
                    },
                    {
                        title: "Missing L",
                        field: "Miss",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                       
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const additionSheet = rowData['addition_sheet'] || '';
                            const channel = (rowData['Channel '] || '').trim();
                            // Dot matches number (trend dot can invert vs value and look "wrong")
                            const dotColor = value === 0 ? '#198754' : (value > 0 ? '#dc3545' : DEFAULT_DOT_GRAY);
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="missing_l" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;

                            const textColor = value === 0 ? '#198754' : value > 0 ? '#dc3545' : 'black';
                            const style = `color:${textColor};font-weight:600;`;

                            if (additionSheet) {
                                return `<a href="${additionSheet}" target="_blank" style="${style}text-decoration:none;cursor:pointer;" title="Click to open addition sheet">${value}</a>${chartIcon}`;
                            }

                            const valueHtml = String(value);
                            const body = acPricingHoverCellHtml(channel, 'missing_l', 'missing', valueHtml, style);
                            return body + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                                return;
                            }
                            const hoverEl = e.target.closest('.ac-pricing-hover-cell');
                            if (hoverEl) {
                                e.stopPropagation();
                                const rowData = cell.getRow().getData();
                                acOpenPricingPageWithBadge(rowData['missing_link'] || '', hoverEl.getAttribute('data-filter-badge'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Map",
                        field: "Map",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = value === 0 ? DEFAULT_DOT_GRAY : '#198754';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="map" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const style = value === 0 ? 'color:#6c757d;font-weight:600;' : 'color:#198754;font-weight:600;';
                            const valueHtml = value.toLocaleString('en-US');
                            return acPricingHoverCellHtml(channel, 'map', 'map', valueHtml, style) + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                                return;
                            }
                            const hoverEl = e.target.closest('.ac-pricing-hover-cell');
                            if (hoverEl) {
                                e.stopPropagation();
                                const rowData = cell.getRow().getData();
                                acOpenPricingPageWithBadge(rowData['missing_link'] || '', hoverEl.getAttribute('data-filter-badge'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "N Map",
                        field: "NMap",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = value === 0 ? '#28a745' : '#dc3545';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nmap" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;

                            const color = value === 0 ? 'green' : 'red';
                            const style = `color:${color};font-weight:bold;`;
                            const valueHtml = String(value);

                            return acPricingHoverCellHtml(channel, 'nmap', 'nmap', valueHtml, style) + chartIcon;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                                return;
                            }
                            const hoverEl = e.target.closest('.ac-pricing-hover-cell');
                            if (hoverEl) {
                                e.stopPropagation();
                                const rowData = cell.getRow().getData();
                                acOpenPricingPageWithBadge(rowData['missing_link'] || '', hoverEl.getAttribute('data-filter-badge'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L Missing Ads",
                        field: "Missing Ads",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();

                            if (!value || value === 0) return '-';

                            // Add info icon for channels that might have PT/KW/HL breakdown
                            const infoIcon = `<i class="fas fa-info-circle missing-info-icon ms-1" 
                                data-channel="${channel}" 
                                style="cursor:pointer;color:#dc3545;font-size:12px;" 
                                title="View L Missing Ads Breakdown"></i>`;

                            return `<span style="font-weight:600;color:#dc3545;">${value.toLocaleString('en-US')}</span>${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('missing-info-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                showMissingAdsBreakdown(
                                channelName); // Show Missing Ads specific modal
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong style="color:#dc3545;">${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L60 Sales",
                        field: "L-60 Sales",
                        headerTooltip: "Sales from days 31-60 (previous 30-day period: {{ \Carbon\Carbon::now()->subDays(60)->format('M d, Y') }} – {{ \Carbon\Carbon::now()->subDays(31)->format('M d, Y') }}).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l60_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l60_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View L60 Sales Chart"></i>`;
                            return `<span style="font-weight: 600;">$${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            return `<strong>$${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Sales",
                        field: "L30 Sales",
                        headerTooltip: "Rolling sales per channel. Amz = last {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} complete Pacific days through yesterday — Seller Central Ordered product sales (ItemPrice − promo; shipping/gift/tax excluded; Canceled excluded).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l30_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l30_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight: 600;">$${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            return `<strong>$${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L60 Orders",
                        field: "L60 Orders",
                        headerTooltip: "Order count from the previous 30-day period (L60 window). Shein: from API-synced shein_daily_data_l60 when populated.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l60_orders');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l60_orders" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View L60 Orders Chart"></i>`;
                            if (!value) return `<span style="color:#adb5bd;">—</span>${chartIcon}`;
                            return `<span style="font-weight: 600;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span');
                                cv = cv ? parseFloat(cv.textContent.replace(/[,$%\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            return `<strong>${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Growth",
                        field: "Growth",
                        visible: false,
                        headerTooltip: "Growth % comparing yesterday’s sales (Y Sales) to sales on the Pacific day 30 days before yesterday (D30 Sales). Formula: ((Y Sales − D30 Sales) / D30 Sales) × 100. Green = up vs that day, red = down.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 88,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            let growth = null;
                            const value = cell.getValue();

                            if (value !== null && value !== undefined && value !== '' && value !== '—') {
                                const growthStr = String(value).replace('%', '');
                                const parsed = parseFloat(growthStr);
                                if (!isNaN(parsed)) growth = parsed;
                            }

                            // Fallback: Y Sales vs D30 Sales (day −30)
                            if (growth === null) {
                                const ySales = parseNumber(rowData['Y Sales'] || 0);
                                const d30 = parseNumber(rowData['D30 Sales'] || 0);
                                if (!d30) {
                                    return '<span style="color:#adb5bd;">—</span>';
                                }
                                growth = ((ySales - d30) / d30) * 100;
                            }

                            if (!isFinite(growth)) {
                                return '<span style="color:#adb5bd;">—</span>';
                            }

                            if (Math.abs(growth) < 0.1) {
                                return '<span style="font-weight:600;color:#6c757d;">0%</span>';
                            }

                            const isPositive = growth > 0;
                            const color = isPositive ? '#198754' : '#dc3545';
                            const arrow = isPositive ? '↑' : '↓';

                            return `<span style="font-weight:600;color:${color};">${arrow} ${Math.abs(growth).toFixed(0)}%</span>`;
                        },
                        bottomCalc: function(values, data) {
                            let sumY = 0;
                            let sumD30 = 0;
                            data.forEach(function(row) {
                                sumY += parseNumber(row['Y Sales'] || 0);
                                const d30 = row['D30 Sales'];
                                if (d30 !== null && d30 !== undefined && d30 !== '') {
                                    sumD30 += parseNumber(d30);
                                }
                            });
                            if (sumD30 === 0) return null;
                            return ((sumY - sumD30) / sumD30) * 100;
                        },
                        bottomCalcFormatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '<strong>—</strong>';
                            const growth = parseNumber(v);
                            if (!isFinite(growth)) return '<strong>—</strong>';

                            if (Math.abs(growth) < 0.1) {
                                return '<strong style="color:#6c757d;">0%</strong>';
                            }

                            const isPositive = growth > 0;
                            const color = isPositive ? '#198754' : '#dc3545';
                            const arrow = isPositive ? '↑' : '↓';

                            return `<strong style="color:${color};">${arrow} ${Math.abs(growth).toFixed(0)}%</strong>`;
                        }
                    },
                    {
                        title: "Y Sales",
                        field: "Y Sales",
                        headerTooltip: "Yesterday Pacific. Amz = Seller Central Ordered product sales for that calendar day (shipping/gift/tax excluded).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue() || 0);
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'y_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="y_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            if (!value || value === 0) {
                                // NYS = "No Yesterday Sales" — shown whenever a channel had
                                // zero revenue on the prior PST day.
                                return `<span style="color:#adb5bd;font-weight:600;" title="No Yesterday Sales">NYS</span>${chartIcon}`;
                            }
                            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong style="color:#adb5bd;" title="No Yesterday Sales">NYS</strong>';
                            return `<strong style="color:#0d6efd;">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "L7 Sales",
                        field: "L7 Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        headerTooltip: "Sales over the last 7 Pacific calendar days ending yesterday (inclusive).",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue() || 0);
                            if (!value || value === 0) {
                                return `<span style="color:#adb5bd;font-weight:600;" title="No L7 Sales">-</span>`;
                            }
                            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong style="color:#adb5bd;">-</strong>';
                            return `<strong style="color:#0d6efd;">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                      
                        title: "Source",
                        field: "Update",
                        headerTooltip: "Data source tag for this channel. A = API, S = GS. Set in the channel's Edit modal.",
                        hozAlign: "center",
                        width: 80,
                        sorter: function(a, b) {
                            // Sort A before S before blanks, so unset rows sink to the bottom
                            const na = (a === 'A' || a === 'S') ? a : 'Z';
                            const nb = (b === 'A' || b === 'S') ? b : 'Z';
                            return na.localeCompare(nb);
                        },
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            const v = (raw === 'A' || raw === 'S') ? raw : '';
                            if (!v) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            // Color-coded chip so the two values are scannable at a glance.
                            const bg    = v === 'A' ? '#198754' : '#fd7e14';
                            const label = v === 'A' ? 'API'     : 'GS';
                            return `<span class="badge" style="background-color:${bg};color:#fff;font-weight:600;min-width:24px;" title="${label}">${label}</span>`;
                        }
                    },
                    {
                        // Manual Promotions % entered per channel in the Edit modal.
                        title: "Promotions",
                        field: "promotions",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        headerTooltip: "Manual promotions percentage — set in the channel's Edit modal.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || isNaN(parseFloat(raw))) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            const val = parseFloat(raw);
                            return `<span style="font-weight:600;">${val.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2})}%</span>`;
                        }
                    },
                    {
                        title: "Spend",
                        field: "Total Ad Spend",
                        hozAlign: "center",
                        sorter: "number",
                        visible: true,
                        formatter: function(cell) {
                            const totalSpent = parseNumber(cell.getValue() || 0);
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (totalSpent === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            // Trend dot compares the last two COMPLETED Pacific days — today's
                            // PT date is excluded because its rolling-window value is still
                            // accruing. The tooltip surfaces that date so the value is read
                            // correctly across timezones.
                            const dotTitle = `Data through ${caLastCompletedMdy()} PT. Click to view chart.`;
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_spend" style="cursor:pointer;color:${dotColor};font-size:8px;" title="${dotTitle}"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-spend-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Spend Breakdown"></i>`;
                            return `<span style="font-weight:600;">$${Math.round(totalSpent).toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('ad-spend-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAdSpendBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "views",
                        field: "Total Views",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: true,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value == null || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'total_views');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="total_views" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                const span = cell.getElement().querySelector('span');
                                const cv = span ? parseFloat(span.textContent.replace(/[,$%\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "CVR",
                        field: "CVR",
                        headerTooltip: "Per channel: Qty ÷ Total Views — units-based (matches /temu-decrease). Total Views come from listing/Map snapshots (traffic to offers), not the same as ad clicks. Compare to &quot;AD CVR&quot; (ad sold ÷ clicks). Big view updates can lower this % without &quot;true&quot; conversion collapsing.",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            // Prefer server-provided CVR (Temu / Temu 2 use temu_l30 ÷ product_clicks
                            // to match the /temu-decrease & /temu2-decrease badges exactly); fall back
                            // to Qty ÷ Total Views for channels that don't pre-compute it.
                            const aData = aRow.getData(), bData = bRow.getData();
                            const aServer = (aData['CVR'] !== undefined && aData['CVR'] !== null && aData['CVR'] !== '');
                            const bServer = (bData['CVR'] !== undefined && bData['CVR'] !== null && bData['CVR'] !== '');
                            const cvrA = aServer ? parseNumber(aData['CVR']) : (parseNumber(aData['Total Views'] || 0) > 0 ? (parseNumber(aData['Qty'] || 0) / parseNumber(aData['Total Views'])) * 100 : 0);
                            const cvrB = bServer ? parseNumber(bData['CVR']) : (parseNumber(bData['Total Views'] || 0) > 0 ? (parseNumber(bData['Qty'] || 0) / parseNumber(bData['Total Views'])) * 100 : 0);
                            return cvrA - cvrB;
                        },
                        width: 70,
                        visible: true,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const views = parseNumber(row['Total Views'] || 0);
                           
                            const serverCvr = row['CVR'];
                            const hasServerCvr = (serverCvr !== undefined && serverCvr !== null && serverCvr !== '');
                            let pct;
                            if (hasServerCvr) {
                                pct = parseNumber(serverCvr);
                            } else {
                                if (views === 0) return '-';
                                const qty = parseNumber(row['Qty'] || 0);
                                pct = (qty / views) * 100;
                            }
                            const dotColor = getMetricDotColor(channel, 'cvr');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="cvr" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View CVR trend"></i>`;
                            return `<span style="font-weight:600;color:${dotColor};">${(Math.round(pct * 100) / 100).toFixed(2)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var span = cell.getElement().querySelector('span');
                                var cv = span ? parseFloat(span.textContent.replace(/[$,%,\s]/g, '')) : null;
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            
                            let totalQty = 0, totalViews = 0;
                            data.forEach(function(row) {
                                totalQty += parseNumber(row['Qty'] || 0);
                                totalViews += parseNumber(row['Total Views'] || 0);
                            });
                            if (totalViews === 0) return '-';
                            return '<strong>' + ((totalQty / totalViews) * 100).toFixed(2) + '%</strong>';
                        }
                    },
                    {
                        title: "Orders",
                        field: "L30 Orders",
                        headerTooltip: "Rolling order count per channel. Amz = {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific — same as Amz Daily Sales (Canceled/Cancelled excluded).",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'l30_orders');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l30_orders" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Qty items",
                        field: "Qty",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'qty');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="qty" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "G ROI %",
                        field: "G Roi",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'groi');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="groi" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            let style = '';

                            if (value <= 50) {
                                style = 'color:#a00211;';
                            } else if (value > 50 && value <= 75) {
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
                            } else if (value > 75 && value <= 125) {
                                style = 'color:#28a745;';
                            } else {
                                style = 'color:#8000ff;';
                            }

                            return `<span style="${style}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "GPFT%",
                        field: "Gprofit%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'gprofit');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="gprofit" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            let style = '';

                            if (value >= 0 && value <= 10) {
                                style = 'color:#a00211;';
                            } else if (value > 10 && value <= 18) {
                                style =
                                    'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
                            } else if (value > 18 && value <= 25) {
                                style = 'color:#3591dc;';
                            } else if (value > 25 && value <= 40) {
                                style = 'color:#28a745;';
                            } else {
                                style = 'color:#e83e8c;';
                            }

                            return `<span style="${style}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        },
                        bottomCalc: function(values, data) {
                            let gpDollars = 0, totalL30 = 0;
                            data.forEach(function(row) {
                                const l30 = parseNumber(row['L30 Sales'] || 0);
                                const gp = parseNumber(row['Gprofit%'] || 0);
                                gpDollars += (gp / 100) * l30;
                                totalL30 += l30;
                            });
                            return totalL30 > 0 ? (gpDollars / totalL30) * 100 : 0;
                        },
                        bottomCalcFormatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            return `<strong>${v.toFixed(1)}%</strong>`;
                        }
                    },
                    {
                        title: "Gross PFT",
                        field: "_gross_pft",
                        visible: false,
                        hozAlign: "center",
                        sorter: "number",
                        mutator: function(value, data) {
                            const l30 = parseNumber(data['L30 Sales'] || 0);
                            const gp = parseNumber(data['Gprofit%'] || 0);
                            return (gp / 100) * l30;
                        },
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            return `<span>$${Math.round(v).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Ads %",
                        field: "Ads%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const channelRaw = (rowData['Channel '] || '').trim();
                            const channel = channelRaw.toLowerCase();
                            const dotColor = getMetricDotColor(channelRaw, 'ads_pct');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channelRaw}" data-metric="ads_pct" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;

                            let adsPercent = 0;
                            if (channel === 'walmart' || channel === 'topdawg' || channel ===
                                'shopifyb2c') {
                                adsPercent = parseNumber(rowData['TACOS %'] || 0);
                            } else {
                                adsPercent = parseNumber(cell.getValue() || 0);
                            }

                            let style = '';
                            if (adsPercent < 5) {
                                style = 'color:#e83e8c;';
                            } else if (adsPercent >= 5 && adsPercent <= 10) {
                                style = 'color:#28a745;';
                            } else {
                                style = 'color:#a00211;';
                            }

                            return `<span style="${style}font-weight:600;">${adsPercent.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "NP$",
                        field: "NP$",
                        headerTooltip: "Net Profit Amount = L30 Sales × NPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const l30Sales = parseNumber(rowData['L30 Sales'] || 0);
                            const npftPercent = parseNumber(rowData['N PFT'] || 0);
                            const netProfitAmount = (l30Sales * npftPercent) / 100;
                            
                            if (netProfitAmount === 0) {
                                return '<span style="color:#adb5bd;">—</span>';
                            }
                            
                            const color = netProfitAmount > 0 ? '#198754' : '#dc3545';
                            return `<span style="font-weight:600;color:${color};">$${Math.round(netProfitAmount).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: function(values, data) {
                            let totalNetProfit = 0;
                            data.forEach(function(row) {
                                const l30Sales = parseNumber(row['L30 Sales'] || 0);
                                const npftPercent = parseNumber(row['N PFT'] || 0);
                                totalNetProfit += (l30Sales * npftPercent) / 100;
                            });
                            return totalNetProfit;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>—</strong>';
                            const color = value > 0 ? '#198754' : '#dc3545';
                            return `<strong style="color:${color};">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "NROI %",
                        field: "N ROI",
                        hozAlign: "center",
                        // Most channels: (Gross Profit − Ad Spend) / COGS × 100.
                        // Temu / Temu 2: GROI% − Ads% (same as /temu-decrease after Ads reduce).
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'nroi');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nroi" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            let style = '';

                            if (value <= 50) {
                                style = 'color:#a00211;';
                            } else if (value > 50 && value <= 75) {
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
                            } else if (value > 75 && value <= 125) {
                                style = 'color:#28a745;';
                            } else {
                                style = 'color:#8000ff;';
                            }

                            return `<span style="${style}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "NPFT%",
                        field: "N PFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = getMetricDotColor(channel, 'npft');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="npft" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            let style = '';

                            if (value >= 0 && value <= 10) {
                                style = 'color:#a00211;';
                            } else if (value > 10 && value <= 18) {
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;';
                            } else if (value > 18 && value <= 25) {
                                style = 'color:#3591dc;';
                            } else if (value > 25 && value <= 40) {
                                style = 'color:#28a745;';
                            } else {
                                style = 'color:#e83e8c;';
                            }

                            return `<span style="${style}font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "KW $",
                        field: "KW Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">$${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#198754;">$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "PT $",
                        field: "PT Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">$${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#0d6efd;">$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "HL $",
                        field: "HL Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">$${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#dc3545;">$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "PMT $",
                        field: "PMT Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">$${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#ffc107;">$${parseNumber(value).toFixed(2)}</strong>`;
                        }
                    },
                    {
                        title: "Shop $",
                        field: "Shopping Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">$${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#4285f4;">$${parseNumber(value).toFixed(2)}</strong>`;
                        }
                    },
                    {
                        title: "SERP $",
                        field: "SERP Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_spend');
                            const chartIcon = `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">$${value.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), 'spend');
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong style="color:#6f42c1;">$${parseNumber(value).toFixed(2)}</strong>`;
                        }
                    },
                    {
                        title: "AD CLICKS",
                        field: "clicks",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'clicks');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="clicks" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right clicks-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Clicks Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('clicks-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleClicksBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // Hidden Clicks Breakdown Columns
                    {
                        title: "KW Clicks",
                        field: "KW Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#198754;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PT Clicks",
                        field: "PT Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#0d6efd;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "HL Clicks",
                        field: "HL Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#dc3545;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PMT Clicks",
                        field: "PMT Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#ffc107;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Shop Clicks",
                        field: "Shopping Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#4285f4;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "SERP Clicks",
                        field: "SERP Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="clicks" style="cursor:pointer;color:${getMetricDotColor(channel, 'clicks')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#6f42c1;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "AD SALES",
                        field: "Ad Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 120,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_sales');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_sales" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-sales-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Ad Sales Breakdown"></i>`;
                            return `<span style="font-weight:600;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('ad-sales-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAdSalesBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // Hidden Ad Sales Breakdown Columns
                    {
                        title: "KW Sales",
                        field: "KW Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#198754;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PT Sales",
                        field: "PT Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#0d6efd;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "HL Sales",
                        field: "HL Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#dc3545;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PMT Sales",
                        field: "PMT Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#ffc107;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Shop Sales",
                        field: "Shopping Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#4285f4;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "SERP Sales",
                        field: "SERP Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="sales" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sales')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#6f42c1;">$${Math.round(parseNumber(cell.getValue())).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "AD SOLD",
                        field: "ad_sold",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ad_sold');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_sold" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-sold-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Ad Sold Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('ad-sold-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAdSoldBreakdownColumns();
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // Hidden Ad Sold Breakdown Columns
                    {
                        title: "KW Sold",
                        field: "KW Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#198754;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PT Sold",
                        field: "PT Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#0d6efd;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "HL Sold",
                        field: "HL Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#dc3545;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "PMT Sold",
                        field: "PMT Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#ffc107;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Shop Sold",
                        field: "Shopping Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#4285f4;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "SERP Sold",
                        field: "SERP Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="sold" style="cursor:pointer;color:${getMetricDotColor(channel, 'ad_sold')};font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            return `<strong style="color:#6f42c1;">${parseNumber(cell.getValue()).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "ACOS",
                        field: "ACOS",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'acos');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="acos" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right acos-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle ACOS Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('acos-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleAcosBreakdownColumns();
                            }
                        },
                        bottomCalc: function(values, data) {
                            let totalSpend = 0,
                                totalAdSales = 0;
                            data.forEach(row => {
                                totalSpend += parseNumber(row['Total Ad Spend'] || 0);
                                totalAdSales += parseNumber(row['Ad Sales'] || 0);
                            });
                            return totalAdSales > 0 ? (totalSpend / totalAdSales) * 100 : 0;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>${value.toFixed(1)}%</strong>`;
                        }
                    },
                    // Hidden ACOS Breakdown Columns
                    {
                        title: "KW ACOS",
                        field: "KW ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PT ACOS",
                        field: "PT ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "HL ACOS",
                        field: "HL ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PMT ACOS",
                        field: "PMT ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "Shop ACOS",
                        field: "Shopping ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "SERP ACOS",
                        field: "SERP ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="acos" style="cursor:pointer;color:${getMetricDotColor(channel, 'acos')};font-size:8px;" title="View ACOS Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "AD CVR",
                        field: "Ads CVR",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const dotColor = getMetricDotColor(channel, 'ads_cvr');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ads_cvr" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right cvr-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle CVR Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                            if (e.target.classList.contains('cvr-breakdown-toggle')) {
                                e.stopPropagation();
                                toggleCvrBreakdownColumns();
                            }
                        },
                        bottomCalc: function(values, data, calcParams) {
                            let totalAdSold = 0;
                            let totalClicks = 0;
                            data.forEach(row => {
                                totalAdSold += parseNumber(row.ad_sold || 0);
                                totalClicks += parseNumber(row.clicks || 0);
                            });
                            return totalClicks > 0 ? ((totalAdSold / totalClicks) * 100) : 0;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>${value.toFixed(1)}%</strong>`;
                        }
                    },
                    // Hidden CVR Breakdown Columns
                    {
                        title: "KW CVR",
                        field: "KW CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#198754;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PT CVR",
                        field: "PT CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#0d6efd;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "HL CVR",
                        field: "HL CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#dc3545;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "PMT CVR",
                        field: "PMT CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#ffc107;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "Shop CVR",
                        field: "Shopping CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#4285f4;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "SERP CVR",
                        field: "SERP CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = asPercent(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="cvr" style="cursor:pointer;color:${getMetricDotColor(channel, 'ads_cvr')};font-size:8px;" title="View CVR Chart"></i>`;
                            return `<span style="font-weight:600;color:#6f42c1;">${value.toFixed(1)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('ad-chart-icon')) {
                                e.stopPropagation();
                                showAdBreakdownChart($(e.target).data('channel'), $(e.target).data(
                                    'adtype'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "Shipping Health",
                        field: "Shipping Health",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        visible: false,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v == null || v === '' || v === '-') return '-';
                            return typeof v === 'number' ? (v + '%') : v;
                        }
                    },
                    {
                        title: "CC Health",
                        field: "CC Health",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v == null || v === '' || v === '-') return '-';
                            return typeof v === 'number' ? (v + '%') : v;
                        }
                    },
                    {
                        title: "Returns %",
                        field: "Returns %",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        visible: false,
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            if (v == null || isNaN(v) || v === 0) return '-';
                            return v.toFixed(1) + '%';
                        }
                    },
                    {
                        title: "A2Z Claims",
                        field: "A2Z Claims",
                        hozAlign: "center",
                        sorter: "number",
                        width: 95,
                        visible: false,
                        formatter: function(cell) {
                            const v = parseNumber(cell.getValue());
                            if (v == null || isNaN(v)) return '-';
                            return v.toLocaleString('en-US');
                        }
                    },
                    {
                        title: "Reviews",
                        field: "Ratings & Reviews",
                        hozAlign: "center",
                        sorter: "number",
                        width: 130,
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            let avg = parseNumber(rowData['Avg Rating'] || 0);
                            let total = parseNumber(rowData['Total Reviews'] || 0);
                            if ((!total || total <= 0) && rowData['Reviews'] && typeof rowData['Reviews'] === 'object') {
                                avg = parseNumber(rowData['Reviews']['Avg Rating'] || rowData['Reviews']['avg_rating'] || 0);
                                total = parseNumber(rowData['Reviews']['Total Reviews'] || rowData['Reviews']['total_reviews'] || 0);
                            }
                            if ((avg == null || isNaN(avg)) && (total == null || isNaN(total) || total === 0)) return '-';
                            const r = (!isNaN(avg) && avg > 0) ? avg.toFixed(1) + ' ★' : '';
                            const rev = (!isNaN(total) && total > 0) ? total.toLocaleString('en-US') : '';
                            return [r, rev].filter(Boolean).join(' | ') || '-';
                        }
                    },
                    {
                        title: "Action",
                        field: "_action",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            return `
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-primary edit-channel-btn" 
                                            data-channel='${JSON.stringify(rowData)}' 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();

                            const $target = $(e.target);

                            // Handle Edit button
                            if ($target.hasClass('edit-channel-btn') || $target.closest(
                                    '.edit-channel-btn').length) {
                                const $btn = $target.hasClass('edit-channel-btn') ? $target :
                                    $target.closest('.edit-channel-btn');
                                const rowDataStr = $btn.attr('data-channel');

                                console.log('Edit button clicked in cellClick');

                                if (typeof bootstrap === 'undefined') {
                                    alert('Bootstrap is not loaded. Please refresh the page.');
                                    return;
                                }

                                try {
                                    const rowData = JSON.parse(rowDataStr);

                                    const channel = rowData['Channel '] || rowData['Channel'] || '';
                                    const sheetUrl = rowData['sheet_link'] || '';
                                    const type = rowData['type'] || '';
                                    const missingLink = rowData['missing_link'] || '';
                                    const logo = rowData['logo'] || '';
                                    const sellerLink = rowData['seller_link'] || '';

                                    // Populate modal
                                    $('#editChannelName').val(channel);
                                    $('#editChannelAlias').val(rowData['alias'] || '');
                                    $('#editChannelPromotions').val(rowData['promotions'] ?? '');
                                    $('#editChannelComplianceCount').val(rowData['compliance_count'] ?? '');
                                    $('#editChannelUrl').val(sheetUrl);
                                    $('#editType').val(type);
                                    $('#editMissingLink').val(missingLink);
                                    $('#editChannelSellerLink').val(sellerLink);
                                    $('#originalChannel').val(channel);

                                    // Reset file input + show current logo (if any)
                                    $('#editChannelLogo').val('');
                                    $('#editExistingLogo').val(logo);
                                    if (logo) {
                                        $('#editChannelLogoPreview').html(
                                            `<img src="/storage/${logo}" alt="${channel}"/>`);
                                    } else {
                                        $('#editChannelLogoPreview').html(
                                            '<span class="placeholder-text">No logo</span>');
                                    }

                                    // Open modal
                                    const modalElement = document.getElementById(
                                    'editChannelModal');
                                    if (modalElement) {
                                        const modal = new bootstrap.Modal(modalElement);
                                        modal.show();
                                    }
                                } catch (error) {
                                    console.error('Error:', error);
                                }
                                return;
                            }
                        }
                    },
                    {
                        title: "Total PFT",
                        field: "Total PFT",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            // Calculate Total PFT from L30 Sales × NPFT%
                            const rowData = cell.getRow().getData();
                            const l30Sales = parseNumber(rowData['L30 Sales'] || 0);
                            const npftPercent = parseNumber(rowData['N PFT'] || 0);
                            const totalPft = (l30Sales * npftPercent) / 100;
                            return `<span>$${totalPft.toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "COGS",
                        field: "cogs",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            return `<span>$${value.toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "Sheet",
                        field: "sheet_link",
                        hozAlign: "center",
                        visible: true,
                        formatter: function(cell) {
                            const link = cell.getValue();
                            if (!link) return '-';
                            return `<a href="${link}" target="_blank" class="btn btn-sm btn-success">🔗</a>`;
                        }
                    },
                    {
                        // Manual Compliance Count entered per channel in the Edit modal.
                        title: "Compliance Count",
                        field: "compliance_count",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
                        headerTooltip: "Manual compliance count — set in the channel's Edit modal.",
                        formatter: function(cell) {
                            const raw = cell.getValue();
                            if (raw === null || raw === undefined || raw === '' || isNaN(parseInt(raw))) {
                                return '<span style="color:#adb5bd;">-</span>';
                            }
                            return `<span style="font-weight:600;">${parseInt(raw).toLocaleString('en-US')}</span>`;
                        }
                    },
                ]
            });

            $(window).on('resize.ammFreezeHeader', function() {
                const wrap = document.getElementById('marketplace-table-wrapper');
                if (wrap) wrap.style.height = marketplaceTableHeight() + 'px';
                if (table && typeof table.redraw === 'function') table.redraw(true);
            });

            function loadMetricDotTrends(tableData) {
                var channelKeys = channelKeysFromTableData(tableData);
                if (channelKeys.length === 0) return;

                function finish(channels) {
                    if (channels) applyDotTrendsMap(channels);
                    for (var c = 0; c < channelKeys.length; c++) {
                        for (var m = 0; m < metricDotMetricKeys.length; m++) {
                            var key = channelKeys[c] + '_' + metricDotMetricKeys[m];
                            if (lastDotColorByKey[key] === undefined) lastDotColorByKey[key] = DEFAULT_DOT_GRAY;
                        }
                    }
                    saveDotColorsToStorage();
                    paintMetricDots(channelKeys);
                }

                if (dotTrendsPrefetch && typeof dotTrendsPrefetch.then === 'function') {
                    dotTrendsPrefetch.done(function(response) {
                        finish(response && response.success ? response.channels : null);
                    }).fail(function() {
                        finish(null);
                    });
                    return;
                }

                finish(null);
            }

            function colorSummaryBadgeDots(channelKeys) {
                var inverted = invertedDotMetrics;
                var sumMetrics = {
                    l30_sales: 1, y_sales: 1, l30_orders: 1, qty: 1, ad_spend: 1, pft: 1,
                    clicks: 1, ad_sales: 1, ad_sold: 1, total_views: 1, inv_at_lp: 1,
                    inv_at_sp: 1, inventory: 1, missing_l: 1, map: 1, nmap: 1,
                    reviews: 1, l60_sales: 1, l60_orders: 1
                };
                var weightBy = {
                    gprofit: 'l30_sales', npft: 'l30_sales', ads_pct: 'l30_sales',
                    groi: 'l30_sales', nroi: 'l30_sales',
                    cvr: 'total_views', ads_cvr: 'clicks', acos: 'ad_sales'
                };
                function pairClass(v1, v2, metric) {
                    if (v1 == null || v2 == null || isNaN(v1) || isNaN(v2)) return 'none';
                    if (v2 === v1) return 'flat';
                    var isInv = inverted.indexOf(metric) >= 0;
                    if (isInv) return v2 < v1 ? 'up' : 'down';
                    return v2 > v1 ? 'up' : 'down';
                }
                $('#summary-stats .summary-trend-dot[data-metric]').each(function() {
                    var metric = $(this).attr('data-metric');
                    if (!metric) return;
                    // Prefer the blended All pair (same last two days as the All chart)
                    // so the outer badge cannot be green while the last graph point is red.
                    var allColor = lastDotColorByKey['all_' + metric];
                    if (allColor) {
                        var allCls = allColor === '#28a745' ? 'up'
                            : allColor === '#dc3545' ? 'down' : 'flat';
                        $(this).removeClass('up down flat none').addClass(allCls);
                        return;
                    }
                    var s1 = 0, s2 = 0, w1 = 0, w2 = 0, n = 0;
                    var weightKey = weightBy[metric] || (sumMetrics[metric] ? metric : null);
                    if (weightKey) {
                        (channelKeys || []).forEach(function(ch) {
                            var p = lastDotPairByKey[ch + '_' + metric];
                            if (!p || p[0] == null || p[1] == null || isNaN(p[0]) || isNaN(p[1])) return;
                            var wt = lastDotPairByKey[ch + '_' + weightKey];
                            var a = 1, b = 1;
                            if (weightKey !== metric && wt) {
                                a = (wt[0] != null && !isNaN(wt[0])) ? wt[0] : 0;
                                b = (wt[1] != null && !isNaN(wt[1])) ? wt[1] : 0;
                            }
                            s1 += p[0] * a;
                            s2 += p[1] * b;
                            w1 += a;
                            w2 += b;
                            n++;
                        });
                        if (n > 0) {
                            var v1 = weightKey !== metric ? (w1 > 0 ? s1 / w1 : null) : s1;
                            var v2 = weightKey !== metric ? (w2 > 0 ? s2 / w2 : null) : s2;
                            $(this).removeClass('up down flat none').addClass(pairClass(v1, v2, metric));
                            return;
                        }
                    }
                    var up = 0, down = 0;
                    (channelKeys || []).forEach(function(ch) {
                        var c = lastDotColorByKey[ch + '_' + metric];
                        if (c === '#28a745') up++;
                        else if (c === '#dc3545') down++;
                    });
                    var cls = 'none';
                    if (up > down) cls = 'up';
                    else if (down > up) cls = 'down';
                    else if (up > 0 || down > 0) cls = 'flat';
                    $(this).removeClass('up down flat none').addClass(cls);
                });
            }

            // Update summary statistics
            function updateSummaryStats(data) {
                let totalChannels = data.length;
                let totalL30Sales = 0;
                let totalYSales = 0;
                let totalL30Orders = 0;
                let totalQty = 0;
                let totalClicks = 0;
                let totalPft = 0;
                let totalCogs = 0;
                let totalAdSpend = 0;
                let totalViews = 0;
                let totalMap = 0;
                let totalMiss = 0;
                let totalNMap = 0;
                let gprofitSum = 0;
                let groiSum = 0;
                let npftSum = 0;
                let nroiSum = 0;
                let validChannels = 0;

                data.forEach(row => {
                    const channel = (row['Channel '] || '').trim().toLowerCase();
                    const l30Sales = parseNumber(row['L30 Sales'] || 0);
                    const ySales = parseNumber(row['Y Sales'] || 0);
                    const l30Orders = parseNumber(row['L30 Orders'] || 0);
                    const qty = parseNumber(row['Qty'] || 0);
                    const clicks = parseNumber(row['clicks'] || 0);
                    const gprofitPercent = parseNumber(row['Gprofit%'] || 0);
                    const groi = parseNumber(row['G Roi'] || 0);
                    const npft = parseNumber(row['N PFT'] || 0);
                    const nroi = parseNumber(row['N ROI'] || 0);
                    const cogs = parseNumber(row['cogs'] || 0);
                    const mapCount = parseNumber(row['Map'] || 0);
                    const missCount = parseNumber(row['Miss'] || 0);
                    const nmapCount = parseNumber(row['NMap'] || 0);

                    // Use Total Ad Spend directly (already computed correctly per channel).
                    // Reverb stores bump fees in Ads% when Total Ad Spend was historically 0.
                    let adSpend = parseNumber(row['Total Ad Spend'] || 0);
                    if (adSpend <= 0 && channel.indexOf('reverb') !== -1) {
                        const bumpPct = parseNumber(row['Ads%'] || row['TACOS %'] || row['TACOS'] || 0);
                        if (bumpPct > 0 && l30Sales > 0) {
                            adSpend = (bumpPct / 100) * l30Sales;
                        }
                    }
                    const views = parseNumber(row['Total Views'] || 0);

                    totalL30Sales += l30Sales;
                    totalYSales += ySales;
                    totalL30Orders += l30Orders;
                    totalQty += qty;
                    totalClicks += clicks;
                    totalAdSpend += adSpend;
                    totalViews += views;
                    totalCogs += cogs;
                    totalMap += mapCount;
                    totalMiss += missCount;
                    totalNMap += nmapCount;

                    // Calculate profit amount from percentage
                    const profitAmount = (gprofitPercent / 100) * l30Sales;
                    totalPft += profitAmount;

                    if (l30Sales > 0) {
                        gprofitSum += gprofitPercent;
                        groiSum += groi;
                        npftSum += npft;
                        nroiSum += nroi;
                        validChannels++;
                    }
                });

                // Calculate overall metrics (same as channel-masters)
                const avgGprofit = totalL30Sales > 0 ? (totalPft / totalL30Sales) * 100 : 0;
                const avgGroi = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

                // Calculate average TAcos % = Total Ad Spend / Total L30 Sales (same as channel-masters)
                const avgAdsPercent = totalL30Sales > 0 ? (totalAdSpend / totalL30Sales) * 100 : 0;

                // N PFT = G PFT - TAcos % (same as channel-masters)
                const avgNpft = avgGprofit - avgAdsPercent;

                // NROI% = (Net Profit / COGS) × 100 where Net Profit = Total PFT − Total Ad Spend
                // (same as before — do not cut Ads% from GROI%).
                const netProfit = totalPft - totalAdSpend;
                const avgNroi = totalCogs > 0 ? (netProfit / totalCogs) * 100 : 0;

                // Store the exact numeric value on the badge so chart scaling matches the
                // live table total (compact text like "326K" must not be parsed as 326).
                function setBadgeExact($inner, exactVal) {
                    const $badge = $inner.closest('.badge-chart-link');
                    if ($badge.length) {
                        if (exactVal === null || exactVal === undefined || isNaN(exactVal)) {
                            $badge.removeAttr('data-exact-value');
                        } else {
                            $badge.attr('data-exact-value', exactVal);
                        }
                    }
                }
                function toCompact(val) {
                    const v = Math.round(val);
                    if (Math.abs(v) >= 1000000) return (v / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
                    if (Math.abs(v) >= 1000) return Math.round(v / 1000) + 'K';
                    return String(v);
                }

                // Update badges
                $('#total-channels').text(totalChannels);
                (function() {
                    const val = Math.round(totalL30Sales);
                    const $el = $('#total-l30-sales');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'Sum of Sales column (channel rolling L30 / window varies). $' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                // Show NYS when no channel had any sales yesterday — clearer than "$0".
                (function() {
                    const $el = $('#total-y-sales');
                    if (totalYSales > 0) {
                        const val = Math.round(totalYSales);
                        $el.text('$' + val.toLocaleString('en-US'));
                        setBadgeExact($el, val);
                    } else {
                        $el.text('NYS');
                        setBadgeExact($el, null);
                    }
                })();
                (function() {
                    const val = Math.round(totalL30Orders);
                    const $el = $('#total-l30-orders');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalQty);
                    const $el = $('#total-qty');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                function pct1(n) {
                    return Math.round(n * 10) / 10;
                }
                (function() {
                    const val = pct1(avgGprofit);
                    const $el = $('#avg-gprofit');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                $('#total-gross-pft').text('$' + Math.round(totalPft).toLocaleString('en-US'));
                (function() {
                    const val = pct1(avgGroi);
                    const $el = $('#avg-groi');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalAdSpend);
                    const $el = $('#total-ad-spend');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'View trend - Total Ad Spend: $' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(avgAdsPercent * 10) / 10;
                    const $el = $('#ads-percent-badge');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalViews);
                    const $el = $('#total-views-badge');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'View trend - Total Views (listing/Map traffic): ' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                // Listing CVR: prefer each channel's listing CVR × views (same as the CVR column /
                // /temu-decrease / Shopify / Reverb badges). Fallback to Qty ÷ Views.
                // 2 decimals so the badge value shifts day-over-day.
                let cvrUnits = 0;
                let cvrViews = 0;
                data.forEach(row => {
                    const views = parseNumber(row['Total Views'] || 0);
                    if (views <= 0) return;
                    const serverCvr = row['CVR'];
                    if (serverCvr !== undefined && serverCvr !== null && serverCvr !== '') {
                        cvrUnits += (parseNumber(serverCvr) / 100) * views;
                        cvrViews += views;
                    } else {
                        cvrUnits += parseNumber(row['Qty'] || 0);
                        cvrViews += views;
                    }
                });
                const cvrPct = cvrViews > 0 ? (cvrUnits / cvrViews) * 100 : null;
                (function() {
                    const $el = $('#cvr-pct-badge');
                    if (cvrPct !== null) {
                        const val = Math.round(cvrPct * 100) / 100;
                        $el.text(val.toFixed(2) + '%');
                        setBadgeExact($el, val);
                    } else {
                        $el.text('-');
                        setBadgeExact($el, null);
                    }
                })();
                // NPFT $ = gross profit $ − total ad spend (= L30 × (G% − Ad Spend/Sales) in aggregate)
                (function() {
                    const val = Math.round(netProfit);
                    const $el = $('#total-pft');
                    $el.text(toCompact(val));
                    $el.closest('.badge').attr('title',
                        'Net profit $ = sum(rolling Sales×Gprofit% − Ad spend): $' + val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = pct1(avgNpft);
                    const $el = $('#avg-npft');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = pct1(avgNroi);
                    const $el = $('#avg-nroi');
                    $el.text(val.toFixed(1) + '%');
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalNMap);
                    const $el = $('#total-nmap');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();
                (function() {
                    const val = Math.round(totalMiss);
                    const $el = $('#total-miss');
                    $el.text(val.toLocaleString('en-US'));
                    setBadgeExact($el, val);
                })();

                // Reviews badge: weighted avg rating (sum(rating*reviews)/sum(reviews)), total reviews (sum)
                let ratingSum = 0, reviewsSum = 0;
                data.forEach(row => {
                    let r = parseNumber(row['Avg Rating'] || 0);
                    let rev = parseNumber(row['Total Reviews'] || 0);
                    if ((!rev || rev <= 0) && row['Reviews'] && typeof row['Reviews'] === 'object') {
                        r = parseNumber(row['Reviews']['Avg Rating'] || row['Reviews']['avg_rating'] || 0);
                        rev = parseNumber(row['Reviews']['Total Reviews'] || row['Reviews']['total_reviews'] || 0);
                    }
                    if (!isNaN(r) && !isNaN(rev) && rev > 0) { ratingSum += r * rev; reviewsSum += rev; }
                });
                const weightedAvgRating = reviewsSum > 0 ? (ratingSum / reviewsSum).toFixed(1) : '0.0';
                const totalReviews = Math.round(reviewsSum).toLocaleString('en-US');
                const $revBadge = $('#ratings-reviews-badge');
                $revBadge.text(weightedAvgRating + ' ★ | ' + totalReviews);
                setBadgeExact($revBadge, reviewsSum);
            }

            // Combine channel search and type (B2C/B2B/Dropship) filters
            function applyMasterFilters() {
                if (!table || typeof table.clearFilter !== 'function') return;
                const q = ($('#channel-search').val() || '').trim().toLowerCase();
                const typeVal = $('#type-filter').val() || 'all';
                const needsFilter = q.length > 0 || (typeVal && typeVal !== 'all');
                if (!needsFilter) {
                    table.clearFilter(true);
                    return;
                }
                table.clearFilter(true);
                table.addFilter(function(data) {
                    const ch = String(data['Channel '] || data['Channel'] || '').toLowerCase();
                    if (q && ch.indexOf(q) === -1) return false;
                    if (typeVal && typeVal !== 'all' && String(data['type'] || '') !== typeVal) return false;
                    return true;
                });
            }

            // Channel Search
            $('#channel-search').on('keyup', function() {
                applyMasterFilters();
            });

            // Type Filter
            $('#type-filter').on('change', function() {
                applyMasterFilters();
            });

            // ---- Persisted column visibility (channel_tabulator_column_settings) ----
            const COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
            // Per-user key so each user keeps their own saved column layout and
            // gets their latest selection back when they reopen the page.
            const COLUMN_VISIBILITY_CHANNEL = 'all_marketplace_master_user_{{ auth()->id() ?? 'guest' }}';
            const columnCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let savedColumnVisibility = {};

            function fetchColumnVisibility() {
                return fetch(`${COLUMN_VISIBILITY_URL}?channel=${encodeURIComponent(COLUMN_VISIBILITY_CHANNEL)}`, {
                        credentials: 'same-origin'
                    })
                    .then(r => r.json())
                    .then(map => {
                        savedColumnVisibility = (map && typeof map === 'object' && !Array.isArray(map)) ? map : {};
                        return savedColumnVisibility;
                    })
                    .catch(() => {
                        savedColumnVisibility = {};
                        return {};
                    });
            }

            // Fields that are permanently hidden in the UI but still drive calculations
            // (e.g. Growth uses D30 Sales; NP$ is derived from L30 Sales × N PFT).
            // PT / PMT / SERP / KW / HL breakdowns removed from page + Columns menu.
            const PERMANENTLY_HIDDEN_FIELDS = [
                'L-60 Sales', 'L60 Orders', 'NP$', 'D30 Sales',
                'PT Spent', 'PMT Spent', 'SERP Spent',
                'PT Clicks', 'PMT Clicks', 'SERP Clicks',
                'PT Sales', 'PMT Sales', 'SERP Sales',
                'PT Sold', 'PMT Sold', 'SERP Sold',
                'PT ACOS', 'PMT ACOS', 'SERP ACOS',
                'PT CVR', 'PMT CVR', 'SERP CVR',
                'KW Spent', 'HL Spent',
                'KW Clicks', 'HL Clicks',
                'KW Sales', 'HL Sales',
                'HL Sold',
                'KW ACOS', 'HL ACOS',
                'KW CVR', 'HL CVR',
                'A2Z Claims',
                'Missing Ads',
                'Map',
                'Miss',
                'NMap',
                'Growth',
                '_gross_pft',
                'Shopping Sales', 'Shopping ACOS', 'Shopping Sold', 'Shopping CVR',
                'ad_sold', 'Ads CVR', 'clicks', 'Ad Sales',
                'Shipping Health',
                'ACOS',
                'Total PFT',
                'Returns %',
                'Shopping Spent', 'Shopping Clicks',
                'compliance_count',
                'KW Sold',
                'CC Health',
                'cogs',
                'Seller Rating & Reviews',
                'alias',
            ];

            // Hidden from Columns menu only — still shown on the table.
            const COLUMNS_MENU_EXCLUDED_FIELDS = ['_action'];

            function applyColumnVisibility() {
                if (!table) return;
                if (savedColumnVisibility && Object.keys(savedColumnVisibility).length) {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;
                        if (savedColumnVisibility[def.field] === false) col.hide();
                        else if (savedColumnVisibility[def.field] === true) col.show();
                    });
                }
                const existingFields = {};
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field) existingFields[field] = col;
                });
                PERMANENTLY_HIDDEN_FIELDS.forEach(field => {
                    const c = existingFields[field];
                    if (c) c.hide();
                });
            }

            function saveColumnVisibility() {
                if (!table) return Promise.resolve();
                const visibility = {};
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field) visibility[def.field] = col.isVisible();
                });
                savedColumnVisibility = visibility;
                return fetch(COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': columnCsrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        channel: COLUMN_VISIBILITY_CHANNEL,
                        visibility: visibility,
                    }),
                }).then(r => {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json().catch(() => ({}));
                });
            }

            // Build Column Visibility Dropdown — lists every column with a field.
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-list");
                if (!menu) return;

                menu.innerHTML = '';

                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    const field = def.field;
                    if (!field) return;
                    if (PERMANENTLY_HIDDEN_FIELDS.indexOf(field) !== -1) return;
                    if (COLUMNS_MENU_EXCLUDED_FIELDS.indexOf(field) !== -1) return;

                    const isVisible = col.isVisible();
                    const li = document.createElement("li");
                    li.style.minWidth = '0';
                    li.innerHTML =
                        `<label class="dropdown-item py-1 px-2 text-truncate" style="white-space:nowrap;" title="${def.title}"><input type="checkbox" ${isVisible ? 'checked' : ''} data-field="${field}"> ${def.title}</label>`;
                    menu.appendChild(li);
                });
            }

            // Autosave column visibility whenever a checkbox is toggled.
            let columnVisibilitySaveTimer = null;
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type !== 'checkbox') return;
                const field = e.target.getAttribute('data-field');
                const col = table.getColumn(field);
                if (!col) return;
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                clearTimeout(columnVisibilitySaveTimer);
                columnVisibilitySaveTimer = setTimeout(function() {
                    saveColumnVisibility().catch(function() {
                        if (typeof showToast === 'function') {
                            showToast('error', 'Failed to save column settings.');
                        }
                    });
                }, 300);
            });

            // Table built event — load saved column visibility, apply it, then build the menu.
            table.on('tableBuilt', function() {
                fetchColumnVisibility().then(() => {
                    applyColumnVisibility();
                    buildColumnDropdown();
                });
            });

            // Table data loaded: rebuild dropdown; dot colors are loaded from ajaxResponse on first data load.
            table.on('dataLoaded', function() {
                setTimeout(function() {
                    buildColumnDropdown();
                    if (typeof table !== 'undefined' && table.redraw) {
                        table.redraw(true);
                    }
                }, 100);
                if (!dotTrendsLoadedOnce && table.getData && table.getData().length) {
                    dotTrendsLoadedOnce = true;
                    loadMetricDotTrends(table.getData());
                }
            });

            // Show clicks breakdown modal - Fetch data from adv_masters_datas table
            function showClicksBreakdown(channelName) {
                console.log('Opening clicks breakdown for:', channelName);

                // Set modal title
                $('#clicksModalChannelName').text(channelName);

                // Show loading state
                $('#clicksBreakdownTableBody').html(
                    '<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-info" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#clicksBreakdownTotalClicks').text('0');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('clicksBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('Clicks breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalClicks = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const clicks = parseInt(item.clicks) || 0;
                                totalClicks += clicks;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong>${clicks > 0 ? clicks.toLocaleString('en-US') : '0'}</strong></td>
                                    </tr>
                                `;
                            });

                            $('#clicksBreakdownTableBody').html(html);

                            // Update totals
                            $('#clicksBreakdownTotalClicks').text(totalClicks.toLocaleString('en-US'));
                        } else {
                            $('#clicksBreakdownTableBody').html(
                                '<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching clicks breakdown:', xhr);
                        $('#clicksBreakdownTableBody').html(
                            '<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Show Ad Sales Breakdown Modal (Ad Sales focused)
            function showAdSalesBreakdown(channelName) {
                console.log('Opening ad sales breakdown for:', channelName);

                // Set modal title
                $('#salesModalChannelName').text(channelName);

                // Show loading state
                $('#adSalesBreakdownTableBody').html(
                    '<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#adSalesBreakdownTotalSales').text('$0');
                $('#adSalesBreakdownTotalAcos').text('-');
                $('#adSalesBreakdownTotalTacos').text('-');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('adSalesBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('Ad sales breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalSpent = 0;
                            let totalAdSales = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const spent = parseFloat(item.spent) || 0;
                                const adSales = parseFloat(item.ad_sales) || 0;
                                const acos = parseFloat(item.acos) || 0;
                                const tacos = parseFloat(item.tacos) || 0;

                                totalSpent += spent;
                                totalAdSales += adSales;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong>$${adSales > 0 ? Math.round(adSales).toLocaleString('en-US') : '0'}</strong></td>
                                        <td class="text-end">${acos > 0 ? acos.toFixed(2) + '%' : '-'}</td>
                                        <td class="text-end">${tacos > 0 ? tacos.toFixed(2) + '%' : '-'}</td>
                                    </tr>
                                `;
                            });

                            $('#adSalesBreakdownTableBody').html(html);

                            // Calculate totals using formulas:
                            // ACOS = (spent / ad_sales) * 100
                            const totalAcos = totalAdSales > 0 ? ((totalSpent / totalAdSales) * 100)
                                .toFixed(2) : 0;
                            // TACOS = (spent / parent_l30_sales) * 100
                            const parentL30Sales = parseFloat(response.parent_l30_sales) || 0;
                            const totalTacos = parentL30Sales > 0 ? ((totalSpent / parentL30Sales) *
                                100).toFixed(2) : 0;

                            // Update totals
                            $('#adSalesBreakdownTotalSales').text('$' + Math.round(totalAdSales)
                                .toLocaleString('en-US'));
                            $('#adSalesBreakdownTotalAcos').text(totalAcos > 0 ? totalAcos + '%' : '-');
                            $('#adSalesBreakdownTotalTacos').text(totalTacos > 0 ? totalTacos + '%' :
                                '-');
                        } else {
                            $('#adSalesBreakdownTableBody').html(
                                '<tr><td colspan="4" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching ad sales breakdown:', xhr);
                        $('#adSalesBreakdownTableBody').html(
                            '<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Show CVR Breakdown Modal (CVR only)
            function showCvrBreakdown(channelName) {
                console.log('Opening CVR breakdown for:', channelName);

                // Set modal title
                $('#cvrModalChannelName').text(channelName);

                // Show loading state
                $('#cvrBreakdownTableBody').html(
                    '<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#cvrBreakdownTotalCvr').text('-');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('cvrBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('CVR breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalClicks = 0;
                            let totalAdSold = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const clicks = parseInt(item.clicks) || 0;
                                const adSold = parseInt(item.ad_sold) || 0;
                                const cvr = parseFloat(item.cvr) || 0;

                                totalClicks += clicks;
                                totalAdSold += adSold;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong>${cvr > 0 ? Math.round(cvr) + '%' : '-'}</strong></td>
                                    </tr>
                                `;
                            });

                            $('#cvrBreakdownTableBody').html(html);

                            // Calculate total CVR: (ads sold / clicks) * 100
                            const totalCvr = totalClicks > 0 ? Math.round((totalAdSold / totalClicks) *
                                100) : 0;

                            // Update totals
                            $('#cvrBreakdownTotalCvr').text(totalCvr > 0 ? totalCvr + '%' : '-');
                        } else {
                            $('#cvrBreakdownTableBody').html(
                                '<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching CVR breakdown:', xhr);
                        $('#cvrBreakdownTableBody').html(
                            '<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Show Missing Ads Breakdown Modal (Missing Ads only)
            function showMissingAdsBreakdown(channelName) {
                console.log('Opening missing ads breakdown for:', channelName);

                // Set modal title
                $('#missingModalChannelName').text(channelName);

                // Show loading state
                $('#missingAdsBreakdownTableBody').html(
                    '<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-danger" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>'
                    );

                // Reset totals
                $('#missingAdsBreakdownTotal').text('0');

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('missingAdsBreakdownModal'));
                modal.show();

                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: {
                        channel: channelName
                    },
                    success: function(response) {
                        console.log('Missing ads breakdown response:', response);

                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalMissingAds = 0;

                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = {
                                    'PT': 1,
                                    'KW': 2,
                                    'HL': 3
                                };
                                return (orderMap[a.type] || 4) - (orderMap[b.type] || 4);
                            });

                            response.data.forEach(item => {
                                const missingAds = parseInt(item.missing_ads) || 0;
                                totalMissingAds += missingAds;

                                html += `
                                    <tr>
                                        <td><strong>${item.type}</strong> <small class="text-muted">(${item.channel})</small></td>
                                        <td class="text-end"><strong style="color:#dc3545;">${missingAds > 0 ? missingAds.toLocaleString('en-US') : '0'}</strong></td>
                                    </tr>
                                `;
                            });

                            $('#missingAdsBreakdownTableBody').html(html);

                            // Update totals
                            $('#missingAdsBreakdownTotal').text(totalMissingAds.toLocaleString(
                            'en-US'));
                        } else {
                            $('#missingAdsBreakdownTableBody').html(
                                '<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>'
                                );
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching missing ads breakdown:', xhr);
                        $('#missingAdsBreakdownTableBody').html(
                            '<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Ad Breakdown Chart variables
            let adBreakdownChartInstance = null;
            let salesOrdersItemsBarChartInstance = null;
            let currentChartChannel = '';
            let currentChartAdType = '';
            let currentChartMetric = 'spend';
            // Trend chart default range — fixed at 30 days (matches the modal dropdown's pre-selected option).
            let currentChartDays = 30;
            let adChartAjax = null; // track in-flight request
            let currentChartMode = 'ad'; // 'ad' = ad breakdown, 'metric' = channel metric
            let currentMetricKey = ''; // metric key for channel metric mode

            // Channels that have daily data
            const channelsWithDailyData = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'shopifyb2c', 'temu', 'temu2', 'topdawg', 'walmart'];
            const adTypesForChannel = {
                'amazon': ['kw', 'pt', 'hl'],
                'amazonfba': ['kw', 'pt'],
                'ebay': ['kw', 'pmt'],
                'ebaytwo': ['kw', 'pmt'],
                'ebaythree': ['kw', 'pmt'],
                'shopifyb2c': ['shopping', 'serp'],
                'temu': ['kw'],
                'temu2': ['kw'],
                'topdawg': ['kw'],
                'walmart': ['kw']
            };

            // Date helper — YYYY-MM-DD in local time
            const adChartFmtDate = (d) => {
                return d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
            };

            // Compute start/end from days selection
            function adChartDateRange(days) {
                const today = new Date();
                const end = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 2);
                if (days === 0) {
                    // Lifetime — send no start_date so backend returns all available data
                    return { start: null, end: adChartFmtDate(end) };
                }
                const start = new Date(end.getFullYear(), end.getMonth(), end.getDate() - days + 1);
                return { start: adChartFmtDate(start), end: adChartFmtDate(end) };
            }

            // Range label for modal title
            function adChartRangeLabel(days) {
                if (days === 0) return 'Lifetime';
                return 'L' + days;
            }

            // Show Ad Breakdown Chart Modal
            function showAdBreakdownChart(channel, adType, metricType = 'spend') {
                currentChartMode = 'ad';
                currentChartChannel = snapshotChannelKey(channel);
                currentChartAdType = adType.toLowerCase();
                currentChartMetric = metricType;
                currentChartDays = 30; // ad reports: default rolling 30

                const hasData = channelsWithDailyData.includes(currentChartChannel) &&
                    (adTypesForChannel[currentChartChannel] || []).includes(currentChartAdType);

                $('#adChartRangeSelect').val('30');

                // Set modal title
                const adTypeLabel = currentChartAdType.toUpperCase();
                let metricLabel;
                if (metricType === 'acos') metricLabel = 'ACOS %';
                else if (metricType === 'cvr') metricLabel = 'CVR';
                else metricLabel = metricType.charAt(0).toUpperCase() + metricType.slice(1);
                $('#adChartModalTitle').text(`${channel} - ${adTypeLabel} ${metricLabel} (Rolling ${adChartRangeLabel(currentChartDays)})`);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('adBreakdownChartModal'));
                modal.show();

                if (!hasData) {
                    $('#adBreakdownChartContainer').hide();
                    $('#adChartLoading').hide();
                    $('#adChartNoData').show();
                    return;
                }

                loadAdBreakdownChart();
            }

            // Load chart data (handles both ad-breakdown and channel-metric modes)
            function loadAdBreakdownChart() {
                // Abort any previous in-flight request
                if (adChartAjax) adChartAjax.abort();

                $('#adChartNoData').hide();
                $('#adBreakdownChartContainer').hide();
                $('#adChartLoading').show();

                let url, params;

                if (currentChartMode === 'metric') {
                    // Channel metric mode — uses ChannelMasterSummary snapshots
                    url = '/channel-metric-chart-data';
                    params = {
                        channel: currentChartChannel,
                        metric: currentMetricKey,
                        days: currentChartDays
                    };
                    if (currentCellValue !== null) {
                        params.badge_value = currentCellValue;
                    }
                } else {
                    // Ad breakdown mode — uses daily ad campaign reports
                    url = '/ad-breakdown-chart-data';
                    const range = adChartDateRange(currentChartDays);
                    params = {
                        channel: currentChartChannel,
                        ad_type: currentChartAdType,
                        metric: currentChartMetric,
                        end_date: range.end
                    };
                    if (range.start) params.start_date = range.start;
                }

                adChartAjax = $.ajax({
                    url: url,
                    method: 'GET',
                    data: params,
                    success: function(response) {
                        adChartAjax = null;
                        $('#adChartLoading').hide();

                        if (response.success && response.data && response.data.length > 0) {
                            $('#adBreakdownChartContainer').show();
                            renderAdBreakdownChart(response.data);
                            if (currentChartMode === 'metric') {
                                loadSalesOrdersItemsBarChart();
                            } else {
                                $('#salesOrdersItemsBarContainer').hide();
                                if (salesOrdersItemsBarChartInstance) {
                                    salesOrdersItemsBarChartInstance.destroy();
                                    salesOrdersItemsBarChartInstance = null;
                                }
                            }
                        } else {
                            $('#adChartNoData').show();
                            $('#salesOrdersItemsBarContainer').hide();
                            if (salesOrdersItemsBarChartInstance) {
                                salesOrdersItemsBarChartInstance.destroy();
                                salesOrdersItemsBarChartInstance = null;
                            }
                        }
                    },
                    error: function(xhr, status) {
                        adChartAjax = null;
                        if (status === 'abort') return;
                        console.error('Error fetching chart data:', xhr);
                        $('#adChartLoading').hide();
                        $('#adChartNoData').show();
                        $('#salesOrdersItemsBarContainer').hide();
                        if (salesOrdersItemsBarChartInstance) {
                            salesOrdersItemsBarChartInstance.destroy();
                            salesOrdersItemsBarChartInstance = null;
                        }
                    }
                });
            }

            // Metric label map for titles
            const metricLabels = {
                'l60_sales': 'L60 Sales',
                'l60_orders': 'L60 Orders',
                'l30_sales': 'Sales',
                'y_sales': 'Y Sales',
                'l30_orders': 'Orders',
                'qty': 'Qty',
                'gprofit': 'Gprofit%',
                'groi': 'G ROI%',
                'ads_pct': 'TAcos %',
                'pft': 'Total Pft',
                'npft': 'N PFT%',
                'nroi': 'N ROI%',
                'missing_l': 'Missing L',
                'map': 'Map',
                'nmap': 'N Map',
                'ad_spend': 'Spend',
                'clicks': 'AD Clicks',
                'ad_sales': 'AD Sales',
                'ad_sold': 'AD Sold',
                'acos': 'ACOS',
                'ads_cvr': 'AD CVR',
                'cvr': 'CVR',
                'total_views': 'views',
                'inv_at_lp': 'Inv@LP',
                'inv_at_sp': 'Inv@SP',
                'inventory': 'inv',
                'tat': 'TAT',
                'reviews': 'Reviews',
            };

            // Show metric chart (for non-ad-breakdown columns)
            var currentCellValue = null;
            function showMetricChart(channel, metricKey, cellValue) {
                currentChartMode = 'metric';
                currentChartChannel = snapshotChannelKey(channel);
                currentMetricKey = metricKey;
                currentChartMetric = metricKey; // for fmtVal formatting
                // Trend chart default range — always opens at 30 days regardless of any other window.
                currentChartDays = 30;
                currentCellValue = (cellValue !== undefined && cellValue !== null && !isNaN(cellValue)) ? cellValue : null;

                $('#adChartRangeSelect').val('30');

                // Set title
                const label = metricLabels[metricKey] || metricKey;
                $('#adChartModalTitle').text(`${channel} - ${label} (Rolling ${adChartRangeLabel(currentChartDays)})`);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('adBreakdownChartModal'));
                modal.show();

                loadAdBreakdownChart();
            }

            // Range dropdown change handler
            $(document).on('change', '#adChartRangeSelect', function() {
                const days = parseInt($(this).val());
                if (days === currentChartDays) return;
                currentChartDays = days;

                // Update modal title range label
                const titleEl = $('#adChartModalTitle');
                titleEl.text(titleEl.text().replace(/\(Rolling [^)]+\)/, `(Rolling ${adChartRangeLabel(days)})`));

                loadAdBreakdownChart();
            });

            // Shein / Aliexpress / Faire: hover Miss|Map|NMap cell → trend chart; click → pricing page filter
            $(document).on('mouseenter', '#marketplace-table .ac-pricing-hover-cell', function() {
                const $el = $(this);
                const channel = $el.data('channel');
                const metric = $el.data('chart-metric');
                if (!channel || !metric) return;
                if (acPricingCellHoverTimer) clearTimeout(acPricingCellHoverTimer);
                const cv = parseFloat(String($el.text()).replace(/,/g, '')) || null;
                acPricingCellHoverTimer = setTimeout(function() {
                    showMetricChart(channel, metric, cv);
                }, 500);
            });
            $(document).on('mouseleave', '#marketplace-table .ac-pricing-hover-cell', function() {
                if (acPricingCellHoverTimer) {
                    clearTimeout(acPricingCellHoverTimer);
                    acPricingCellHoverTimer = null;
                }
            });
            $(document).on('mousedown', '#marketplace-table .ac-pricing-hover-cell', function() {
                if (acPricingCellHoverTimer) {
                    clearTimeout(acPricingCellHoverTimer);
                    acPricingCellHoverTimer = null;
                }
            });

            // Parse compact badge text (e.g. "326K", "1.7M", "$12,345", "46%") into a number.
            function parseBadgeDisplayValue(raw) {
                if (raw === undefined || raw === null) return null;
                let text = String(raw).replace(/[,$%\s]/g, '').trim();
                if (!text || text === 'NYS' || text === '-' || text === 'N/A') return null;
                const suffix = text.slice(-1).toUpperCase();
                let mult = 1;
                if (suffix === 'K') { mult = 1000; text = text.slice(0, -1); }
                else if (suffix === 'M') { mult = 1000000; text = text.slice(0, -1); }
                const n = parseFloat(text);
                return (isNaN(n) ? null : n * mult);
            }

            // Badge click handler — show overall (all channels) metric trend
            $(document).on('click', '.badge-chart-link', function() {
                const metricKey = $(this).data('metric');
                // Prefer data-exact-value (set when badges update) so compact "326K" is not
                // misread as 326. Fall back to parsing the visible text (supports K/M).
                let badgeValue = parseFloat($(this).attr('data-exact-value'));
                if (isNaN(badgeValue)) {
                    badgeValue = parseBadgeDisplayValue($(this).find('span[id]').first().text());
                }
                showMetricChart('All', metricKey, badgeValue);
            });

            // Per-metric daily bar chart was removed from the badge popup by request.
            // Stub kept so existing call sites in loadAdBreakdownChart() remain valid.
            function loadSalesOrdersItemsBarChart() { /* removed */ }

            // Render chart
            function renderAdBreakdownChart(data) {
                const ctx = document.getElementById('adBreakdownChart').getContext('2d');

                // Destroy existing chart
                if (adBreakdownChartInstance) {
                    adBreakdownChartInstance.destroy();
                }

                const labels = data.map(d => d.date);
                const values = data.map(d => d.value);

                // --- Compute stats ---
                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted = [...values].sort((a, b) => a - b);
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;

                // Headroom must fit the slanted last-point label. 10% of a tight
                // 7-day CVR range (~0.9pp) is only ~16px — the "5.09%" label
                // was clipped at the top/right of the plot.
                const range = dataMax - dataMin || 1;
                const yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
                const yMin = Math.max(0, dataMin - range * 0.12);
                const yMax = dataMax + yPad;

                // --- Format helper (no decimals for spend/sales) ---
                const fmtVal = (v) => {
                    const m = currentChartMetric;
                    if (m === 'spend' || m === 'sales' || m === 'l30_sales' || m === 'y_sales' || m === 'ad_spend' || m === 'ad_sales' || m === 'pft' || m === 'inv_at_lp' || m === 'inv_at_sp' || m === 'inventory') {
                        return '$' + Math.round(v).toLocaleString('en-US');
                    }
                    // Listing CVR / Ads CVR shift slowly inside a rolling window — show 2 decimals
                    // so adjacent days don't display as the same number (avoids a flat trend).
                    if (m === 'cvr' || m === 'ads_cvr') {
                        return v.toFixed(2) + '%';
                    }
                    if (m === 'acos' || m === 'gprofit' || m === 'groi' || m === 'ads_pct' || m === 'npft' || m === 'nroi') {
                        return v.toFixed(1) + '%';
                    }
                    if (m === 'tat') return v.toFixed(2);
                    return Math.round(v).toLocaleString('en-US');
                };

                // --- Dot colors: green=UP red=DOWN, but INVERTED for ACOS & TAcos % (lower is better) ---
                const invertedMetrics = ['acos', 'ads_pct'];
                const isInverted = invertedMetrics.includes(currentChartMetric);
                const dotColors = metricChartDotColors(values, isInverted);

                // Labels + High/Low use the same trend color as the dots.
                // Never paint every positive value red (that made "outer" red while
                // the graph dot was green).
                const labelColors = dotColors.slice();
                const refGray = '#6c757d';
                let maxIdx = 0, minIdx = 0;
                values.forEach((v, i) => {
                    if (v >= values[maxIdx]) maxIdx = i;
                    if (v <= values[minIdx]) minIdx = i;
                });
                const highestEl = document.getElementById('adChartHighest');
                const medianEl = document.getElementById('adChartMedian');
                const lowestEl = document.getElementById('adChartLowest');
                highestEl.textContent = fmtVal(dataMax);
                highestEl.style.color = dotColors[maxIdx] || refGray;
                if (highestEl.previousElementSibling) highestEl.previousElementSibling.style.color = highestEl.style.color;
                medianEl.textContent = fmtVal(median);
                medianEl.style.color = refGray;
                if (medianEl.previousElementSibling) medianEl.previousElementSibling.style.color = refGray;
                lowestEl.textContent = fmtVal(dataMin);
                lowestEl.style.color = dotColors[minIdx] || refGray;
                if (lowestEl.previousElementSibling) lowestEl.previousElementSibling.style.color = lowestEl.style.color;

                // --- Median line plugin ---
                const medianLinePlugin = {
                    id: 'medianLine',
                    afterDraw(chart) {
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
                    }
                };

                // afterDraw (not afterDatasetsDraw): Chart.js clips dataset draws
                // to the plot box, which hid the last day's label at the top/right.
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;
                        const lastIdx = meta.data.length - 1;
                        const anchors = [];

                        ctx.save();
                        ctx.font = 'bold 10px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'left';
                        ctx.textBaseline = 'middle';

                        meta.data.forEach((point, i) => {
                            const val = dataset.data[i];
                            let offsetY = (i % 2 === 0) ? -12 : -26;
                            if (i === lastIdx) {
                                offsetY = (lastIdx % 2 === 0) ? -26 : -12;
                            }
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
                    }
                };

                adBreakdownChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: currentChartMetric.charAt(0).toUpperCase() + currentChartMetric.slice(1),
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
                            pointBorderWidth: 1.5
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        clip: false,
                        layout: {
                            padding: { top: 44, left: 4, right: 22, bottom: 8 }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 },
                                bodyFont: { size: 10 },
                                padding: 6,
                                callbacks: {
                                    label: function(context) {
                                        const idx = context.dataIndex;
                                        let parts = [];
                                        parts.push('Value: ' + fmtVal(context.raw));
                                        if (idx > 0) {
                                            const diff = context.raw - values[idx - 1];
                                            const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                            parts.push('vs Yesterday: ' + arrow + ' ' + fmtVal(Math.abs(diff)));
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
                                ticks: {
                                    font: { size: 9 },
                                    callback: function(value) {
                                        return fmtVal(value);
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 60,
                                    minRotation: 60,
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31),
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });
            }

            // Edit channel button handler
            $(document).on('click', '.edit-channel-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                console.log('Edit button clicked');

                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded!');
                    alert('Bootstrap is not loaded. Please refresh the page.');
                    return;
                }

                try {
                    const rowData = JSON.parse($(this).attr('data-channel'));
                    console.log('Row data:', rowData);

                    const channel = rowData['Channel '] || rowData['Channel'] || '';
                    const sheetUrl = rowData['sheet_link'] || '';
                    const type = rowData['type'] || '';
                    const missingLink = rowData['missing_link'] || '';
                    const logo = rowData['logo'] || '';
                    const sellerLink = rowData['seller_link'] || '';
                    // Row exposes the value as 'Update' (see per-channel data builders).
                    // Coerce anything that isn't 'A' or 'S' (incl. 0, null, '0') to '' so
                    // the dropdown lands on its placeholder "Select" entry.
                    const rawUpdate = rowData['Update'];
                    const updateFlag = (rawUpdate === 'A' || rawUpdate === 'S') ? rawUpdate : '';

                    // Populate modal fields
                    $('#editChannelName').val(channel);
                    $('#editChannelAlias').val(rowData['alias'] || '');
                    $('#editChannelPromotions').val(rowData['promotions'] ?? '');
                    $('#editChannelComplianceCount').val(rowData['compliance_count'] ?? '');
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editMissingLink').val(missingLink);
                    $('#editChannelSellerLink').val(sellerLink);
                    $('#editChannelUpdate').val(updateFlag);
                    $('#originalChannel').val(channel);

                    // Reset file input + show current logo (if any)
                    $('#editChannelLogo').val('');
                    $('#editExistingLogo').val(logo);
                    if (logo) {
                        $('#editChannelLogoPreview').html(
                            `<img src="/storage/${logo}" alt="${channel}"/>`);
                    } else {
                        $('#editChannelLogoPreview').html(
                            '<span class="placeholder-text">No logo</span>');
                    }

                    // Show modal using Bootstrap 5 API
                    const modalElement = document.getElementById('editChannelModal');
                    if (!modalElement) {
                        console.error('editChannelModal element not found!');
                        return;
                    }

                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                    console.log('Modal opened');
                } catch (error) {
                    console.error('Error opening edit modal:', error);
                    showToast('error', 'Error opening edit form: ' + error.message);
                }
            });

            // Prevent ad spend select from triggering other events
            $(document).on('click', '.ad-spend-select', function(e) {
                e.stopPropagation();
            });

            // Reset select to total when clicking away
            $(document).on('change', '.ad-spend-select', function() {
                const $select = $(this);
                setTimeout(function() {
                    $select.val('total');
                }, 2000); // Reset after 2 seconds
            });

            // Live preview when picking a logo in the Add Channel modal
            $(document).on('change', '#channelLogo', function() {
                const file = this.files && this.files[0];
                const $preview = $('#channelLogoPreview');
                if (!file) {
                    $preview.html('<span class="placeholder-text">No logo</span>');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    $preview.html(`<img src="${e.target.result}" alt="logo preview"/>`);
                };
                reader.readAsDataURL(file);
            });

            // Live preview when picking a new logo in the Edit Channel modal
            $(document).on('change', '#editChannelLogo', function() {
                const file = this.files && this.files[0];
                const $preview = $('#editChannelLogoPreview');
                if (!file) {
                    // Restore the existing logo (if any) when file picker is cleared
                    const existing = $('#editExistingLogo').val();
                    if (existing) {
                        $preview.html(`<img src="/storage/${existing}" alt="logo"/>`);
                    } else {
                        $preview.html('<span class="placeholder-text">No logo</span>');
                    }
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    $preview.html(`<img src="${e.target.result}" alt="logo preview"/>`);
                };
                reader.readAsDataURL(file);
            });

            // Reset Add modal preview when the modal closes
            $(document).on('hidden.bs.modal', '#addChannelModal', function() {
                $('#channelLogoPreview').html('<span class="placeholder-text">No logo</span>');
            });

            // Save channel form handler
            $(document).on('click', '#saveChannelBtn', function() {
                const channelName = $('#channelName').val().trim();
                const channelUrl = $('#channelUrl').val().trim();
                const type = $('#type').val().trim();
                const sellerLink = $('#channelSellerLink').val().trim();
                const updateFlag = $('#channelUpdate').val();
                const logoFile = $('#channelLogo')[0].files[0];

                if (!channelName || !channelUrl || !type) {
                    showToast('error', 'All fields are required');
                    return;
                }

                const formData = new FormData();
                formData.append('channel', channelName);
                formData.append('alias', $('#channelAlias').val().trim());
                formData.append('promotions', $('#channelPromotions').val().trim());
                formData.append('compliance_count', $('#channelComplianceCount').val().trim());
                formData.append('sheet_link', channelUrl);
                formData.append('type', type);
                formData.append('seller_link', sellerLink);
                formData.append('update', updateFlag);
                if (logoFile) {
                    formData.append('logo', logoFile);
                }
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                $.ajax({
                    url: '/channel_master/store',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'addChannelModal'));
                            if (modal) modal.hide();
                            $('#channelForm')[0].reset();
                            $('#channelSellerLink').val('');
                            $('#channelUpdate').val('');
                            $('#channelLogoPreview').html('<span class="placeholder-text">No logo</span>');
                            table.setData(); // Reload data
                            showToast('success', 'Channel added successfully');
                        } else {
                            showToast('error', res.message || 'Failed to add channel');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Error submitting form';
                        showToast('error', msg);
                    }
                });
            });

            // Archive channel from Edit modal
            $(document).on('click', '#archiveChannelBtn', function() {
                const channel = ($('#originalChannel').val() || $('#editChannelName').val() || '').trim();
                if (!channel) {
                    showToast('error', 'Channel name is missing');
                    return;
                }
                if (!confirm(
                        `Are you sure you want to archive channel: ${channel}?\n\nThis will set the channel status to "Inactive" and it will no longer appear in the list.`
                    )) {
                    return;
                }
                const $btn = $(this);
                $btn.prop('disabled', true);
                $.ajax({
                    url: '/channel-archive',
                    method: 'POST',
                    data: {
                        channel: channel,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'editChannelModal'));
                            if (modal) modal.hide();
                            showToast('success', 'Channel archived successfully');
                            table.replaceData();
                        } else {
                            showToast('error', response.message || 'Failed to archive channel');
                        }
                    },
                    error: function(xhr) {
                        showToast('error',
                            'Error archiving channel: ' + (xhr.responseJSON?.message ||
                                'Unknown error'));
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Update channel form handler
            $(document).on('click', '#updateChannelBtn', function() {
                const channel = $('#editChannelName').val().trim();
                const alias = $('#editChannelAlias').val().trim();
                const promotions = $('#editChannelPromotions').val().trim();
                const complianceCount = $('#editChannelComplianceCount').val().trim();
                const sheetUrl = $('#editChannelUrl').val().trim();
                const type = $('#editType').val();
                const missingLink = $('#editMissingLink').val().trim();
                const originalChannel = $('#originalChannel').val().trim();
                const sellerLink = $('#editChannelSellerLink').val().trim();
                const updateFlag = $('#editChannelUpdate').val();
                const logoFile = $('#editChannelLogo')[0].files[0];

                if (!channel || !sheetUrl) {
                    showToast('error', 'Channel Name and Sheet URL are required');
                    return;
                }

                const formData = new FormData();
                formData.append('channel', channel);
                formData.append('alias', alias);
                formData.append('promotions', promotions);
                formData.append('compliance_count', complianceCount);
                formData.append('sheet_url', sheetUrl);
                formData.append('type', type);
                formData.append('missing_link', missingLink);
                formData.append('original_channel', originalChannel);
                formData.append('seller_link', sellerLink);
                formData.append('update', updateFlag);
                if (logoFile) {
                    formData.append('logo', logoFile);
                }
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

                $.ajax({
                    url: '/channel_master/update',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'editChannelModal'));
                            if (modal) modal.hide();
                            $('#editChannelForm')[0].reset();
                            $('#editChannelSellerLink').val('');
                            $('#editChannelUpdate').val('');
                            $('#editChannelLogoPreview').html('<span class="placeholder-text">No logo</span>');
                            $('#editExistingLogo').val('');
                            table.setData(); // Reload data
                            showToast('success', 'Channel updated successfully');
                        } else {
                            showToast('error', res.message || 'Update failed');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Error updating channel';
                        showToast('error', msg);
                    }
                });
            });

            function yMpEsc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            // Same GPFT/NPFT + GROI/NROI bands as the Active Channels table on this page.
            function yMpBandColor(value, kind) {
                const v = parseNumber(value);
                if (v == null || isNaN(v)) return null;
                if (kind === 'gpft' || kind === 'npft') {
                    if (v <= 10) return '#a00211';
                    if (v <= 18) return '#ffc107';
                    if (v <= 25) return '#3591dc';
                    if (v <= 40) return '#28a745';
                    return '#e83e8c';
                }
                if (v <= 50) return '#a00211';
                if (v <= 75) return '#ffc107';
                if (v <= 125) return '#28a745';
                return '#8000ff';
            }

            function yMpChartDot(channel, metric, value, color) {
                if (!channel || !metric) return '';
                const hex = color || DEFAULT_DOT_GRAY;
                const cv = (value != null && !isNaN(parseNumber(value))) ? parseNumber(value) : '';
                return `<i class="fas fa-circle ymp-chart-dot" data-channel="${yMpEsc(channel)}" data-metric="${yMpEsc(metric)}" data-value="${cv}" style="color:${hex};font-size:8px;" title="Hover for history graph"></i>`;
            }

            function yMpWrap(channel, metric, value, innerHtml, color) {
                if (!channel || !metric) return innerHtml;
                const cv = (value != null && !isNaN(parseNumber(value))) ? parseNumber(value) : '';
                return `<span class="ymp-cell ymp-hover-cell" data-channel="${yMpEsc(channel)}" data-metric="${yMpEsc(metric)}" data-value="${cv}">${innerHtml}${yMpChartDot(channel, metric, value, color)}</span>`;
            }

            function yMpPct(value, kind, channel, metric) {
                if (value === null || value === undefined || value === '') {
                    return '<span class="text-muted">—</span>';
                }
                const v = parseNumber(value);
                if (v == null || isNaN(v)) return '<span class="text-muted">—</span>';
                let style = 'font-weight:600;';
                if (kind === 'gpft' || kind === 'npft') {
                    if (v <= 10) style += 'color:#a00211;';
                    else if (v <= 18) style += 'background:#ffc107;color:#000;padding:2px 6px;border-radius:4px;';
                    else if (v <= 25) style += 'color:#3591dc;';
                    else if (v <= 40) style += 'color:#28a745;';
                    else style += 'color:#e83e8c;';
                } else {
                    if (v <= 50) style += 'color:#a00211;';
                    else if (v <= 75) style += 'background:#ffc107;color:#000;padding:2px 6px;border-radius:4px;';
                    else if (v <= 125) style += 'color:#28a745;';
                    else style += 'color:#8000ff;';
                }
                const color = yMpBandColor(v, kind);
                return yMpWrap(channel, metric, v, `<span style="${style}">${Math.round(v)}%</span>`, color);
            }

            function yMpMoney(value, channel, metric) {
                const v = parseNumber(value);
                if (!v) return '<span class="text-muted" title="No Yesterday Sales">NYS</span>';
                const color = (typeof getMetricDotColor === 'function' && channel && metric)
                    ? getMetricDotColor(channel, metric)
                    : '#0d6efd';
                return yMpWrap(channel, metric, v, `<span style="font-weight:600;color:#0d6efd;">$${Math.round(v).toLocaleString('en-US')}</span>`, color);
            }

            function yMpInt(value, channel, metric) {
                const v = parseNumber(value);
                if (v == null || isNaN(v) || v === 0) return '<span class="text-muted">—</span>';
                const color = (typeof getMetricDotColor === 'function' && channel && metric)
                    ? getMetricDotColor(channel, metric)
                    : DEFAULT_DOT_GRAY;
                return yMpWrap(channel, metric, v, Math.round(v).toLocaleString('en-US'), color);
            }

            function yMpCvr(row) {
                const serverCvr = row['CVR'];
                if (serverCvr !== undefined && serverCvr !== null && serverCvr !== '') {
                    return parseNumber(serverCvr);
                }
                const views = parseNumber(row['Total Views'] || 0);
                const qty = parseNumber(row['Qty'] || 0);
                return views > 0 ? (qty / views) * 100 : null;
            }

            function renderYesterdayMarketplaceRows(rows) {
                let sumSales = 0, sumPft = 0, sumCogs = 0, sumAd = 0, sumGpftSales = 0;
                let sumViews = 0, sumQty = 0, sumOrders = 0;

                const body = (rows || []).map(function(row) {
                    const name = (row.channel || '').toString().trim() || '—';
                    const ySales = parseNumber(row.sales || 0) || 0;
                    const gpft = row.gpft;
                    const groi = row.groi;
                    const nroi = row.nroi;
                    const npft = row.npft;
                    const views = row.views;
                    const orders = parseNumber(row.orders || 0) || 0;
                    const qty = parseNumber(row.qty || 0) || 0;
                    const cvr = row.cvr;

                    sumSales += ySales;
                    sumPft += parseNumber(row.pft || 0) || 0;
                    sumCogs += parseNumber(row.cogs || 0) || 0;
                    sumAd += parseNumber(row.ad_spend || 0) || 0;
                    sumGpftSales += parseNumber(row.gpft_sales || 0) || 0;
                    sumViews += parseNumber(views || 0) || 0;
                    sumQty += qty;
                    sumOrders += orders;

                    const cvrNum = parseNumber(cvr);
                    const cvrHtml = (cvr == null || cvr === '' || isNaN(cvrNum))
                        ? '<span class="text-muted">—</span>'
                        : yMpWrap(name, 'cvr', cvrNum, `<span style="font-weight:600;">${Math.round(cvrNum)}%</span>`, (typeof getMetricDotColor === 'function' ? getMetricDotColor(name, 'cvr') : DEFAULT_DOT_GRAY));

                    return `<tr>
                        <td class="text-start fw-semibold">${yMpEsc(name)}</td>
                        <td class="text-end">${yMpMoney(ySales, name, 'y_sales')}</td>
                        <td class="text-center">${yMpPct(gpft, 'gpft', name, 'gprofit')}</td>
                        <td class="text-center">${yMpPct(groi, 'groi', name, 'groi')}</td>
                        <td class="text-center">${yMpPct(nroi, 'nroi', name, 'nroi')}</td>
                        <td class="text-center">${yMpPct(npft, 'npft', name, 'npft')}</td>
                        <td class="text-end">${yMpInt(views, name, 'total_views')}</td>
                        <td class="text-center">${cvrHtml}</td>
                        <td class="text-end">${yMpInt(orders, name, 'l30_orders')}</td>
                    </tr>`;
                }).join('');

                const totGpft = sumGpftSales > 0 ? (sumPft / sumGpftSales) * 100 : null;
                const totGroi = sumCogs > 0 ? (sumPft / sumCogs) * 100 : null;
                const totNroi = sumCogs > 0 ? ((sumPft - sumAd) / sumCogs) * 100 : null;
                const totAds = sumGpftSales > 0 ? (sumAd / sumGpftSales) * 100 : 0;
                const totNpft = totGpft != null ? totGpft - totAds : null;
                const totCvr = sumViews > 0 ? (sumQty / sumViews) * 100 : null;
                const totCvrHtml = (totCvr == null || isNaN(totCvr))
                    ? '—'
                    : Math.round(totCvr) + '%';

                const totCvrCell = (totCvr == null || isNaN(totCvr))
                    ? '—'
                    : yMpWrap('All', 'cvr', totCvr, totCvrHtml, (typeof getMetricDotColor === 'function' ? getMetricDotColor('All', 'cvr') : DEFAULT_DOT_GRAY));

                $('#yesterdayMpTableBody').html(body || '<tr><td colspan="9" class="text-center text-muted">No channels</td></tr>');
                $('#yesterdayMpTableFoot').html(`<tr>
                    <td class="text-start">Total</td>
                    <td class="text-end">${yMpMoney(sumSales, 'All', 'y_sales')}</td>
                    <td class="text-center">${yMpPct(totGpft, 'gpft', 'All', 'gprofit')}</td>
                    <td class="text-center">${yMpPct(totGroi, 'groi', 'All', 'groi')}</td>
                    <td class="text-center">${yMpPct(totNroi, 'nroi', 'All', 'nroi')}</td>
                    <td class="text-center">${yMpPct(totNpft, 'npft', 'All', 'npft')}</td>
                    <td class="text-end">${yMpInt(sumViews, 'All', 'total_views')}</td>
                    <td class="text-center">${totCvrCell}</td>
                    <td class="text-end">${yMpInt(sumOrders, 'All', 'l30_orders')}</td>
                </tr>`);
            }

            let ympHoverTimer = null;
            function openYmpHistoryChart(channel, metric, value) {
                if (!channel || !metric || typeof showMetricChart !== 'function') return;
                const chartEl = document.getElementById('adBreakdownChartModal');
                const onShown = function() {
                    if (chartEl) chartEl.style.zIndex = '10050';
                    const backs = document.querySelectorAll('.modal-backdrop');
                    const last = backs[backs.length - 1];
                    if (last) last.style.zIndex = '10040';
                    if (chartEl) chartEl.removeEventListener('shown.bs.modal', onShown);
                };
                if (chartEl) chartEl.addEventListener('shown.bs.modal', onShown);
                const cv = (value !== undefined && value !== null && value !== '' && !isNaN(Number(value)))
                    ? Number(value)
                    : null;
                showMetricChart(channel, metric, cv);
            }

            $(document).on('mouseenter', '#yesterdayMpTable .ymp-hover-cell', function() {
                const chartEl = document.getElementById('adBreakdownChartModal');
                if (chartEl && chartEl.classList.contains('show')) return;
                const $el = $(this);
                const channel = $el.data('channel');
                const metric = $el.data('metric');
                const value = $el.data('value');
                if (!channel || !metric) return;
                if (ympHoverTimer) clearTimeout(ympHoverTimer);
                ympHoverTimer = setTimeout(function() {
                    openYmpHistoryChart(channel, metric, value);
                }, 500);
            });
            $(document).on('mouseleave', '#yesterdayMpTable .ymp-hover-cell', function() {
                if (ympHoverTimer) {
                    clearTimeout(ympHoverTimer);
                    ympHoverTimer = null;
                }
            });
            $(document).on('mousedown', '#yesterdayMpTable .ymp-hover-cell', function() {
                if (ympHoverTimer) {
                    clearTimeout(ympHoverTimer);
                    ympHoverTimer = null;
                }
            });
            $(document).on('click', '#yesterdayMpTable .ymp-hover-cell', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $el = $(this);
                openYmpHistoryChart($el.data('channel'), $el.data('metric'), $el.data('value'));
            });

            $('#adBreakdownChartModal').on('hidden.bs.modal', function() {
                const yEl = document.getElementById('yesterdayMpModal');
                if (yEl && yEl.classList.contains('show')) {
                    document.body.classList.add('modal-open');
                    if (!document.querySelector('.modal-backdrop')) {
                        const back = document.createElement('div');
                        back.className = 'modal-backdrop fade show';
                        back.style.zIndex = '9998';
                        document.body.appendChild(back);
                    }
                }
            });

            function openYesterdayMarketplaceModal() {
                const el = document.getElementById('yesterdayMpModal');
                if (typeof bootstrap === 'undefined' || !el) return;
                $('#yesterdayMpTableBody').html('<tr><td colspan="9" class="text-center text-muted py-3">Loading yesterday metrics…</td></tr>');
                $('#yesterdayMpTableFoot').empty();
                bootstrap.Modal.getOrCreateInstance(el).show();

                $.get('/yesterday-marketplace-metrics')
                    .done(function(res) {
                        const rows = (res && res.data && res.data.rows) ? res.data.rows : [];
                        renderYesterdayMarketplaceRows(rows);
                    })
                    .fail(function() {
                        $('#yesterdayMpTableBody').html('<tr><td colspan="9" class="text-center text-danger">Could not load yesterday metrics</td></tr>');
                    });
            }

            $(document).on('click', '#yesterdayMpViewBtn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openYesterdayMarketplaceModal();
            });
        });
    </script>
@endsection
