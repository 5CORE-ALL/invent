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
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
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
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'All Marketplace Master',
        'sub_title' => 'Comprehensive Marketplace Analytics',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Search -->
                    <input type="text" id="channel-search" class="form-control form-control-sm"
                        placeholder="Search Channel..." style="width: 150px; display: inline-block;">

                    <!-- Type Filter -->
                    <select id="type-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
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

                    <button id="toggleAdColumnsBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-ad"></i> Show Ads Data
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            Total Channels: <span id="total-channels">0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="l30_sales" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            L30 Sales: <span id="total-l30-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="l30_orders" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            L30 Orders: <span id="total-l30-orders">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="qty" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Total Qty: <span id="total-qty">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2 badge-chart-link" data-metric="gprofit" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            Avg Gprofit%: <span id="avg-gprofit">0%</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="groi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Avg G ROI%: <span id="avg-groi">0%</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2 badge-chart-link" data-metric="ad_spend" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Total Ad Spend: <span id="total-ad-spend">$0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2 badge-chart-link" data-metric="pft" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            NPFT: <span id="total-pft">$0</span>
                        </span>
                        <span class="badge bg-dark fs-6 p-2 badge-chart-link" data-metric="npft" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Avg N PFT%: <span id="avg-npft">0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2 badge-chart-link" data-metric="nroi" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Avg N ROI%: <span id="avg-nroi">0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2 badge-chart-link" data-metric="clicks" style="color: black; font-weight: bold; cursor:pointer;" title="View trend">
                            Total Clicks: <span id="total-clicks">0</span>
                        </span>
                        {{-- <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;">
                             Map: <span id="total-map">0</span>
                        </span> --}}
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="nmap" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            N Map: <span id="total-nmap">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2 badge-chart-link" data-metric="missing_l" style="color: white; font-weight: bold; cursor:pointer;" title="View trend">
                            Missing L : <span id="total-miss">0</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="marketplace-table-wrapper"
                    style="height: calc(100vh - 300px); display: flex; flex-direction: column;">
                    <div id="marketplace-table" style="flex: 1;"></div>
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
                            <label for="editMissingLink" class="form-label">Missing Link</label>
                            <input type="url" class="form-control" id="editMissingLink" placeholder="https://...">
                            <small class="text-muted">This link will open when clicking the Missing column</small>
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
                        <span id="modalChannelName">Channel</span> - Historical Data (Last 30 Days)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead style="background: #f5f7fa;">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">L30 Sales</th>
                                    <th class="text-end">L30 Orders</th>
                                    <th class="text-end">Total Qty</th>
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
                        <span id="modalGraphChannelName">Channel</span> - Historical Graph (Last 30 Days)
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
    <div class="modal fade" id="adBreakdownChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="adChartModalTitle">Ad Breakdown - Rolling L30</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="adChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
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
            adSpendBreakdownFields.forEach(field => {
                if (adSpendBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        // Track AD CLICKS breakdown columns visibility
        let clicksBreakdownVisible = false;
        const clicksBreakdownFields = ['KW Clicks', 'PT Clicks', 'HL Clicks', 'PMT Clicks', 'Shopping Clicks',
            'SERP Clicks'];

        function toggleClicksBreakdownColumns() {
            clicksBreakdownVisible = !clicksBreakdownVisible;
            clicksBreakdownFields.forEach(field => {
                if (clicksBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
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
            acosBreakdownFields.forEach(field => {
                if (acosBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        // Track AD CVR breakdown columns visibility
        let cvrBreakdownVisible = false;
        const cvrBreakdownFields = ['KW CVR', 'PT CVR', 'HL CVR', 'PMT CVR', 'Shopping CVR', 'SERP CVR'];

        function toggleCvrBreakdownColumns() {
            cvrBreakdownVisible = !cvrBreakdownVisible;
            cvrBreakdownFields.forEach(field => {
                if (cvrBreakdownVisible) {
                    table.showColumn(field);
                } else {
                    table.hideColumn(field);
                }
            });
        }

        $(document).ready(function() {
            // Initialize Tabulator
            table = new Tabulator("#marketplace-table", {
                ajaxURL: "/channels-master-data",
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 50,
                paginationCounter: "rows",
                columnCalcs: "both",
                initialSort: [{
                    column: "L30 Sales",
                    dir: "desc"
                }],
                ajaxResponse: function(url, params, response) {
                    if (response && response.data) {
                        updateSummaryStats(response.data);
                        return response.data;
                    }
                    return [];
                },
                columns: [{
                        title: "Channel",
                        field: "Channel ",
                        frozen: true,
                        formatter: function(cell) {
                            const channel = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const type = rowData.type || 'B2C';

                            // Determine type badge class
                            let typeBadgeClass = 'type-b2c';
                            if (type === 'B2B') typeBadgeClass = 'type-b2b';
                            else if (type === 'Dropship') typeBadgeClass = 'type-dropship';

                            const historyIcon =
                                `<i class="fas fa-chart-line history-table-icon" style="cursor:pointer;color:#4361ee;font-size:14px;margin-left:5px;" title="View Historical Table" data-channel="${channel}"></i>`;
                            const graphIcon =
                                `<i class="fas fa-chart-area history-graph-icon" style="cursor:pointer;color:#28a745;font-size:14px;margin-left:5px;" title="View Historical Graph" data-channel="${channel}"></i>`;

                            return `<div>
                                <div>
                                    <span>${channel}</span>
                                    ${historyIcon}
                                    ${graphIcon}
                                </div>
                                <span class="type-badge ${typeBadgeClass}">${type}</span>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            // Handle icon clicks directly
                            if (e.target.classList.contains('history-table-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                console.log('Table icon clicked for:', channelName);
                                showChannelHistory(channelName);
                                return;
                            }

                            if (e.target.classList.contains('history-graph-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                console.log('Graph icon clicked for:', channelName);
                                showChannelHistoryGraph(channelName);
                                return;
                            }
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
                            const missingLink = rowData['missing_link'] || '';
                            const channel = (rowData['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="missing_l" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;

                            const style = 'color:black;font-weight:600;';

                            if (missingLink && value > 0) {
                                return `<a href="${missingLink}" target="_blank" style="${style}text-decoration:none;cursor:pointer;" title="Click to view missing items details">${value}</a>${chartIcon}`;
                            }

                            return `<span style="${style}">${value}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    // {
                    //     title: "Map",
                    //     field: "Map",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: function(cell) {
                    //         const value = parseNumber(cell.getValue());

                    //         // Simple black text - no background colors
                    //         const style = 'color:black;font-weight:600;';

                    //         return `<span style="${style}">${value}</span>`;
                    //     },
                    //     bottomCalc: "sum",
                    //     bottomCalcFormatter: function(cell) {
                    //         const value = cell.getValue();
                    //         return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                    //     }
                    // },
                    {
                        title: "N Map",
                        field: "NMap",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nmap" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;

                            let color = 'red';
                            if (value === 0) {
                                color = 'green';
                            }

                            return `<span style="color:${color};font-weight:bold;">${value}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Sheet",
                        field: "sheet_link",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const link = cell.getValue();
                            if (!link) return '-';
                            return `<a href="${link}" target="_blank" class="btn btn-sm btn-success">🔗</a>`;
                        }
                    },
                    {
                        title: "L30 Sales",
                        field: "L30 Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l30_sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            return `<span style="font-weight: 600;">$${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "AD SPEND",
                        field: "Total Ad Spend",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const totalSpent = parseNumber(cell.getValue() || 0);
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (totalSpent === 0) return '-';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_spend" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-spend-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Spend Breakdown"></i>`;
                            return `<span style="font-weight:600;">$${Math.round(totalSpent).toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
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
                        title: "KW",
                        field: "KW Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PT",
                        field: "PT Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "HL",
                        field: "HL Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PMT",
                        field: "PMT Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SHOP",
                        field: "Shopping Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SERP",
                        field: "SERP Spent",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right clicks-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Clicks Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
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
                        title: "KW",
                        field: "KW Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PT",
                        field: "PT Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "HL",
                        field: "HL Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PMT",
                        field: "PMT Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SHOP",
                        field: "Shopping Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SERP",
                        field: "SERP Clicks",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="clicks" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-sales-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Ad Sales Breakdown"></i>`;
                            return `<span style="font-weight:600;">$${Math.round(value).toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
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
                        title: "KW",
                        field: "KW Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PT",
                        field: "PT Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "HL",
                        field: "HL Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PMT",
                        field: "PMT Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SHOP",
                        field: "Shopping Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SERP",
                        field: "SERP Sales",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="sales" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ad_sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right ad-sold-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle Ad Sold Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
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
                        title: "KW",
                        field: "KW Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PT",
                        field: "PT Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "HL",
                        field: "HL Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "PMT",
                        field: "PMT Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SHOP",
                        field: "Shopping Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                        title: "G-SERP",
                        field: "SERP Sold",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="sold" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right acos-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle ACOS Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
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
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>${parseFloat(value).toFixed(1)}%</strong>`;
                        }
                    },
                    // Hidden ACOS Breakdown Columns
                    {
                        title: "KW",
                        field: "KW ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View ACOS Chart"></i>`;
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
                        title: "PT",
                        field: "PT ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View ACOS Chart"></i>`;
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
                        title: "HL",
                        field: "HL ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View ACOS Chart"></i>`;
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
                        title: "PMT",
                        field: "PMT ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View ACOS Chart"></i>`;
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
                        title: "G-SHOP",
                        field: "Shopping ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View ACOS Chart"></i>`;
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
                        title: "G-SERP",
                        field: "SERP ACOS",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="acos" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View ACOS Chart"></i>`;
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
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            if (!value || value === 0) return '-';
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="ads_cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            const infoIcon =
                                `<i class="fas fa-chevron-right cvr-breakdown-toggle ms-1" style="cursor:pointer;color:#17a2b8;font-size:10px;" title="Toggle CVR Breakdown"></i>`;
                            return `<span style="font-weight:600;">${value.toFixed(1)}%</span>${chartIcon}${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
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
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>${Math.round(value)}%</strong>`;
                        }
                    },
                    // Hidden CVR Breakdown Columns
                    {
                        title: "KW",
                        field: "KW CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="kw" data-metric="cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View CVR Chart"></i>`;
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
                        title: "PT",
                        field: "PT CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pt" data-metric="cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View CVR Chart"></i>`;
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
                        title: "HL",
                        field: "HL CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="hl" data-metric="cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View CVR Chart"></i>`;
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
                        title: "PMT",
                        field: "PMT CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="pmt" data-metric="cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View CVR Chart"></i>`;
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
                        title: "G-SHOP",
                        field: "Shopping CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="shopping" data-metric="cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View CVR Chart"></i>`;
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
                        title: "G-SERP",
                        field: "SERP CVR",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            if (value === 0) return '-';
                            const chartIcon =
                                `<i class="fas fa-circle ad-chart-icon ms-1" data-channel="${channel}" data-adtype="serp" data-metric="cvr" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View CVR Chart"></i>`;
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
                        title: "L30 Orders",
                        field: "L30 Orders",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="l30_orders" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Qty",
                        field: "Qty",
                        hozAlign: "center",
                        sorter: "number",
                        width: 90,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="qty" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
                            return `<span>${value.toLocaleString('en-US')}</span>${chartIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('metric-chart-icon')) {
                                e.stopPropagation();
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },

                    {
                        title: "Gprofit%",
                        field: "Gprofit%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="gprofit" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "G ROI%",
                        field: "G Roi",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="groi" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        }
                    },

                    {
                        title: "Ads%",
                        field: "Ads%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const channelRaw = (rowData['Channel '] || '').trim();
                            const channel = channelRaw.toLowerCase();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channelRaw}" data-metric="ads_pct" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;

                            let adsPercent = 0;
                            if (channel === 'walmart' || channel === 'temu' || channel ===
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
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "N PFT%",
                        field: "N PFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="npft" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
                        }
                    },
                    {
                        title: "N ROI%",
                        field: "N ROI",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const channel = (cell.getRow().getData()['Channel '] || '').trim();
                            const chartIcon = `<i class="fas fa-circle metric-chart-icon ms-1" data-channel="${channel}" data-metric="nroi" style="cursor:pointer;color:#b8860b;font-size:8px;" title="View Chart"></i>`;
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
                                showMetricChart($(e.target).data('channel'), $(e.target).data('metric'));
                            }
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

                                    // Populate modal
                                    $('#editChannelName').val(channel);
                                    $('#editChannelUrl').val(sheetUrl);
                                    $('#editType').val(type);
                                    $('#editTarget').val(target);
                                    $('#editMissingLink').val(missingLink);
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
                ]
            });

            // Update summary statistics
            function updateSummaryStats(data) {
                let totalChannels = data.length;
                let totalL30Sales = 0;
                let totalL30Orders = 0;
                let totalQty = 0;
                let totalClicks = 0;
                let totalPft = 0;
                let totalCogs = 0;
                let totalAdSpend = 0;
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

                    totalL30Sales += l30Sales;
                    totalL30Orders += l30Orders;
                    totalQty += qty;
                    totalClicks += clicks;
                    totalAdSpend += adSpend;
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

                // Calculate average Ads% = Total Ad Spend / Total L30 Sales (same as channel-masters)
                const avgAdsPercent = totalL30Sales > 0 ? (totalAdSpend / totalL30Sales) * 100 : 0;

                // N PFT = G PFT - Ads% (same as channel-masters)
                const avgNpft = avgGprofit - avgAdsPercent;

                // N ROI = (Net Profit / COGS) * 100 where Net Profit = Total PFT - Total Ad Spend (same as channel-masters)
                const netProfit = totalPft - totalAdSpend;
                const avgNroi = totalCogs > 0 ? (netProfit / totalCogs) * 100 : 0;

                // Update badges
                $('#total-channels').text(totalChannels);
                $('#total-l30-sales').text('$' + Math.round(totalL30Sales).toLocaleString('en-US'));
                $('#total-l30-orders').text(Math.round(totalL30Orders).toLocaleString('en-US'));
                $('#total-qty').text(Math.round(totalQty).toLocaleString('en-US'));
                $('#total-clicks').text(Math.round(totalClicks).toLocaleString('en-US'));
                $('#avg-gprofit').text(avgGprofit.toFixed(1) + '%');
                $('#avg-groi').text(avgGroi.toFixed(1) + '%');
                $('#total-ad-spend').text('$' + Math.round(totalAdSpend).toLocaleString('en-US'));
                $('#total-pft').text('$' + Math.round(totalPft).toLocaleString('en-US'));
                $('#avg-npft').text(avgNpft.toFixed(1) + '%');
                $('#avg-nroi').text(avgNroi.toFixed(1) + '%');
                $('#total-map').text(Math.round(totalMap).toLocaleString('en-US'));
                $('#total-nmap').text(Math.round(totalNMap).toLocaleString('en-US'));
                $('#total-miss').text(Math.round(totalMiss).toLocaleString('en-US'));
            }

            // Channel Search
            $('#channel-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("Channel ", "like", value);
            });

            // Type Filter
            $('#type-filter').on('change', function() {
                const value = $(this).val();
                if (value === 'all') {
                    table.clearFilter(true);
                } else {
                    table.setFilter("type", "=", value);
                }
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

            // Toggle Ad Columns (from Total Ad Spend to Missing Ads)
            document.getElementById("toggleAdColumnsBtn").addEventListener("click", function() {
                toggleMainAdColumns();
            });

            // Table built event
            table.on('tableBuilt', function() {
                buildColumnDropdown();
            });

            // Table data loaded event - rebuild dropdown
            table.on('dataLoaded', function() {
                setTimeout(function() {
                    buildColumnDropdown();
                }, 100);
            });

            // History table icon click handler
            $(document).on('click', '.history-table-icon', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const channelName = $(this).data('channel');
                showChannelHistory(channelName);
            });

            // History graph icon click handler
            $(document).on('click', '.history-graph-icon', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const channelName = $(this).data('channel');
                showChannelHistoryGraph(channelName);
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
            let currentChartChannel = '';
            let currentChartAdType = '';
            let currentChartMetric = 'spend';
            let currentChartDays = 30;
            let adChartAjax = null; // track in-flight request
            let currentChartMode = 'ad'; // 'ad' = ad breakdown, 'metric' = channel metric
            let currentMetricKey = ''; // metric key for channel metric mode

            // Channels that have daily data
            const channelsWithDailyData = ['amazon', 'amazonfba', 'ebay', 'ebaytwo', 'ebaythree', 'shopifyb2c', 'temu', 'walmart'];
            const adTypesForChannel = {
                'amazon': ['kw', 'pt', 'hl'],
                'amazonfba': ['kw', 'pt'],
                'ebay': ['kw', 'pmt'],
                'ebaytwo': ['kw', 'pmt'],
                'ebaythree': ['kw', 'pmt'],
                'shopifyb2c': ['shopping', 'serp'],
                'temu': ['kw'],
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
                currentChartDays = 30; // reset to default

                const hasData = channelsWithDailyData.includes(currentChartChannel) &&
                    (adTypesForChannel[currentChartChannel] || []).includes(currentChartAdType);

                // Reset dropdown to 30D
                $('#adChartRangeSelect').val('30');

                // Set modal title
                const adTypeLabel = currentChartAdType.toUpperCase();
                let metricLabel;
                if (metricType === 'acos') metricLabel = 'ACOS %';
                else if (metricType === 'cvr') metricLabel = 'CVR %';
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
                        } else {
                            $('#adChartNoData').show();
                        }
                    },
                    error: function(xhr, status) {
                        adChartAjax = null;
                        if (status === 'abort') return;
                        console.error('Error fetching chart data:', xhr);
                        $('#adChartLoading').hide();
                        $('#adChartNoData').show();
                    }
                });
            }

            // Metric label map for titles
            const metricLabels = {
                'l30_sales': 'L30 Sales',
                'l30_orders': 'L30 Orders',
                'qty': 'Qty',
                'gprofit': 'Gprofit%',
                'groi': 'G ROI%',
                'ads_pct': 'Ads%',
                'pft': 'Total Pft',
                'npft': 'N PFT%',
                'nroi': 'N ROI%',
                'missing_l': 'Missing L',
                'nmap': 'N Map',
                'ad_spend': 'AD Spend',
                'clicks': 'AD Clicks',
                'ad_sales': 'AD Sales',
                'ad_sold': 'AD Sold',
                'acos': 'ACOS',
                'ads_cvr': 'AD CVR',
            };

            // Show metric chart (for non-ad-breakdown columns)
            function showMetricChart(channel, metricKey) {
                currentChartMode = 'metric';
                currentChartChannel = channel.toLowerCase().replace(/[^a-z0-9]/g, '');
                currentMetricKey = metricKey;
                currentChartMetric = metricKey; // for fmtVal formatting
                currentChartDays = 30;

                // Reset dropdown
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

            // Badge click handler — show overall (all channels) metric trend
            $(document).on('click', '.badge-chart-link', function() {
                const metricKey = $(this).data('metric');
                showMetricChart('All', metricKey);
            });

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
                    if (m === 'spend' || m === 'sales' || m === 'l30_sales' || m === 'ad_spend' || m === 'ad_sales' || m === 'pft') {
                        return '$' + Math.round(v).toLocaleString('en-US');
                    }
                    if (m === 'acos' || m === 'cvr' || m === 'ads_cvr' || m === 'gprofit' || m === 'groi' || m === 'ads_pct' || m === 'npft' || m === 'nroi') {
                        return v.toFixed(1) + '%';
                    }
                    return Math.round(v).toLocaleString('en-US');
                };

                // --- Populate right-side reference panel ---
                document.getElementById('adChartHighest').textContent = fmtVal(dataMax);
                document.getElementById('adChartMedian').textContent = fmtVal(median);
                document.getElementById('adChartLowest').textContent = fmtVal(dataMin);

                // --- Dot colors: green=UP red=DOWN, but INVERTED for ACOS & Ads% (lower is better) ---
                const invertedMetrics = ['acos', 'ads_pct'];
                const isInverted = invertedMetrics.includes(currentChartMetric);
                const dotColors = values.map((v, i) => {
                    if (i === 0) return '#6c757d';           // neutral for first point
                    if (isInverted) {
                        return v < values[i - 1] ? '#28a745' :   // green = lower (good for ACOS/Ads%)
                               v > values[i - 1] ? '#dc3545' :   // red   = higher (bad for ACOS/Ads%)
                               '#6c757d';
                    }
                    return v > values[i - 1] ? '#28a745' :   // green = higher than yesterday (UP)
                           v < values[i - 1] ? '#dc3545' :   // red   = lower than yesterday (DOWN)
                           '#6c757d';                         // neutral = same
                });

                // --- Value label colors: same as dot colors (match previous day comparison) ---
                const labelColors = dotColors;

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
                        ctx.font = 'bold 7px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';

                        meta.data.forEach((point, i) => {
                            const val = dataset.data[i];
                            const x = point.x;
                            const y = point.y;

                            // Alternate label position to reduce overlap
                            const offsetY = (i % 2 === 0) ? -7 : -14;

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
                            padding: { top: 18, left: 2, right: 2, bottom: 2 }
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
                                    autoSkip: true,
                                    maxTicksLimit: 30,
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

                    // Populate modal fields
                    $('#editChannelName').val(channel);
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editTarget').val(target);
                    $('#editMissingLink').val(missingLink);
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

            // Show channel history modal
            function showChannelHistory(channelName) {
                console.log('Opening history table for:', channelName);

                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded!');
                    alert('Bootstrap is not loaded. Please refresh the page.');
                    return;
                }

                $('#modalChannelName').text(channelName);
                $('#historyTableBody').html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');

                const modalElement = document.getElementById('channelHistoryModal');
                if (!modalElement) {
                    console.error('channelHistoryModal element not found!');
                    return;
                }

                const modal = new bootstrap.Modal(modalElement);
                modal.show();

                const channelKey = channelName.toLowerCase().replace(/\s+/g, '');

                $.ajax({
                    url: '/channel-master-history/' + channelKey,
                    method: 'GET',
                    success: function(response) {
                        if (response.status === 200 && response.data) {
                            renderHistoryTable(response.data);
                        } else {
                            $('#historyTableBody').html(
                                '<tr><td colspan="5" class="text-center">No historical data found</td></tr>'
                                );
                        }
                    },
                    error: function() {
                        $('#historyTableBody').html(
                            '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>'
                            );
                    }
                });
            }

            // Render history table
            function renderHistoryTable(data) {
                const formatDate = (dateStr) => {
                    if (!dateStr) return '';
                    const datePart = dateStr.split('T')[0];
                    return datePart;
                };

                let html = '';
                data.forEach(row => {
                    const summaryData = row.summary_data || {};
                    const npft = parseNumber(summaryData.gprofit_percent || 0) - parseNumber(summaryData
                        .tcos_percent || 0);
                    const clicks = parseNumber(summaryData.clicks || 0);
                    const totalQty = parseNumber(summaryData.total_quantity || 0);

                    html += `
                        <tr>
                            <td>${formatDate(row.snapshot_date)}</td>
                            <td class="text-end">$${parseNumber(summaryData.l30_sales).toLocaleString()}</td>
                            <td class="text-end">${parseNumber(summaryData.l30_orders).toLocaleString()}</td>
                            <td class="text-end">${totalQty > 0 ? totalQty.toLocaleString() : '-'}</td>
                            <td class="text-end">${clicks > 0 ? clicks.toLocaleString() : '-'}</td>
                            <td class="text-end">${parseNumber(summaryData.gprofit_percent).toFixed(1)}%</td>
                            <td class="text-end">${npft.toFixed(1)}%</td>
                        </tr>
                    `;
                });

                if (html === '') {
                    html = '<tr><td colspan="7" class="text-center">No data available</td></tr>';
                }

                $('#historyTableBody').html(html);
            }

            // Show channel history graph modal
            function showChannelHistoryGraph(channelName) {
                console.log('Opening history graph for:', channelName);

                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap is not loaded!');
                    alert('Bootstrap is not loaded. Please refresh the page.');
                    return;
                }

                $('#modalGraphChannelName').text(channelName);
                $('#loadingGraphMessage').show();
                $('#historyGraphContainer').hide();

                const modalElement = document.getElementById('channelHistoryGraphModal');
                if (!modalElement) {
                    console.error('Modal element not found!');
                    return;
                }

                const modal = new bootstrap.Modal(modalElement);
                modal.show();

                const channelKey = channelName.toLowerCase().replace(/\s+/g, '');

                $.ajax({
                    url: '/channel-master-history/' + channelKey,
                    method: 'GET',
                    success: function(response) {
                        if (response.status === 200 && response.data && response.data.length > 0) {
                            renderHistoryGraph(response.data, channelName);
                        } else {
                            $('#loadingGraphMessage').html(
                                '<p class="text-center text-muted">No historical data found</p>');
                        }
                    },
                    error: function() {
                        $('#loadingGraphMessage').html(
                            '<p class="text-center text-danger">Error loading data</p>');
                    }
                });
            }

            // Render history graph using Google Charts
            function renderHistoryGraph(data, channelName) {
                const toNum = (v, def = 0) => {
                    const n = parseFloat(String(v).replace(/,/g, ''));
                    return Number.isFinite(n) ? n : def;
                };

                const formatDate = (dateStr) => {
                    if (!dateStr) return '';
                    return dateStr.split('T')[0];
                };

                // Prepare data for Google Charts
                const chartData = [
                    ['Date', 'L30 Sales', 'L30 Orders', 'Total Qty', 'Clicks', 'Gprofit%', 'NPFT%']
                ];

                const reversedData = [...data].reverse();

                reversedData.forEach(row => {
                    const summaryData = row.summary_data || {};
                    const npft = toNum(summaryData.gprofit_percent || 0) - toNum(summaryData.tcos_percent ||
                        0);
                    const clicks = toNum(summaryData.clicks || 0);
                    const totalQty = toNum(summaryData.total_quantity || 0);

                    chartData.push([
                        formatDate(row.snapshot_date),
                        toNum(summaryData.l30_sales),
                        toNum(summaryData.l30_orders),
                        totalQty,
                        clicks,
                        toNum(summaryData.gprofit_percent),
                        npft
                    ]);
                });

                // Calculate min/max for dynamic axis scaling
                let minSales = Infinity,
                    maxSales = -Infinity;
                let minOrders = Infinity,
                    maxOrders = -Infinity;
                let minQty = Infinity,
                    maxQty = -Infinity;
                let minClicks = Infinity,
                    maxClicks = -Infinity;
                let minGprofit = Infinity,
                    maxGprofit = -Infinity;
                let minNPFT = Infinity,
                    maxNPFT = -Infinity;

                for (let i = 1; i < chartData.length; i++) {
                    const row = chartData[i];
                    const sales = row[1];
                    const orders = row[2];
                    const qty = row[3];
                    const clicks = row[4];
                    const gprofit = row[5];
                    const npft = row[6];

                    if (sales < minSales) minSales = sales;
                    if (sales > maxSales) maxSales = sales;
                    if (orders < minOrders) minOrders = orders;
                    if (orders > maxOrders) maxOrders = orders;
                    if (qty < minQty) minQty = qty;
                    if (qty > maxQty) maxQty = qty;
                    if (clicks < minClicks) minClicks = clicks;
                    if (clicks > maxClicks) maxClicks = clicks;
                    if (gprofit < minGprofit) minGprofit = gprofit;
                    if (gprofit > maxGprofit) maxGprofit = gprofit;
                    if (npft < minNPFT) minNPFT = npft;
                    if (npft > maxNPFT) maxNPFT = npft;
                }

                // Calculate combined min/max for axes with 15% buffer
                const leftMin = Math.min(minSales, minOrders, minQty, minClicks);
                const leftMax = Math.max(maxSales, maxOrders, maxQty, maxClicks);
                const leftRange = leftMax - leftMin;
                const leftAxisMin = Math.max(0, leftMin - (leftRange * 0.15));
                const leftAxisMax = leftMax + (leftRange * 0.15);

                const rightMin = Math.min(minGprofit, minNPFT);
                const rightMax = Math.max(maxGprofit, maxNPFT);
                const rightRange = rightMax - rightMin;
                const rightAxisMin = Math.max(0, rightMin - (rightRange * 0.15));
                const rightAxisMax = rightMax + (rightRange * 0.15);

                google.charts.load('current', {
                    packages: ['corechart', 'line']
                });
                google.charts.setOnLoadCallback(() => {
                    const dataTable = google.visualization.arrayToDataTable(chartData);

                    const options = {
                        title: `${channelName} - Historical Trends (Last ${data.length} Days)`,
                        titleTextStyle: {
                            fontSize: 18,
                            bold: true,
                            color: '#333333'
                        },
                        curveType: 'none',
                        legend: {
                            position: 'bottom',
                            textStyle: {
                                fontSize: 12
                            },
                            alignment: 'center'
                        },
                        interpolateNulls: false,
                        hAxis: {
                            title: 'Date',
                            titleTextStyle: {
                                fontSize: 13,
                                bold: true,
                                color: '#333'
                            },
                            slantedText: true,
                            slantedTextAngle: 45,
                            textStyle: {
                                fontSize: 11,
                                color: '#333',
                                bold: true
                            },
                            gridlines: {
                                color: '#e0e0e0',
                                count: -1
                            },
                            minorGridlines: {
                                color: '#f5f5f5',
                                count: 2
                            },
                            baselineColor: '#999',
                            showTextEvery: 1
                        },
                        series: {
                            0: {
                                color: '#1e88e5',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 0,
                                visibleInLegend: true
                            },
                            1: {
                                color: '#ff9800',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 0,
                                visibleInLegend: true
                            },
                            2: {
                                color: '#00bcd4',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 0,
                                visibleInLegend: true
                            },
                            3: {
                                color: '#9c27b0',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 0,
                                visibleInLegend: true
                            },
                            4: {
                                color: '#e53935',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 1,
                                visibleInLegend: true
                            },
                            5: {
                                color: '#43a047',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 1,
                                visibleInLegend: true
                            }
                        },
                        vAxes: {
                            0: {
                                title: 'Sales, Orders, Qty & Clicks',
                                titleTextStyle: {
                                    color: '#1e88e5',
                                    fontSize: 14,
                                    bold: true
                                },
                                textStyle: {
                                    color: '#000000',
                                    fontSize: 14,
                                    bold: true
                                },
                                gridlines: {
                                    color: '#d0d0d0',
                                    count: 6
                                },
                                format: 'short',
                                viewWindow: {
                                    min: leftAxisMin,
                                    max: leftAxisMax
                                }
                            },
                            1: {
                                title: 'Profit % (Gprofit% & NPFT%)',
                                titleTextStyle: {
                                    color: '#e53935',
                                    fontSize: 14,
                                    bold: true
                                },
                                textStyle: {
                                    color: '#000000',
                                    fontSize: 14,
                                    bold: true
                                },
                                gridlines: {
                                    color: '#d0d0d0',
                                    count: 6
                                },
                                format: 'short',
                                viewWindow: {
                                    min: rightAxisMin,
                                    max: rightAxisMax
                                }
                            }
                        },
                        chartArea: {
                            left: 120,
                            top: 80,
                            right: 150,
                            bottom: 120,
                            backgroundColor: '#ffffff'
                        },
                        backgroundColor: {
                            fill: '#ffffff',
                            strokeWidth: 1,
                            stroke: '#cccccc'
                        },
                        tooltip: {
                            isHtml: false,
                            textStyle: {
                                fontSize: 11
                            },
                            showColorCode: true
                        },
                        animation: {
                            startup: true,
                            duration: 800,
                            easing: 'out'
                        },
                        pointsVisible: true
                    };

                    $('#loadingGraphMessage').hide();
                    $('#historyGraphContainer').show();

                    if (data.length < 30) {
                        $('#dataPointsInfo').text(
                            `Showing ${data.length} days of available data. Data accumulates daily.`);
                        $('#dataInfoMessage').show();
                    } else {
                        $('#dataInfoMessage').hide();
                    }

                    const chart = new google.visualization.LineChart(document.getElementById(
                        'historyGraphContainer'));
                    chart.draw(dataTable, options);

                    // Redraw on window resize
                    window.addEventListener('resize', function() {
                        chart.draw(dataTable, options);
                    });

                    // Redraw after modal is fully shown
                    setTimeout(function() {
                        chart.draw(dataTable, options);
                    }, 300);
                });
            }

            // Save channel form handler
            $(document).on('click', '#saveChannelBtn', function() {
                const channelName = $('#channelName').val().trim();
                const channelUrl = $('#channelUrl').val().trim();
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
