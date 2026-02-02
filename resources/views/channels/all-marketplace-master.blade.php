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
                    <select id="type-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
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

                    <button id="addChannelBtn" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addChannelModal">
                        <i class="fas fa-plus-circle"></i> Add Channel
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            Total Channels: <span id="total-channels">0</span>
                        </span>
                        <span class="badge bg-success fs-6 p-2" style="color: black; font-weight: bold;">
                            L30 Sales: <span id="total-l30-sales">$0</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;">
                            L30 Orders: <span id="total-l30-orders">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            Total Qty: <span id="total-qty">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;">
                            Avg Gprofit%: <span id="avg-gprofit">0%</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2" style="color: white; font-weight: bold;">
                            Avg G ROI%: <span id="avg-groi">0%</span>
                        </span>
                        <span class="badge bg-secondary fs-6 p-2" style="color: white; font-weight: bold;">
                            Total Ad Spend: <span id="total-ad-spend">$0</span>
                        </span>
                        <span class="badge bg-dark fs-6 p-2" style="color: white; font-weight: bold;">
                            Avg N PFT%: <span id="avg-npft">0%</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                            Avg N ROI%: <span id="avg-nroi">0%</span>
                        </span>
                        <span class="badge bg-info fs-6 p-2" style="color: black; font-weight: bold;">
                            Total Clicks: <span id="total-clicks">0</span>
                        </span>
                        <span class="badge bg-warning fs-6 p-2" style="color: black; font-weight: bold;">
                             Map: <span id="total-map">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2" style="color: white; font-weight: bold;">
                             NMap: <span id="total-nmap">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2" style="color: white; font-weight: bold;">
                             Missing: <span id="total-miss">0</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="marketplace-table-wrapper" style="height: calc(100vh - 300px); display: flex; flex-direction: column;">
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
                            <label for="editBase" class="form-label">Achieved</label>
                            <input type="number" class="form-control" id="editBase" step="0.01">
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

    <!-- Missing Ads Breakdown Modal -->
    <div class="modal fade" id="missingAdsBreakdownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
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
                initialSort: [
                    {column: "L30 Sales", dir: "desc"}
                ],
                ajaxResponse: function(url, params, response) {
                    if (response && response.data) {
                        updateSummaryStats(response.data);
                        return response.data;
                    }
                    return [];
                },
                columns: [
                    {
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
                            
                            const historyIcon = `<i class="fas fa-chart-line history-table-icon" style="cursor:pointer;color:#4361ee;font-size:14px;margin-left:5px;" title="View Historical Table" data-channel="${channel}"></i>`;
                            const graphIcon = `<i class="fas fa-chart-area history-graph-icon" style="cursor:pointer;color:#28a745;font-size:14px;margin-left:5px;" title="View Historical Graph" data-channel="${channel}"></i>`;
                            
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
                        title: "Missing",
                        field: "Miss",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const missingLink = rowData['missing_link'] || '';
                            
                            // Simple black text - no background colors
                            const style = 'color:black;font-weight:600;';
                            
                            // Make clickable if missing_link exists
                            if (missingLink && value > 0) {
                                return `<a href="${missingLink}" target="_blank" style="${style}text-decoration:none;cursor:pointer;" title="Click to view missing items details">${value}</a>`;
                            }
                            
                            return `<span style="${style}">${value}</span>`;
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
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            
                            // Simple black text - no background colors
                            const style = 'color:black;font-weight:600;';
                            
                            return `<span style="${style}">${value}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "NMap",
                        field: "NMap",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            
                            // Badge with color coding based on value
                            let bg = '', color = 'white';
                            if (value === 0) { 
                                bg = '#00ff00'; 
                                color = 'black'; 
                            } else if (value <= 50) { 
                                bg = '#ffff00'; 
                                color = 'black'; 
                            } else if (value <= 100) { 
                                bg = '#ffa500'; 
                            } else { 
                                bg = '#ff0000'; 
                            }
                            
                            const badgeStyle = `background:${bg};color:${color};padding:4px 10px;border-radius:6px;font-weight:bold;font-size:11px;display:inline-block;min-width:35px;text-align:center;`;
                            
                            return `<span style="${badgeStyle}">${value}</span>`;
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
                            return `<span style="font-weight: 600;">$${value.toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Growth",
                        field: "Growth",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const l30Sales = parseNumber(rowData['L30 Sales']);
                            const achieved = parseNumber(rowData['base']);
                            const growthPercent = achieved > 0 ? ((l30Sales - achieved) / achieved) * 100 : 0;
                            
                            let style = '';
                            if (growthPercent < 0) { 
                                style = 'color:#a00211;'; // Red text
                            } else if (growthPercent === 0) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else { 
                                style = 'color:#28a745;'; // Green text
                            }
                            
                            return `<span style="${style}font-weight:600;">${growthPercent < 0 ? '' : '+'}${growthPercent.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "Ads Clicks",
                        field: "clicks",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            
                            if (value === 0) return '-';
                            
                            // Add info icon for channels that might have PT/KW/HL breakdown
                            const infoIcon = `<i class="fas fa-info-circle clicks-info-icon ms-1" 
                                data-channel="${channel}" 
                                style="cursor:pointer;color:#17a2b8;font-size:12px;" 
                                title="View Clicks Breakdown"></i>`;
                            
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('clicks-info-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                showClicksBreakdown(channelName);
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Ad Sales",
                        field: "Ad Sales",
                        hozAlign: "center",
                        sorter: "number",
                        width: 120,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            
                            if (!value || value === 0) return '-';
                            
                            // Add info icon for channels that might have PT/KW/HL breakdown
                            const infoIcon = `<i class="fas fa-info-circle sales-info-icon ms-1" 
                                data-channel="${channel}" 
                                style="cursor:pointer;color:#17a2b8;font-size:12px;" 
                                title="View Ad Sales Breakdown"></i>`;
                            
                            // Round to whole number, no decimals
                            return `<span style="font-weight:600;">$${Math.round(value).toLocaleString('en-US')}</span>${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('sales-info-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                showAdSalesBreakdown(channelName); // Show Ad Sales specific modal
                            }
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            if (!value || value === 0) return '<strong>-</strong>';
                            return `<strong>$${Math.round(parseNumber(value)).toLocaleString('en-US')}</strong>`;
                        }
                    },
                    {
                        title: "Ads CVR",
                        field: "Ads CVR",
                        hozAlign: "center",
                        sorter: "number",
                        width: 100,
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim();
                            
                            if (!value || value === 0) return '-';
                            
                            // Add info icon for channels that might have PT/KW/HL breakdown
                            const infoIcon = `<i class="fas fa-info-circle cvr-info-icon ms-1" 
                                data-channel="${channel}" 
                                style="cursor:pointer;color:#17a2b8;font-size:12px;" 
                                title="View Ads CVR Breakdown"></i>`;
                            
                            return `<span style="font-weight:600;">${Math.round(value)}%</span>${infoIcon}`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('cvr-info-icon')) {
                                e.stopPropagation();
                                const channelName = $(e.target).data('channel');
                                showCvrBreakdown(channelName); // Show CVR specific modal
                            }
                        },
                        bottomCalc: function(values, data, calcParams) {
                            // Custom calculation for total CVR
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
                    {
                        title: "L Missing Ads",
                        field: "Missing Ads",
                        hozAlign: "center",
                        sorter: "number",
                        width: 110,
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
                                showMissingAdsBreakdown(channelName); // Show Missing Ads specific modal
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
                            return `<span>${value.toLocaleString('en-US')}</span>`;
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
                            return `<span>${value.toLocaleString('en-US')}</span>`;
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
                            let style = '';
                            
                            if (value >= 0 && value <= 10) { 
                                style = 'color:#a00211;'; // Red text
                            } else if (value > 10 && value <= 18) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else if (value > 18 && value <= 25) { 
                                style = 'color:#3591dc;'; // Blue text
                            } else if (value > 25 && value <= 40) { 
                                style = 'color:#28a745;'; // Green text
                            } else { 
                                style = 'color:#e83e8c;'; // Pink text
                            }
                            
                            return `<span style="${style}font-weight:600;">${value.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "G ROI%",
                        field: "G Roi",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            let style = '';
                            
                            if (value <= 50) { 
                                style = 'color:#a00211;'; // Red text
                            } else if (value > 50 && value <= 75) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else if (value > 75 && value <= 125) { 
                                style = 'color:#28a745;'; // Green text
                            } else { 
                                style = 'color:#8000ff;'; // Purple text
                            }
                            
                            return `<span style="${style}font-weight:600;">${value.toFixed(0)}%</span>`;
                        }
                    },
                   
                    {
                        title: "Ads%",
                        field: "Ads%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim().toLowerCase();
                            
                            // For Walmart, Temu, and Shopify B2C, use TACOS %
                            let adsPercent = 0;
                            if (channel === 'walmart' || channel === 'temu' || channel === 'shopifyb2c') {
                                adsPercent = parseNumber(rowData['TACOS %'] || 0);
                            } else {
                                adsPercent = parseNumber(cell.getValue() || 0);
                            }
                            
                            let style = '';
                            if (adsPercent < 5) { 
                                style = 'color:#e83e8c;'; // Pink text
                            } else if (adsPercent >= 5 && adsPercent <= 10) { 
                                style = 'color:#28a745;'; // Green text
                            } else { 
                                style = 'color:#a00211;'; // Red text
                            }
                            
                            return `<span style="${style}font-weight:600;">${adsPercent.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "N PFT%",
                        field: "N PFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            let style = '';
                            
                            if (value >= 0 && value <= 10) { 
                                style = 'color:#a00211;'; // Red text
                            } else if (value > 10 && value <= 18) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else if (value > 18 && value <= 25) { 
                                style = 'color:#3591dc;'; // Blue text
                            } else if (value > 25 && value <= 40) { 
                                style = 'color:#28a745;'; // Green text
                            } else { 
                                style = 'color:#e83e8c;'; // Pink text
                            }
                            
                            return `<span style="${style}font-weight:600;">${value.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "N ROI%",
                        field: "N ROI",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            let style = '';
                            
                            if (value <= 50) { 
                                style = 'color:#a00211;'; // Red text
                            } else if (value > 50 && value <= 75) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else if (value > 75 && value <= 125) { 
                                style = 'color:#28a745;'; // Green text
                            } else { 
                                style = 'color:#8000ff;'; // Purple text
                            }
                            
                            return `<span style="${style}font-weight:600;">${value.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "Ad Spend",
                        field: "Total Ad Spend",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const calcTotal = (row) => {
                                const channel = (row['Channel '] || '').trim().toLowerCase();
                                if (channel === 'tiktok shop') return parseNumber(row['TikTok Ad Spend'] || 0);
                                const kwSpent = parseNumber(row['KW Spent'] || 0);
                                const pmtSpent = parseNumber(row['PMT Spent'] || 0);
                                const hlSpent = parseNumber(row['HL Spent'] || 0);
                                const walmartSpent = parseNumber(row['Walmart Spent'] || 0);
                                if (channel === 'walmart') return walmartSpent;
                                if (channel === 'temu' || channel === 'shopifyb2c') return kwSpent;
                                return kwSpent + pmtSpent + hlSpent;
                            };
                            return calcTotal(aRow.getData()) - calcTotal(bRow.getData());
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim().toLowerCase();
                            const kwSpent = parseNumber(rowData['KW Spent'] || 0);
                            const pmtSpent = parseNumber(rowData['PMT Spent'] || 0);
                            const hlSpent = parseNumber(rowData['HL Spent'] || 0);
                            const walmartSpent = parseNumber(rowData['Walmart Spent'] || 0);
                            const tiktokAdSpend = parseNumber(rowData['TikTok Ad Spend'] || 0);
                            
                            // For Walmart, use Walmart Spent as total
                            // For Temu and Shopify B2C, use KW Spent as total (Google Ads/Temu ad spend is stored in KW Spent field)
                            // For Tiktok Shop, use TikTok Ad Spend from tiktok_campaign_reports
                            let totalSpent = 0;
                            if (channel === 'walmart') {
                                totalSpent = walmartSpent;
                            } else if (channel === 'temu' || channel === 'shopifyb2c') {
                                totalSpent = kwSpent;
                            } else if (channel === 'tiktok shop') {
                                totalSpent = tiktokAdSpend;
                            } else {
                                totalSpent = kwSpent + pmtSpent + hlSpent;
                            }
                            
                            if (totalSpent === 0) return '-';
                            
                            // For Tiktok Shop, show TikTok Ad Spend (from tiktok_campaign_reports)
                            if (channel === 'tiktok shop') {
                                return `
                                    <select class="form-select form-select-sm ad-spend-select"
                                            style="min-width: 90px;
                                                   font-size: 10px;
                                                   padding: 3px 6px;
                                                   background-color: #91e1ff;
                                                   color: black;
                                                   border: 1px solid #91e1ff;
                                                   font-weight: bold;">
                                        <option value="total" selected style="background-color: #91e1ff; color: black; font-weight: bold;">$${Math.round(totalSpent).toLocaleString('en-US')}</option>
                                        <option value="tiktok" style="background-color: #000000; color: white; font-weight: bold;">TT: $${tiktokAdSpend.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                    </select>
                                `;
                            }

                            // For Walmart, show single option
                            if (channel === 'walmart') {
                                return `
                                    <select class="form-select form-select-sm ad-spend-select"
                                            style="min-width: 90px;
                                                   font-size: 10px;
                                                   padding: 3px 6px;
                                                   background-color: #91e1ff;
                                                   color: black;
                                                   border: 1px solid #91e1ff;
                                                   font-weight: bold;">
                                        <option value="total" selected style="background-color: #91e1ff; color: black; font-weight: bold;">$${Math.round(totalSpent).toLocaleString('en-US')}</option>
                                        <option value="walmart" style="background-color: #0071ce; color: white; font-weight: bold;">WM: $${walmartSpent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                    </select>
                                `;
                            }

                            // For Temu, show single option
                            if (channel === 'temu') {
                                return `
                                    <select class="form-select form-select-sm ad-spend-select"
                                            style="min-width: 90px;
                                                   font-size: 10px;
                                                   padding: 3px 6px;
                                                   background-color: #91e1ff;
                                                   color: black;
                                                   border: 1px solid #91e1ff;
                                                   font-weight: bold;">
                                        <option value="total" selected style="background-color: #91e1ff; color: black; font-weight: bold;">$${Math.round(totalSpent).toLocaleString('en-US')}</option>
                                        <option value="temu" style="background-color: #ff6600; color: white; font-weight: bold;">TM: $${kwSpent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                    </select>
                                `;
                            }

                            // For Shopify B2C, show single option (Google Ads)
                            if (channel === 'shopifyb2c') {
                                return `
                                    <select class="form-select form-select-sm ad-spend-select"
                                            style="min-width: 90px;
                                                   font-size: 10px;
                                                   padding: 3px 6px;
                                                   background-color: #91e1ff;
                                                   color: black;
                                                   border: 1px solid #91e1ff;
                                                   font-weight: bold;">
                                        <option value="total" selected style="background-color: #91e1ff; color: black; font-weight: bold;">$${Math.round(totalSpent).toLocaleString('en-US')}</option>
                                        <option value="google" style="background-color: #4285f4; color: white; font-weight: bold;">G: $${kwSpent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                    </select>
                                `;
                            }

                            // For other channels, show KW/PMT/HL breakdown
                            return `
                                <select class="form-select form-select-sm ad-spend-select"
                                        style="min-width: 90px;
                                               font-size: 10px;
                                               padding: 3px 6px;
                                               background-color: #91e1ff;
                                               color: black;
                                               border: 1px solid #91e1ff;
                                               font-weight: bold;">
                                    <option value="total" selected style="background-color: #91e1ff; color: black; font-weight: bold;">$${Math.round(totalSpent).toLocaleString('en-US')}</option>
                                    <option value="kw" style="background-color: #198754; color: white; font-weight: bold;">K: $${kwSpent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                    <option value="pmt" style="background-color: #ffc107; color: black; font-weight: bold;">P: $${pmtSpent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                    <option value="hl" style="background-color: #dc3545; color: white; font-weight: bold;">H: $${hlSpent.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</option>
                                </select>
                            `;
                        },
                        bottomCalc: function(values, data) {
                            let total = 0;
                            data.forEach(row => {
                                const channel = (row['Channel '] || '').trim().toLowerCase();
                                const kwSpent = parseNumber(row['KW Spent'] || 0);
                                const pmtSpent = parseNumber(row['PMT Spent'] || 0);
                                const hlSpent = parseNumber(row['HL Spent'] || 0);
                                const walmartSpent = parseNumber(row['Walmart Spent'] || 0);
                                const tiktokAdSpend = parseNumber(row['TikTok Ad Spend'] || 0);
                                
                                // For Walmart, Temu, Shopify B2C, Tiktok Shop - only count their specific spend field
                                if (channel === 'walmart') {
                                    total += walmartSpent;
                                } else if (channel === 'temu' || channel === 'shopifyb2c') {
                                    total += kwSpent; // Temu/Google Ads spend is stored in KW Spent
                                } else if (channel === 'tiktok shop') {
                                    total += tiktokAdSpend; // TikTok Ad Spend from tiktok_campaign_reports
                                } else {
                                    total += kwSpent + pmtSpent + hlSpent;
                                }
                            });
                            return total;
                        },
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseNumber(value).toFixed(0)}</strong>`;
                        }
                    },
                    {
                        title: "Achieved",
                        field: "base",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            return `<span>${value.toLocaleString('en-US')}</span>`;
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
                            if ($target.hasClass('edit-channel-btn') || $target.closest('.edit-channel-btn').length) {
                                const $btn = $target.hasClass('edit-channel-btn') ? $target : $target.closest('.edit-channel-btn');
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
                    const base = rowData['base'] || 0;
                    const target = rowData['target'] || 0;
                    const missingLink = rowData['missing_link'] || '';
                    
                    // Populate modal
                    $('#editChannelName').val(channel);
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editBase').val(base);
                    $('#editTarget').val(target);
                    $('#editMissingLink').val(missingLink);
                    $('#originalChannel').val(channel);
                                    
                                    // Open modal
                                    const modalElement = document.getElementById('editChannelModal');
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
                            if ($target.hasClass('delete-channel-btn') || $target.closest('.delete-channel-btn').length) {
                                const $btn = $target.hasClass('delete-channel-btn') ? $target : $target.closest('.delete-channel-btn');
                                const channel = $btn.data('channel');
                                
                                if (confirm(`Are you sure you want to archive channel: ${channel}?`)) {
                                    showToast('info', 'Archive functionality coming soon');
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
                            const value = parseNumber(cell.getValue());
                            return `<span>$${value.toFixed(0)}</span>`;
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
                    
                    // Ad spend - handle Walmart, Temu, Shopify B2C, Tiktok Shop separately to avoid double counting
                    const kwSpent = parseNumber(row['KW Spent'] || 0);
                    const pmtSpent = parseNumber(row['PMT Spent'] || 0);
                    const hlSpent = parseNumber(row['HL Spent'] || 0);
                    const walmartSpent = parseNumber(row['Walmart Spent'] || 0);
                    const tiktokAdSpend = parseNumber(row['TikTok Ad Spend'] || 0);
                    
                    let adSpend = 0;
                    if (channel === 'walmart') {
                        adSpend = walmartSpent;
                    } else if (channel === 'temu' || channel === 'shopifyb2c') {
                        adSpend = kwSpent; // Temu/Google Ads spend is stored in KW Spent
                    } else if (channel === 'tiktok shop') {
                        adSpend = tiktokAdSpend; // TikTok Ad Spend from tiktok_campaign_reports
                    } else {
                        adSpend = kwSpent + pmtSpent + hlSpent;
                    }
                    
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
                    li.innerHTML = `<label class="dropdown-item"><input type="checkbox" ${isVisible ? 'checked' : ''} data-field="${field}"> ${def.title}</label>`;
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
                table.download("csv", "all_marketplace_master_" + new Date().toISOString().split('T')[0] + ".csv");
            });

            // Refresh Data
            document.getElementById("refresh-btn").addEventListener("click", function() {
                table.setData();
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
                $('#clicksBreakdownTableBody').html('<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-info" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>');
                
                // Reset totals
                $('#clicksBreakdownTotalClicks').text('0');
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('clicksBreakdownModal'));
                modal.show();
                
                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: { channel: channelName },
                    success: function(response) {
                        console.log('Clicks breakdown response:', response);
                        
                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalClicks = 0;
                            
                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = { 'PT': 1, 'KW': 2, 'HL': 3 };
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
                            $('#clicksBreakdownTableBody').html('<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching clicks breakdown:', xhr);
                        $('#clicksBreakdownTableBody').html('<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>');
                    }
                });
            }

            // Show Ad Sales Breakdown Modal (Ad Sales focused)
            function showAdSalesBreakdown(channelName) {
                console.log('Opening ad sales breakdown for:', channelName);
                
                // Set modal title
                $('#salesModalChannelName').text(channelName);
                
                // Show loading state
                $('#adSalesBreakdownTableBody').html('<tr><td colspan="4" class="text-center"><div class="spinner-border spinner-border-sm text-success" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>');
                
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
                    data: { channel: channelName },
                    success: function(response) {
                        console.log('Ad sales breakdown response:', response);
                        
                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalSpent = 0;
                            let totalAdSales = 0;
                            
                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = { 'PT': 1, 'KW': 2, 'HL': 3 };
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
                            const totalAcos = totalAdSales > 0 ? ((totalSpent / totalAdSales) * 100).toFixed(2) : 0;
                            // TACOS = (spent / parent_l30_sales) * 100
                            const parentL30Sales = parseFloat(response.parent_l30_sales) || 0;
                            const totalTacos = parentL30Sales > 0 ? ((totalSpent / parentL30Sales) * 100).toFixed(2) : 0;
                            
                            // Update totals
                            $('#adSalesBreakdownTotalSales').text('$' + Math.round(totalAdSales).toLocaleString('en-US'));
                            $('#adSalesBreakdownTotalAcos').text(totalAcos > 0 ? totalAcos + '%' : '-');
                            $('#adSalesBreakdownTotalTacos').text(totalTacos > 0 ? totalTacos + '%' : '-');
                        } else {
                            $('#adSalesBreakdownTableBody').html('<tr><td colspan="4" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching ad sales breakdown:', xhr);
                        $('#adSalesBreakdownTableBody').html('<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>');
                    }
                });
            }

            // Show CVR Breakdown Modal (CVR only)
            function showCvrBreakdown(channelName) {
                console.log('Opening CVR breakdown for:', channelName);
                
                // Set modal title
                $('#cvrModalChannelName').text(channelName);
                
                // Show loading state
                $('#cvrBreakdownTableBody').html('<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>');
                
                // Reset totals
                $('#cvrBreakdownTotalCvr').text('-');
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('cvrBreakdownModal'));
                modal.show();
                
                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: { channel: channelName },
                    success: function(response) {
                        console.log('CVR breakdown response:', response);
                        
                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalClicks = 0;
                            let totalAdSold = 0;
                            
                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = { 'PT': 1, 'KW': 2, 'HL': 3 };
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
                            const totalCvr = totalClicks > 0 ? Math.round((totalAdSold / totalClicks) * 100) : 0;
                            
                            // Update totals
                            $('#cvrBreakdownTotalCvr').text(totalCvr > 0 ? totalCvr + '%' : '-');
                        } else {
                            $('#cvrBreakdownTableBody').html('<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching CVR breakdown:', xhr);
                        $('#cvrBreakdownTableBody').html('<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>');
                    }
                });
            }

            // Show Missing Ads Breakdown Modal (Missing Ads only)
            function showMissingAdsBreakdown(channelName) {
                console.log('Opening missing ads breakdown for:', channelName);
                
                // Set modal title
                $('#missingModalChannelName').text(channelName);
                
                // Show loading state
                $('#missingAdsBreakdownTableBody').html('<tr><td colspan="2" class="text-center"><div class="spinner-border spinner-border-sm text-danger" role="status"><span class="visually-hidden">Loading...</span></div> Loading...</td></tr>');
                
                // Reset totals
                $('#missingAdsBreakdownTotal').text('0');
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('missingAdsBreakdownModal'));
                modal.show();
                
                // Fetch breakdown data from backend
                $.ajax({
                    url: '/channel-clicks-breakdown',
                    method: 'GET',
                    data: { channel: channelName },
                    success: function(response) {
                        console.log('Missing ads breakdown response:', response);
                        
                        if (response.success && response.data && response.data.length > 0) {
                            let html = '';
                            let totalMissingAds = 0;
                            
                            // Sort by type (PT, KW, HL)
                            response.data.sort((a, b) => {
                                const orderMap = { 'PT': 1, 'KW': 2, 'HL': 3 };
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
                            $('#missingAdsBreakdownTotal').text(totalMissingAds.toLocaleString('en-US'));
                        } else {
                            $('#missingAdsBreakdownTableBody').html('<tr><td colspan="2" class="text-center text-muted">No PT/KW/HL breakdown available for this channel</td></tr>');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching missing ads breakdown:', xhr);
                        $('#missingAdsBreakdownTableBody').html('<tr><td colspan="2" class="text-center text-danger">Error loading data</td></tr>');
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
                    const base = rowData['base'] || 0;
                    const target = rowData['target'] || 0;
                    const missingLink = rowData['missing_link'] || '';
                    
                    // Populate modal fields
                    $('#editChannelName').val(channel);
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editBase').val(base);
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
                
                if (confirm(`Are you sure you want to archive channel: ${channel}?`)) {
                    showToast('info', 'Archive functionality coming soon');
                    // TODO: Implement archive functionality
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
                            $('#historyTableBody').html('<tr><td colspan="5" class="text-center">No historical data found</td></tr>');
                        }
                    },
                    error: function() {
                        $('#historyTableBody').html('<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>');
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
                    const npft = parseNumber(summaryData.gprofit_percent || 0) - parseNumber(summaryData.tcos_percent || 0);
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
                            $('#loadingGraphMessage').html('<p class="text-center text-muted">No historical data found</p>');
                        }
                    },
                    error: function() {
                        $('#loadingGraphMessage').html('<p class="text-center text-danger">Error loading data</p>');
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
                    const npft = toNum(summaryData.gprofit_percent || 0) - toNum(summaryData.tcos_percent || 0);
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
                let minSales = Infinity, maxSales = -Infinity;
                let minOrders = Infinity, maxOrders = -Infinity;
                let minQty = Infinity, maxQty = -Infinity;
                let minClicks = Infinity, maxClicks = -Infinity;
                let minGprofit = Infinity, maxGprofit = -Infinity;
                let minNPFT = Infinity, maxNPFT = -Infinity;

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

                google.charts.load('current', { packages: ['corechart', 'line'] });
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
                            textStyle: { fontSize: 12 },
                            alignment: 'center'
                        },
                        interpolateNulls: false,
                        hAxis: {
                            title: 'Date',
                            titleTextStyle: { fontSize: 13, bold: true, color: '#333' },
                            slantedText: true,
                            slantedTextAngle: 45,
                            textStyle: { fontSize: 11, color: '#333', bold: true },
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
                            textStyle: { fontSize: 11 },
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
                        $('#dataPointsInfo').text(`Showing ${data.length} days of available data. Data accumulates daily.`);
                        $('#dataInfoMessage').show();
                    } else {
                        $('#dataInfoMessage').hide();
                    }

                    const chart = new google.visualization.LineChart(document.getElementById('historyGraphContainer'));
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
                            const modal = bootstrap.Modal.getInstance(document.getElementById('addChannelModal'));
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
                const base = $('#editBase').val().trim();
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
                        base: base,
                        target: target,
                        missing_link: missingLink,
                        original_channel: originalChannel,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('editChannelModal'));
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
