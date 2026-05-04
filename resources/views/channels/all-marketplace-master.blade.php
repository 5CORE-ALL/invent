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

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Root overflow:hidden breaks position:sticky on the header when the page scrolls */
        #marketplace-table.tabulator {
            overflow: visible;
        }

        /* Keep header visible under the fixed topbar while scrolling long tables */
        #marketplace-table.tabulator .tabulator-header {
            position: sticky;
            top: var(--tz-topbar-height, 70px);
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
                    <!-- Section Filter Dropdown -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-layer-group"></i> <span id="current-section">Main Section</span>
                        </button>
                        <ul class="dropdown-menu" id="section-dropdown">
                            <li><a class="dropdown-item section-option" href="#" data-section="all">
                                <i class="fas fa-th"></i> Main Section
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="ads">
                                <i class="fas fa-ad"></i> ADS
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="inv">
                                <i class="fas fa-boxes"></i> INVENTORY
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="margins">
                                <i class="fas fa-percentage"></i> MARGINS
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="movement">
                                <i class="fas fa-truck"></i> MOVEMENT
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="returns">
                                <i class="fas fa-undo"></i> RETURNS
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="ah">
                                <i class="fas fa-heartbeat"></i> ACCOUNT HEALTH
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="expenses">
                                <i class="fas fa-dollar-sign"></i> EXPENSES
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="traffic">
                                <i class="fas fa-chart-line"></i> TRAFFIC
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="reviews">
                                <i class="fas fa-star"></i> REVIEWS
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="missing">
                                <i class="fas fa-exclamation-triangle"></i> Missing
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="B2C">
                                <i class="fas fa-shopping-cart"></i> B2C
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="B2B">
                                <i class="fas fa-building"></i> B2B
                            </a></li>
                            <li><a class="dropdown-item section-option" href="#" data-section="Dropship">
                                <i class="fas fa-box"></i> Dropshipping
                            </a></li>
                        </ul>
                    </div>
                    
                    <!-- Search -->
                    <input type="text" id="channel-search" class="form-control form-control-sm"
                        placeholder="Search Channel..." style="width: 150px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;"
                        title="Filter by Qty items (L30 order quantity total)">
                        <option value="all" selected>Qty — All</option>
                        <option value="zero">Qty — 0</option>
                        <option value="more">Qty — &gt; 0</option>
                    </select>

                    <!-- Type Filter (hidden from UI) -->
                    <select id="type-filter" class="form-select form-select-sm" style="width: auto; display: none;">
                        <option value="all">All Types</option>
                        <option value="B2C">🛒 B2C</option>
                        <option value="B2B">🏢 B2B</option>
                        <option value="Dropship">📦 Dropship</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>

                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye"></i> Show All
                    </button>

                    <!-- Export Button -->
                    <button id="export-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-file-excel"></i> Export
                    </button>

                    <!-- Refresh Button -->
                    <button id="refresh-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>

                    <button id="addChannelBtn" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addChannelModal">
                        <i class="fas fa-plus-circle"></i> Add Channel
                    </button>

                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-1">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2 ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            Channels: <span id="total-channels">0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="l30_sales" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of Sales column. Amazon = last {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific (same window &amp; AMAZON_SALES_TOTAL_MODE as Amazon Daily Sales). Other channels vary.">
                            Sales: <span id="total-l30-sales">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="y_sales" style="background-color: #17a2b8; color: white; font-weight: bold; cursor:pointer;" title="Sum of Y Sales column (Yesterday's sales across all channels)">
                            Y Sales: <span id="total-y-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="l30_orders" style="color: black; font-weight: bold; cursor:pointer;" title="Sum of Orders column. Amazon = {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }}-day Pacific rolling (same as Amazon Daily Sales); other channels vary.">
                            Orders: <span id="total-l30-orders">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link d-none" data-metric="qty" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Qty items: <span id="total-qty">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="gprofit" style="color: black; font-weight: bold; cursor:pointer;" title="Blended Gprofit% = sum(Sales×G%) / sum(Sales) using each channel’s rolling Sales column; matches GPFT column footer">
                            GPFT: <span id="avg-gprofit">0%</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 d-none" style="color: black; font-weight: bold; border: 1px solid rgba(0,0,0,.25);" title="Gross profit $ = sum of (rolling Sales × Gprofit%) per channel; matches Gross PFT column (show column to verify)">
                            GPFT: <span id="total-gross-pft">$0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="groi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            G ROI: <span id="avg-groi">0%</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="ad_spend" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Spend: <span id="total-ad-spend">$0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="ads_pct" style="background-color: #6610f2; color: white; font-weight: bold; cursor:pointer;" title="Average TACOS % = Total Ad Spend / Total L30 Sales × 100">
                            TACOS: <span id="avg-ads-percent">0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="total_views" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            views: <span id="total-views-badge">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="cvr" style="color: white; font-weight: bold; cursor:pointer;" title="Listing CVR (all channels): (sum of L30 Orders) ÷ (sum of Total Views) × 100. Total Views = listing/Map traffic (e.g. ov_l30, eBay Views) — not ad clicks. Not the same as column &quot;AD CVR&quot; (ad sold ÷ ad clicks). The ratio can move sharply if views jump (new SKUs, sync) or order windows differ by channel (e.g. Amazon 32-day orders vs views from live tabulator).">
                            CVR: <span id="cvr-pct-badge">0%</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="pft" style="color: black; font-weight: bold; cursor:pointer;" title="Net profit $ = sum(rolling Sales×Gprofit% − Ad spend); same as Sales × (G% − Ad Spend/Sales) per channel">
                            NPFT: <span id="total-pft">$0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="npft" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            NPFT: <span id="avg-npft">0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="nroi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            NROI: <span id="avg-nroi">0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="clicks" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            Clicks: <span id="total-clicks">0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="map" style="background-color:#198754;color:#fff;font-weight:bold;cursor:pointer;" title="Sum of Map column (|INV − channel stock| within tolerance — same as pricing pages)">
                            Map: <span id="total-map">0</span>
                        </span>
                        <span class="badge fs-6 p-2 badge-chart-link" data-metric="nmap" style="background-color:#a71d2a;color:#fff;font-weight:bold;cursor:pointer;" title="Sum of N Map column (not mapped / INV vs channel stock beyond tolerance — same as pricing pages)">
                            N Map: <span id="total-nmap">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="missing_l" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Missing L : <span id="total-miss">0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of (Inventory × Amazon Price)">
                            inv: $<span id="inventory-value-amazon">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="inv_at_lp" style="color: black; font-weight: bold; cursor:pointer;" title="View trend - Sum of (Shopify inventory × LP)">
                            Inv@LP: $<span id="inv-at-lp">0</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="tat" style="color: white; font-weight: bold; cursor:pointer;" title="View trend - inv ÷ Sales (months of stock at current sales)">
                            TAT: <span id="tat-badge">0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;" title="Sum of ratings (weighted avg), average of reviews">
                            Reviews: <span id="ratings-reviews-badge">0 ★ | 0</span>
                        </span>
                        <span class="badge bg-dark fs-6 p-2" style="color: white; font-weight: bold;" title="Seller: sum of ratings (weighted avg), average of reviews">
                            Seller review: <span id="seller-ratings-reviews-badge">0 ★ | 0</span>
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
                    <form id="channelForm">
                        <div class="mb-3">
                            <label for="channelName" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="channelName" required>
                        </div>
                        <div class="mb-3">
                            <label for="channelUrl" class="form-label">Sheet Link</label>
                            <input type="url" class="form-control" id="channelUrl">
                        </div>
                        <div class="mb-3">
                            <label for="additionSheet" class="form-label">Addition Sheet</label>
                            <input type="url" class="form-control" id="additionSheet" placeholder="https://...">
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
                    <form id="editChannelForm">
                        <input type="hidden" id="originalChannel" name="original_channel">
                        <div class="mb-3">
                            <label for="editChannelName" class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="editChannelName" readonly>
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
                            <label for="editTarget" class="form-label">Target</label>
                            <input type="number" class="form-control" id="editTarget" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="editMissingLink" class="form-label">Blade page link</label>
                            <input type="url" class="form-control" id="editMissingLink" placeholder="https://...">
                            <small class="text-muted">This link will open when clicking channel name</small>
                        </div>
                        <div class="mb-3">
                            <label for="editAdditionSheet" class="form-label">Addition Sheet</label>
                            <input type="url" class="form-control" id="editAdditionSheet" placeholder="https://...">
                            <small class="text-muted">This link will open when clicking the Missing L column</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="updateChannelBtn">Update Channel</button>
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
                            <option value="30">30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="adBreakdownChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
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
                    <div id="salesOrdersItemsBarContainer" style="height: 20vh; margin-top: 8px; align-items: stretch; display: none;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="salesOrdersItemsBarChart"></canvas>
                        </div>
                        <div id="salesOrdersItemsBarRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 6px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center; font-size: 8px; font-weight: 700; color: #1e88e5;">Sales</div>
                            <div style="text-align: center; font-size: 8px; font-weight: 700; color: #ff9800;">Orders</div>
                            <div style="text-align: center; font-size: 8px; font-weight: 700; color: #00bcd4;">Qty</div>
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
        // Normalize percentage for display: backend may send 0.15 or 15; always show as 0-100 scale with %
        function asPercent(value) {
            const n = parseNumber(value);
            if (!n) return 0;
            if (n > 0 && n <= 1) return n * 100; // decimal form
            return n;
        }

        // Track main Ad columns visibility (from Total Ad Spend to Missing Ads)
        let adColumnsVisible = false;
        const mainAdColumnFields = ['Total Ad Spend', 'clicks', 'Ad Sales', 'ad_sold', 'ACOS', 'Ads CVR', 'Missing Ads'];

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
        const adSpendBreakdownFields = ['KW Spent', 'PT Spent', 'HL Spent', 'PMT Spent', 'Shopping Spent', 'SERP Spent'];

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
        const clicksBreakdownFields = ['KW Clicks', 'PT Clicks', 'HL Clicks', 'PMT Clicks', 'Shopping Clicks',
            'SERP Clicks'];

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
        const adSalesBreakdownFields = ['KW Sales', 'PT Sales', 'HL Sales', 'PMT Sales', 'Shopping Sales', 'SERP Sales'];

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
        const adSoldBreakdownFields = ['KW Sold', 'PT Sold', 'HL Sold', 'PMT Sold', 'Shopping Sold', 'SERP Sold'];

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
        const acosBreakdownFields = ['KW ACOS', 'PT ACOS', 'HL ACOS', 'PMT ACOS', 'Shopping ACOS', 'SERP ACOS'];

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
        const cvrBreakdownFields = ['KW CVR', 'PT CVR', 'HL CVR', 'PMT CVR', 'Shopping CVR', 'SERP CVR'];

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

        $(document).ready(function() {
            // Initialize Tabulator
            table = new Tabulator("#marketplace-table", {
                ajaxURL: "/channels-master-data",
                ajaxSorting: false,
                layout: "fitDataStretch",
                height: false,
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
                        // Update inv badge
                        const invValEl = document.getElementById('inventory-value-amazon');
                        if (invValEl && response.inventory_value_amazon != null) {
                            const val = parseFloat(response.inventory_value_amazon) || 0;
                            invValEl.textContent = Math.round(val).toLocaleString('en-US');
                        }
                        // Update Inv@LP badge (Shopify inv × LP)
                        const invAtLpEl = document.getElementById('inv-at-lp');
                        if (invAtLpEl && response.inv_at_lp != null) {
                            const val = parseFloat(response.inv_at_lp) || 0;
                            invAtLpEl.textContent = Math.round(val).toLocaleString('en-US');
                        }
                        // Update TAT badge (inv / Sales)
                        const tatEl = document.getElementById('tat-badge');
                        if (tatEl && response.inventory_value_amazon != null && response.data && response.data.length) {
                            const invVal = parseFloat(response.inventory_value_amazon) || 0;
                            let totalSales = 0;
                            response.data.forEach(function(row) {
                                const s = (row['L30 Sales'] || 0);
                                totalSales += (typeof parseNumber === 'function' ? parseNumber(s) : parseFloat(String(s).replace(/[^0-9.-]/g, ''))) || 0;
                            });
                            const tat = totalSales > 0 ? invVal / totalSales : 0;
                            tatEl.textContent = tat > 0 ? tat.toFixed(2) : '0';
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
                        title: "Channel",
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
                        title: "Missing L",
                        field: "Miss",
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

                            return `<span style="${style}">${value}</span>${chartIcon}`;
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
                            return `<span style="${style}">${value.toLocaleString('en-US')}</span>${chartIcon}`;
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
                        title: "N Map",
                        field: "NMap",
                        hozAlign: "center",
                        sorter: "number",
                        
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const dotColor = value === 0 ? '#28a745' : '#dc3545';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nmap" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;

                            const color = value === 0 ? 'green' : 'red';

                            return `<span style="color:${color};font-weight:bold;">${value}</span>${chartIcon}`;
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
                        title: "Sales",
                        field: "L30 Sales",
                        headerTooltip: "Rolling sales per channel. Amazon = last {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific — same formula as Amazon Daily Sales (AMAZON_SALES_TOTAL_MODE; Canceled/Cancelled excluded).",
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
                        title: "L60 Sales",
                        field: "L-60 Sales",
                        headerTooltip: "Sales from days 31-60 (previous 30-day period). Used for Growth calculation.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        visible: true,
                        formatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            return `<span style="font-weight: 600;">$${value.toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = Math.round(parseNumber(cell.getValue()));
                            return `<strong>$${value.toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Growth",
                        field: "Growth",
                        headerTooltip: "Growth percentage comparing L30 Sales to L60 Sales. Formula: ((L30 - L60) / L60) × 100. Green indicates positive growth, red indicates decline.",
                        hozAlign: "center",
                        sorter: "number",
                        width: 88,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            
                            // If backend provides Growth value, use it
                            if (value && value !== '0%' && value !== '0.00%') {
                                const growthStr = String(value).replace('%', '');
                                const growth = parseFloat(growthStr);
                                
                                if (isNaN(growth) || Math.abs(growth) < 0.1) {
                                    return '<span style="font-weight:600;color:#6c757d;">0%</span>';
                                }
                                
                                const isPositive = growth > 0;
                                const color = isPositive ? '#198754' : '#dc3545';
                                const arrow = isPositive ? '↑' : '↓';
                                
                                return `<span style="font-weight:600;color:${color};">${arrow} ${Math.abs(growth).toFixed(0)}%</span>`;
                            }
                            
                            // Fallback: calculate from L30 and L60
                            const rowData = cell.getRow().getData();
                            const l30 = parseNumber(rowData['L30 Sales'] || 0);
                            const l60 = parseNumber(rowData['L-60 Sales'] || 0);
                            
                            if (l60 === 0 || !l60) {
                                return '<span style="color:#adb5bd;">—</span>';
                            }
                            
                            const growth = ((l30 - l60) / l60) * 100;
                            
                            if (Math.abs(growth) < 0.1) {
                                return '<span style="font-weight:600;color:#6c757d;">0%</span>';
                            }
                            
                            const isPositive = growth > 0;
                            const color = isPositive ? '#198754' : '#dc3545';
                            const arrow = isPositive ? '↑' : '↓';
                            
                            return `<span style="font-weight:600;color:${color};">${arrow} ${Math.abs(growth).toFixed(0)}%</span>`;
                        },
                        bottomCalc: function(values, data) {
                            let sumL30 = 0;
                            let sumL60 = 0;
                            data.forEach(function(row) {
                                sumL30 += parseNumber(row['L30 Sales'] || 0);
                                sumL60 += parseNumber(row['L-60 Sales'] || 0);
                            });
                            if (sumL60 === 0) return null;
                            return ((sumL30 - sumL60) / sumL60) * 100;
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
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue() || 0);
                            if (!value || value === 0) {
                                return '<span style="color:#adb5bd;">—</span>';
                            }
                            return `<span style="font-weight:600;color:#0d6efd;">$${Math.round(value).toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>—</strong>';
                            return `<strong style="color:#0d6efd;">$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
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
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_spend" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
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
                        headerTooltip: "Per channel: L30 Orders ÷ Total Views. Total Views come from listing/Map snapshots (traffic to offers), not the same as ad clicks. Compare to &quot;AD CVR&quot; (ad sold ÷ clicks). Big view updates can lower this % without &quot;true&quot; conversion collapsing.",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const ordersA = parseNumber(aRow.getData()['L30 Orders'] || 0);
                            const viewsA = parseNumber(aRow.getData()['Total Views'] || 0);
                            const ordersB = parseNumber(bRow.getData()['L30 Orders'] || 0);
                            const viewsB = parseNumber(bRow.getData()['Total Views'] || 0);
                            const cvrA = viewsA > 0 ? (ordersA / viewsA) * 100 : 0;
                            const cvrB = viewsB > 0 ? (ordersB / viewsB) * 100 : 0;
                            return cvrA - cvrB;
                        },
                        width: 70,
                        visible: true,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const channel = (row['Channel '] || '').trim();
                            const orders = parseNumber(row['L30 Orders'] || 0);
                            const views = parseNumber(row['Total Views'] || 0);
                            if (views === 0) return '-';
                            const pct = (orders / views) * 100;
                            const dotColor = getMetricDotColor(channel, 'cvr');
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="cvr" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View CVR trend"></i>`;
                            return `<span style="font-weight:600;color:${dotColor};">${pct.toFixed(1)}%</span>${chartIcon}`;
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
                            let totalOrders = 0, totalViews = 0;
                            data.forEach(function(row) {
                                totalOrders += parseNumber(row['L30 Orders'] || 0);
                                totalViews += parseNumber(row['Total Views'] || 0);
                            });
                            if (totalViews === 0) return '-';
                            return '<strong>' + ((totalOrders / totalViews) * 100).toFixed(1) + '%</strong>';
                        }
                    },
                    {
                        title: "Orders",
                        field: "L30 Orders",
                        headerTooltip: "Rolling order count per channel. Amazon = {{ (int) \App\Http\Controllers\Sales\AmazonSalesController::DAILY_SALES_WINDOW_DAYS }} days Pacific — same as Amazon Daily Sales (Canceled/Cancelled excluded).",
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
                        title: "GPFT",
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

                            return `<span style="${style}font-weight:600;">${value.toFixed(0)}%</span>${chartIcon}`;
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
                            return `<strong>${v.toFixed(0)}%</strong>`;
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

                            return `<span style="${style}font-weight:600;">${value.toFixed(0)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
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

                            return `<span style="${style}font-weight:600;">${value.toFixed(0)}%</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                var cv = cell.getElement().querySelector('span'); cv = cv ? parseFloat(cv.textContent.replace(/[$,%,\s]/g, '')) : null; showMetricChart($(e.target).data('channel'), $(e.target).data('metric'), cv);
                            }
                        }
                    },
                    {
                        title: "NROI %",
                        field: "N ROI",
                        hozAlign: "center",
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

                            return `<span style="${style}font-weight:600;">${value.toFixed(0)}%</span>${chartIcon}`;
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
                            const avg = parseNumber(rowData['Avg Rating'] || 0);
                            const total = parseNumber(rowData['Total Reviews'] || 0);
                            if ((avg == null || isNaN(avg)) && (total == null || isNaN(total) || total === 0)) return '-';
                            const r = (!isNaN(avg) && avg > 0) ? avg.toFixed(1) + ' ★' : '';
                            const rev = (!isNaN(total) && total > 0) ? total.toLocaleString('en-US') : '';
                            return [r, rev].filter(Boolean).join(' | ') || '-';
                        }
                    },
                    {
                        title: "Seller review",
                        field: "Seller Rating & Reviews",
                        hozAlign: "center",
                        sorter: "number",
                        width: 150,
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const avg = parseNumber(rowData['Seller Avg Rating'] || 0);
                            const total = parseNumber(rowData['Seller Total Reviews'] || 0);
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
                            const channel = rowData['Channel '] || '';
                            return `
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-outline-primary edit-channel-btn" 
                                            data-channel='${JSON.stringify(rowData)}' 
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-channel-btn" 
                                            data-channel="${channel}" 
                                            title="Archive">
                                        <i class="fa fa-archive"></i>
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
                                    const target = rowData['target'] || 0;
                                    const missingLink = rowData['missing_link'] || '';
                                    const additionSheet = rowData['addition_sheet'] || '';

                                    // Populate modal
                                    $('#editChannelName').val(channel);
                                    $('#editChannelUrl').val(sheetUrl);
                                    $('#editType').val(type);
                                    $('#editTarget').val(target);
                                    $('#editMissingLink').val(missingLink);
                                    $('#editAdditionSheet').val(additionSheet);
                                    $('#originalChannel').val(channel);

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

                            // Handle Delete button
                            if ($target.hasClass('delete-channel-btn') || $target.closest(
                                    '.delete-channel-btn').length) {
                                const $btn = $target.hasClass('delete-channel-btn') ? $target :
                                    $target.closest('.delete-channel-btn');
                                const channel = $btn.data('channel');

                                if (confirm(
                                        `Are you sure you want to archive channel: ${channel}?\n\nThis will set the channel status to "Inactive" and it will no longer appear in the list.`
                                        )) {
                                    $.ajax({
                                        url: '/channel-archive',
                                        method: 'POST',
                                        data: {
                                            channel: channel,
                                            _token: '{{ csrf_token() }}'
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                showToast('success',
                                                    'Channel archived successfully');
                                                table.replaceData();
                                            } else {
                                                showToast('error', response.message ||
                                                    'Failed to archive channel');
                                            }
                                        },
                                        error: function(xhr) {
                                            showToast('error',
                                                'Error archiving channel: ' + (xhr
                                                    .responseJSON?.message ||
                                                    'Unknown error'));
                                        }
                                    });
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
                ]
            });

            // Initial load only: set column dot color from last-two values (same red/green/gray logic as chart).
            var metricDotMetricKeys = ['missing_l','map','nmap','l30_sales','ad_spend','l30_orders','qty','gprofit','groi','ads_pct','npft','nroi','clicks','ad_sales','ad_sold','acos','ads_cvr','cvr','total_views','inv_at_lp'];
            function loadMetricDotTrends(tableData) {
                if (typeof lastDotColorByKey === 'undefined') return;
                var data = tableData && Array.isArray(tableData) ? tableData : (typeof table !== 'undefined' && table.getData ? table.getData() : []);
                var channelKeys = [];
                for (var i = 0; i < data.length; i++) {
                    var ch = (data[i]['Channel '] || data[i]['Channel'] || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '');
                    if (ch) channelKeys.push(ch);
                }
                if (channelKeys.length === 0) return;
                var params = { channels: channelKeys.join(',') };
                $.ajax({
                    url: channelMetricDotTrendsUrl || '/channel-metric-dot-trends',
                    type: 'GET',
                    data: params,
                    dataType: 'json'
                }).done(function(response) {
                    var invertedMetrics = ['acos', 'ads_pct'];
                    if (response.success && response.channels) {
                        Object.keys(response.channels).forEach(function(channel) {
                            var metrics = response.channels[channel];
                            Object.keys(metrics).forEach(function(metric) {
                                var pair = metrics[metric];
                                var v1 = pair[0] != null ? parseFloat(pair[0]) : null;
                                var v2 = pair[1] != null ? parseFloat(pair[1]) : null;
                                if (v1 == null || v2 == null) {
                                    lastDotColorByKey[channel + '_' + metric] = (typeof DEFAULT_DOT_GRAY !== 'undefined' ? DEFAULT_DOT_GRAY : '#6c757d');
                                    return;
                                }
                                var isInverted = invertedMetrics.indexOf(metric) >= 0;
                                var gray = (typeof DEFAULT_DOT_GRAY !== 'undefined' ? DEFAULT_DOT_GRAY : '#6c757d');
                                var color = v1 === v2 ? gray : isInverted
                                    ? (v2 < v1 ? '#28a745' : '#dc3545')
                                    : (v2 > v1 ? '#28a745' : '#dc3545');
                                lastDotColorByKey[channel + '_' + metric] = color;
                            });
                        });
                    }
                    for (var c = 0; c < channelKeys.length; c++) {
                        for (var m = 0; m < metricDotMetricKeys.length; m++) {
                            var key = channelKeys[c] + '_' + metricDotMetricKeys[m];
                            if (lastDotColorByKey[key] === undefined) lastDotColorByKey[key] = (typeof DEFAULT_DOT_GRAY !== 'undefined' ? DEFAULT_DOT_GRAY : '#6c757d');
                        }
                    }
                    saveDotColorsToStorage();
                    function redrawDots() {
                        if (typeof table !== 'undefined' && table.redraw) table.redraw(true);
                    }
                    redrawDots();
                    setTimeout(redrawDots, 100);
                    setTimeout(redrawDots, 500);
                    setTimeout(redrawDots, 1200);
                }).fail(function() {
                    for (var c = 0; c < channelKeys.length; c++) {
                        for (var m = 0; m < metricDotMetricKeys.length; m++) {
                            lastDotColorByKey[channelKeys[c] + '_' + metricDotMetricKeys[m]] = (typeof DEFAULT_DOT_GRAY !== 'undefined' ? DEFAULT_DOT_GRAY : '#6c757d');
                        }
                    }
                    saveDotColorsToStorage();
                    if (typeof table !== 'undefined' && table.redraw) table.redraw(true);
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

                    // Use Total Ad Spend directly (already computed correctly per channel)
                    const adSpend = parseNumber(row['Total Ad Spend'] || 0);
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

                // N ROI = (Net Profit / COGS) * 100 where Net Profit = Total PFT - Total Ad Spend (same as channel-masters)
                const netProfit = totalPft - totalAdSpend;
                const avgNroi = totalCogs > 0 ? (netProfit / totalCogs) * 100 : 0;

                // Update badges
                $('#total-channels').text(totalChannels);
                $('#total-l30-sales').text('$' + Math.round(totalL30Sales).toLocaleString('en-US'));
                $('#total-y-sales').text('$' + Math.round(totalYSales).toLocaleString('en-US'));
                $('#total-l30-orders').text(Math.round(totalL30Orders).toLocaleString('en-US'));
                $('#total-qty').text(Math.round(totalQty).toLocaleString('en-US'));
                $('#total-clicks').text(Math.round(totalClicks).toLocaleString('en-US'));
                $('#avg-gprofit').text(avgGprofit.toFixed(1) + '%');
                $('#total-gross-pft').text('$' + Math.round(totalPft).toLocaleString('en-US'));
                $('#avg-groi').text(Math.round(avgGroi) + '%');
                $('#total-ad-spend').text('$' + Math.round(totalAdSpend).toLocaleString('en-US'));
                $('#avg-ads-percent').text(avgAdsPercent.toFixed(1) + '%');
                $('#total-views-badge').text(Math.round(totalViews).toLocaleString('en-US'));
                // Listing CVR (overall): Σ L30 Orders / Σ Total Views — not ad conversion; see badge title
                const cvrPct = totalViews > 0 ? (totalL30Orders / totalViews) * 100 : null;
                $('#cvr-pct-badge').text(cvrPct !== null ? cvrPct.toFixed(1) + '%' : '-');
                // NPFT $ = gross profit $ − total ad spend (= L30 × (G% − Ad Spend/Sales) in aggregate)
                $('#total-pft').text('$' + Math.round(netProfit).toLocaleString('en-US'));
                $('#avg-npft').text(avgNpft.toFixed(1) + '%');
                $('#avg-nroi').text(Math.round(avgNroi) + '%');
                $('#total-map').text(Math.round(totalMap).toLocaleString('en-US'));
                $('#total-nmap').text(Math.round(totalNMap).toLocaleString('en-US'));
                $('#total-miss').text(Math.round(totalMiss).toLocaleString('en-US'));

                // Reviews badge: weighted avg rating (sum(rating*reviews)/sum(reviews)), total reviews (sum)
                let ratingSum = 0, reviewsSum = 0, sellerRatingSum = 0, sellerReviewsSum = 0;
                data.forEach(row => {
                    const r = parseNumber(row['Avg Rating'] || 0);
                    const rev = parseNumber(row['Total Reviews'] || 0);
                    const sr = parseNumber(row['Seller Avg Rating'] || 0);
                    const srev = parseNumber(row['Seller Total Reviews'] || 0);
                    if (!isNaN(r) && !isNaN(rev) && rev > 0) { ratingSum += r * rev; reviewsSum += rev; }
                    if (!isNaN(sr) && !isNaN(srev) && srev > 0) { sellerRatingSum += sr * srev; sellerReviewsSum += srev; }
                });
                const weightedAvgRating = reviewsSum > 0 ? (ratingSum / reviewsSum).toFixed(1) : '0';
                const totalReviews = Math.round(reviewsSum).toLocaleString('en-US');
                const sellerWeightedAvg = sellerReviewsSum > 0 ? (sellerRatingSum / sellerReviewsSum).toFixed(1) : '0';
                const sellerTotalRev = Math.round(sellerReviewsSum).toLocaleString('en-US');
                $('#ratings-reviews-badge').text(weightedAvgRating + ' ★ | ' + totalReviews);
                $('#seller-ratings-reviews-badge').text(sellerWeightedAvg + ' ★ | ' + sellerTotalRev);
            }

            // Combine channel search, type (B2C/B2B/Dropship), and Qty inventory filter
            function applyMasterFilters() {
                if (!table || typeof table.clearFilter !== 'function') return;
                const q = ($('#channel-search').val() || '').trim().toLowerCase();
                const inv = $('#inventory-filter').val() || 'all';
                const typeVal = $('#type-filter').val() || 'all';
                const needsFilter = q.length > 0 || inv !== 'all' || (typeVal && typeVal !== 'all');
                if (!needsFilter) {
                    table.clearFilter(true);
                    return;
                }
                table.clearFilter(true);
                table.addFilter(function(data) {
                    const ch = String(data['Channel '] || data['Channel'] || '').toLowerCase();
                    if (q && ch.indexOf(q) === -1) return false;
                    if (typeVal && typeVal !== 'all' && String(data['type'] || '') !== typeVal) return false;
                    const qty = parseNumber(data['Qty'] || 0);
                    if (inv === 'zero' && qty !== 0) return false;
                    if (inv === 'more' && qty <= 0) return false;
                    return true;
                });
            }

            // Channel Search
            $('#channel-search').on('keyup', function() {
                applyMasterFilters();
            });

            $('#inventory-filter').on('change', function() {
                applyMasterFilters();
            });

            // Type Filter
            $('#type-filter').on('change', function() {
                applyMasterFilters();
            });

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                if (!menu) return;

                menu.innerHTML = '';

                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    const field = def.field;
                    if (!field) return;

                    const isVisible = col.isVisible();
                    const li = document.createElement("li");
                    li.innerHTML =
                        `<label class="dropdown-item"><input type="checkbox" ${isVisible ? 'checked' : ''} data-field="${field}"> ${def.title}</label>`;
                    menu.appendChild(li);
                });
            }

            // Column visibility toggle
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const field = e.target.getAttribute('data-field');
                    const col = table.getColumn(field);
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                }
            });

            // Show All Columns
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => {
                    col.show();
                });
                const mapColShowAll = table.getColumn('Map');
                if (mapColShowAll) mapColShowAll.hide();
                buildColumnDropdown();
            });

            // Export to CSV
            document.getElementById("export-btn").addEventListener("click", function() {
                table.download("csv", "all_marketplace_master_" + new Date().toISOString().split('T')[0] +
                    ".csv");
            });

            // Refresh Data
            document.getElementById("refresh-btn").addEventListener("click", function() {
                table.setData();
            });

            // ==========================================
            // SECTION FILTER - Show/Hide Column Groups
            // ==========================================
            
            const sectionColumns = {
                'all': 'ALL', // Show all columns
                'ads': ['L30 Sales', 'Total Ad Spend', 'Total Views', 'CVR', 'KW Spent', 'PT Spent', 'HL Spent', 'PMT Spent', 'KW ACOS', 'PT ACOS', 'HL ACOS', 'PMT ACOS', 'Shopping Spent', 'SERP Spent', 'clicks', 'KW Clicks', 'PT Clicks', 'HL Clicks', 'PMT Clicks', 'Shopping Clicks', 'SERP Clicks', 'Ad Sales', 'KW Sales', 'PT Sales', 'HL Sales', 'PMT Sales', 'Shopping Sales', 'SERP Sales', 'ad_sold', 'KW Sold', 'PT Sold', 'HL Sold', 'PMT Sold', 'Shopping Sold', 'SERP Sold', 'ACOS', 'Shopping ACOS', 'SERP ACOS', 'Ads CVR', 'KW CVR', 'PT CVR', 'HL CVR', 'PMT CVR', 'Shopping CVR', 'SERP CVR', 'TAcos %', 'Missing Ads'],
                'inv': ['Avl', 'Res', 'Inb', 'Unf', 'Wrk', 'Total Inv', 'Allocated'],
                'margins': ['G PFT%', 'G ROI%', 'NP$', 'N PFT%', 'N ROI%', 'COGS', 'Total Ad Spend', 'TAcos %', '_gross_pft'],
                'movement': ['L30 Sales', 'Growth %', 'Y Sales', 'L30 Orders', 'Qty items', 'Velocity'],
                'returns': ['Return Rate', 'Return Units', 'Return Value'],
                'ah': ['AH Score', 'Policy Violations', 'Customer Complaints', 'Shipping Health', 'CC Health', 'Returns %', 'A2Z Claims', 'Ratings & Reviews', 'Seller Rating & Reviews'],
                'expenses': ['Total Ad Spend', 'Shipping Cost', 'FBA Fees', 'Storage Fees'],
                'traffic': ['clicks', 'Sessions', 'Page Views', 'Conversion Rate', 'Total Views', 'CVR'],
                'reviews': ['Total Reviews', 'Avg Rating', '5-Star', '4-Star', '1-Star', 'Ratings & Reviews', 'Seller Rating & Reviews'],
                'missing': ['Miss', 'NMap', 'Missing Ads', 'Total Views']
            };
            
            $('.section-option').on('click', function(e) {
                e.preventDefault();
                const section = $(this).data('section');
                const sectionName = $(this).text().trim();
                
                console.log('Section selected:', section);
                $('#current-section').text(sectionName);
                
                // Type filter: B2C, B2B, Dropship — filter rows by channel type
                if (section === 'B2C' || section === 'B2B' || section === 'Dropship') {
                    $('#type-filter').val(section);
                    applyMasterFilters();
                    return;
                }
                
                if (section === 'all') {
                    $('#type-filter').val('all');
                    applyMasterFilters();
                    // Show all columns (Map stays hidden — use Columns menu to show)
                    table.getColumns().forEach(col => {
                        if (col.getField() !== 'Channel ') { // Keep channel column always visible
                            col.show();
                        }
                    });
                    const mapColAll = table.getColumn('Map');
                    if (mapColAll) mapColAll.hide();
                    console.log('✓ All columns visible');
                } else {
                    // Column section: show/hide column groups
                    table.getColumns().forEach(col => {
                        const field = col.getField();
                        if (field !== 'Channel ') { // Keep channel column
                            col.hide();
                        }
                    });
                    
                    const columnsToShow = sectionColumns[section] || [];
                    columnsToShow.forEach(field => {
                        const column = table.getColumn(field);
                        if (column) {
                            column.show();
                        }
                    });
                    
                    console.log(`✓ Showing ${section.toUpperCase()} columns:`, columnsToShow);
                }
            });

            // Table built event
            table.on('tableBuilt', function() {
                buildColumnDropdown();
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
            let currentChartDays = 32;
            let adChartAjax = null; // track in-flight request
            let currentChartMode = 'ad'; // 'ad' = ad breakdown, 'metric' = channel metric
            let currentMetricKey = ''; // metric key for channel metric mode

            // Dot colors: same as chart's last-data-point logic (red/green/gray). Set on page load only.
            var lastDotColorByKey = {};
            function getMetricDotColor(channelName, metricKey) {
                var k = (channelName || '').toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '') + '_' + (metricKey || '');
                return lastDotColorByKey[k] || DEFAULT_DOT_GRAY;
            }
            function saveDotColorsToStorage() {
                try {
                    localStorage.setItem('channelMasterDotColors', JSON.stringify(lastDotColorByKey));
                } catch (e) { /* ignore */ }
            }

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
                currentChartChannel = channel.toLowerCase().replace(/[^a-z0-9]/g, '');
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
                'l30_sales': 'Sales',
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
                'tat': 'TAT',
            };

            // Show metric chart (for non-ad-breakdown columns)
            var currentCellValue = null;
            function showMetricChart(channel, metricKey, cellValue) {
                currentChartMode = 'metric';
                currentChartChannel = channel.toLowerCase().replace(/[^a-z0-9]/g, '');
                currentMetricKey = metricKey;
                currentChartMetric = metricKey; // for fmtVal formatting
                currentChartDays = 32; // align with Amazon Daily Sales / channel rolling window
                currentCellValue = (cellValue !== undefined && cellValue !== null && !isNaN(cellValue)) ? cellValue : null;

                $('#adChartRangeSelect').val('32');

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

            // Badge click handler — show overall (all channels) metric trend
            $(document).on('click', '.badge-chart-link', function() {
                const metricKey = $(this).data('metric');
                // Extract the displayed badge value so the chart can match it exactly
                const badgeText = $(this).find('span').text().replace(/[,$%]/g, '').trim();
                const badgeValue = parseFloat(badgeText) || null;
                showMetricChart('All', metricKey, badgeValue);
            });

            // Load daily bar chart for the clicked metric only (metric mode) — same channel & days as line chart
            function loadSalesOrdersItemsBarChart() {
                const channel = currentChartChannel;
                const days = currentChartDays;
                const metricKey = currentMetricKey || 'l30_sales';
                $.get('/channel-metric-chart-data', { channel: channel, days: days, metric: metricKey }).done(function(resp) {
                    const data = (resp && resp.data) ? resp.data : [];
                    if (data.length === 0) {
                        $('#salesOrdersItemsBarContainer').css('display', 'none').hide();
                        if (salesOrdersItemsBarChartInstance) {
                            salesOrdersItemsBarChartInstance.destroy();
                            salesOrdersItemsBarChartInstance = null;
                        }
                        return;
                    }
                    const year = new Date().getFullYear();
                    const sorted = [...data].sort(function(a, b) {
                        const dA = new Date((a.date || a.label) + ' ' + year);
                        const dB = new Date((b.date || b.label) + ' ' + year);
                        if (isNaN(dA.getTime()) || isNaN(dB.getTime())) return String(a.date || a.label).localeCompare(String(b.date || b.label));
                        return dA - dB;
                    });
                    const labels = sorted.map(d => d.date || d.label);
                    const values = sorted.map(d => parseFloat(d.value) || 0);
                    $('#salesOrdersItemsBarContainer').css('display', 'flex').show();
                    renderSingleMetricBarChart({ labels, values, metricKey });
                }).fail(function() {
                    $('#salesOrdersItemsBarContainer').css('display', 'none').hide();
                    if (salesOrdersItemsBarChartInstance) {
                        salesOrdersItemsBarChartInstance.destroy();
                        salesOrdersItemsBarChartInstance = null;
                    }
                });
            }

            function barChartFmtVal(metricKey, v) {
                if (metricKey === 'l30_sales' || metricKey === 'ad_spend' || metricKey === 'ad_sales' || metricKey === 'pft' || metricKey === 'inv_at_lp') {
                    return '$' + Math.round(v).toLocaleString('en-US');
                }
                if (metricKey === 'acos' || metricKey === 'cvr' || metricKey === 'ads_cvr' || metricKey === 'gprofit' || metricKey === 'groi' || metricKey === 'ads_pct' || metricKey === 'npft' || metricKey === 'nroi') {
                    return v.toFixed(1) + '%';
                }
                if (metricKey === 'tat') return v.toFixed(2);
                return Math.round(v).toLocaleString('en-US');
            }

            function renderSingleMetricBarChart(barData) {
                const ctx = document.getElementById('salesOrdersItemsBarChart');
                if (!ctx) return;
                const g = ctx.getContext('2d');
                if (salesOrdersItemsBarChartInstance) {
                    salesOrdersItemsBarChartInstance.destroy();
                    salesOrdersItemsBarChartInstance = null;
                }
                const labels = barData.labels || [];
                const values = barData.values || [];
                const metricKey = barData.metricKey || 'l30_sales';
                const seriesLabel = metricLabels[metricKey] || metricKey;
                const isCurrency = ['l30_sales', 'ad_spend', 'ad_sales', 'pft', 'inv_at_lp'].includes(metricKey);
                const isPercent = ['acos', 'cvr', 'ads_cvr', 'gprofit', 'groi', 'ads_pct', 'npft', 'nroi'].includes(metricKey);
                const yTitle = isCurrency ? seriesLabel + ' ($)' : isPercent ? seriesLabel + ' (%)' : seriesLabel;
                salesOrdersItemsBarChartInstance = new Chart(g, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: seriesLabel, data: values, backgroundColor: 'rgba(30,136,229,0.8)', borderColor: '#1e88e5', borderWidth: 1 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 8, left: 4, right: 4, bottom: 20 } },
                        plugins: {
                            legend: { display: true, position: 'top', labels: { font: { size: 9 }, boxWidth: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const v = ctx.raw;
                                        return seriesLabel + ': ' + barChartFmtVal(metricKey, v);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: { size: labels.length > 25 ? 7 : 8 },
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31)
                                }
                            },
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: yTitle, font: { size: 9 } },
                                ticks: {
                                    font: { size: 9 },
                                    callback: function(v) {
                                        if (isCurrency) return '$' + (v >= 1000 ? (v/1000)+'k' : v);
                                        if (isPercent) return v + '%';
                                        return Math.round(v).toLocaleString('en-US');
                                    }
                                }
                            }
                        }
                    }
                });
            }

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

                // Dynamic Y-axis range with 10% padding
                const range = dataMax - dataMin || 1;
                const yMin = Math.max(0, dataMin - range * 0.1);
                const yMax = dataMax + range * 0.1;

                // --- Format helper (no decimals for spend/sales) ---
                const fmtVal = (v) => {
                    const m = currentChartMetric;
                    if (m === 'spend' || m === 'sales' || m === 'l30_sales' || m === 'ad_spend' || m === 'ad_sales' || m === 'pft' || m === 'inv_at_lp') {
                        return '$' + Math.round(v).toLocaleString('en-US');
                    }
                    if (m === 'acos' || m === 'cvr' || m === 'ads_cvr' || m === 'gprofit' || m === 'groi' || m === 'ads_pct' || m === 'npft' || m === 'nroi') {
                        return v.toFixed(1) + '%';
                    }
                    if (m === 'tat') return v.toFixed(2);
                    return Math.round(v).toLocaleString('en-US');
                };

                // --- Populate right-side reference panel (positive values in red) ---
                const refRed = '#dc3545';
                const refGray = '#6c757d';
                const refGreen = '#198754';
                const highestEl = document.getElementById('adChartHighest');
                const medianEl = document.getElementById('adChartMedian');
                const lowestEl = document.getElementById('adChartLowest');
                highestEl.textContent = fmtVal(dataMax);
                highestEl.style.color = dataMax === 0 ? refGreen : dataMax > 0 ? refRed : refGray;
                medianEl.textContent = fmtVal(median);
                medianEl.style.color = median === 0 ? refGreen : median > 0 ? refRed : refGray;
                lowestEl.textContent = fmtVal(dataMin);
                lowestEl.style.color = dataMin === 0 ? refGreen : dataMin > 0 ? refRed : refGray;

                // --- Dot colors: green=UP red=DOWN, but INVERTED for ACOS & TAcos % (lower is better) ---
                const invertedMetrics = ['acos', 'ads_pct'];
                const isInverted = invertedMetrics.includes(currentChartMetric);
                const dotColors = values.map((v, i) => {
                    if (i === 0) return '#6c757d';           // neutral for first point
                    if (isInverted) {
                        return v < values[i - 1] ? '#28a745' :   // green = lower (good for ACOS/TAcos %)
                               v > values[i - 1] ? '#dc3545' :   // red   = higher (bad for ACOS/TAcos %)
                               '#6c757d';
                    }
                    return v > values[i - 1] ? '#28a745' :   // green = higher than yesterday (UP)
                           v < values[i - 1] ? '#dc3545' :   // red   = lower than yesterday (DOWN)
                           '#6c757d';                         // neutral = same
                });

                // --- Value label colors: positive = red, zero = green ---
                const labelColors = values.map(v => v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d');

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

                // --- Value labels plugin (draws value near each dot) ---
                const valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDatasetsDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;

                        ctx.save();
                        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        meta.data.forEach((point, i) => {
                            const val = dataset.data[i];
                            const x = point.x;
                            const y = point.y;

                            // Alternate label position to reduce overlap
                            const offsetY = (i % 2 === 0) ? -10 : -20;

                            ctx.fillStyle = labelColors[i];
                            ctx.fillText(fmtVal(val), x, y + offsetY);
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
                        layout: {
                            padding: { top: 26, left: 2, right: 2, bottom: 2 }
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
                                        if (idx >= 7) {
                                            const diff7 = context.raw - values[idx - 7];
                                            const arrow7 = diff7 < 0 ? '▼' : diff7 > 0 ? '▲' : '▬';
                                            parts.push('vs 7d Ago: ' + arrow7 + ' ' + fmtVal(Math.abs(diff7)));
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
                                    maxRotation: 45,
                                    minRotation: 45,
                                    autoSkip: currentChartMode === 'metric' ? false : labels.length > 14,
                                    maxTicksLimit: currentChartMode === 'metric' ? Math.max(labels.length, 31) : (labels.length > 14 ? 14 : labels.length),
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
                    const target = rowData['target'] || 0;
                    const missingLink = rowData['missing_link'] || '';
                    const additionSheet = rowData['addition_sheet'] || '';

                    // Populate modal fields
                    $('#editChannelName').val(channel);
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editTarget').val(target);
                    $('#editMissingLink').val(missingLink);
                    $('#editAdditionSheet').val(additionSheet);
                    $('#originalChannel').val(channel);

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

            // Delete channel button handler
            $(document).on('click', '.delete-channel-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const channel = $(this).data('channel');

                if (confirm(
                        `Are you sure you want to archive channel: ${channel}?\n\nThis will set the channel status to "Inactive" and it will no longer appear in the list.`
                        )) {
                    $.ajax({
                        url: '/channel-archive',
                        method: 'POST',
                        data: {
                            channel: channel,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                showToast('success', 'Channel archived successfully');
                                // Reload the table
                                table.replaceData();
                            } else {
                                showToast('error', response.message ||
                                    'Failed to archive channel');
                            }
                        },
                        error: function(xhr) {
                            showToast('error', 'Error archiving channel: ' + (xhr.responseJSON
                                ?.message || 'Unknown error'));
                        }
                    });
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

            // Save channel form handler
            $(document).on('click', '#saveChannelBtn', function() {
                const channelName = $('#channelName').val().trim();
                const channelUrl = $('#channelUrl').val().trim();
                const additionSheet = $('#additionSheet').val().trim();
                const type = $('#type').val().trim();

                if (!channelName || !channelUrl || !type) {
                    showToast('error', 'All fields are required');
                    return;
                }

                $.ajax({
                    url: '/channel_master/store',
                    method: 'POST',
                    data: {
                        channel: channelName,
                        sheet_link: channelUrl,
                        addition_sheet: additionSheet,
                        type: type,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'addChannelModal'));
                            if (modal) modal.hide();
                            $('#channelForm')[0].reset();
                            table.setData(); // Reload data
                            showToast('success', 'Channel added successfully');
                        } else {
                            showToast('error', res.message || 'Failed to add channel');
                        }
                    },
                    error: function() {
                        showToast('error', 'Error submitting form');
                    }
                });
            });

            // Update channel form handler
            $(document).on('click', '#updateChannelBtn', function() {
                const channel = $('#editChannelName').val().trim();
                const sheetUrl = $('#editChannelUrl').val().trim();
                const type = $('#editType').val();
                const target = $('#editTarget').val().trim();
                const missingLink = $('#editMissingLink').val().trim();
                const additionSheet = $('#editAdditionSheet').val().trim();
                const originalChannel = $('#originalChannel').val().trim();

                if (!channel || !sheetUrl) {
                    showToast('error', 'Channel Name and Sheet URL are required');
                    return;
                }

                $.ajax({
                    url: '/channel_master/update',
                    method: 'POST',
                    data: {
                        channel: channel,
                        sheet_url: sheetUrl,
                        type: type,
                        target: target,
                        missing_link: missingLink,
                        addition_sheet: additionSheet,
                        original_channel: originalChannel,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById(
                                'editChannelModal'));
                            if (modal) modal.hide();
                            $('#editChannelForm')[0].reset();
                            table.setData(); // Reload data
                            showToast('success', 'Channel updated successfully');
                        } else {
                            showToast('error', res.message || 'Update failed');
                        }
                    },
                    error: function() {
                        showToast('error', 'Error updating channel');
                    }
                });
            });
        });
    </script>
@endsection
