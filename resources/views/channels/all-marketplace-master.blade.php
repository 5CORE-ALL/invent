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
                        <span class="badge bg-success fs-6 p-2" style="color: white; font-weight: bold;">
                             Map: <span id="total-map">0</span>
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
                                    <th class="text-end">Clicks</th>
                                    <th class="text-end">Gprofit%</th>
                                    <th class="text-end">NPFT%</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="6" class="text-center">Loading...</td>
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
                        title: "Clicks",
                        field: "clicks",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            if (value === 0) return '-';
                            return `<span style="font-weight:600;">${value.toLocaleString('en-US')}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
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
                        title: "Missing",
                        field: "Miss",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseNumber(cell.getValue());
                            let style = '';
                            
                            if (value === 0) { 
                                style = 'color:#28a745;'; // Green text
                            } else if (value <= 20) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else if (value <= 50) { 
                                style = 'color:#ff6f00;'; // Dark Orange text
                            } else { 
                                style = 'color:#a00211;'; // Red text
                            }
                            
                            return `<span style="${style}font-weight:600;">${value}</span>`;
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
                            let style = '';
                            
                            if (value === 0) { 
                                style = 'color:#28a745;'; // Green text
                            } else if (value <= 20) { 
                                style = 'background:#ffc107;color:black;padding:4px 8px;border-radius:4px;'; // Yellow bg with black text
                            } else if (value <= 50) { 
                                style = 'color:#ff6f00;'; // Dark Orange text
                            } else { 
                                style = 'color:#a00211;'; // Red text
                            }
                            
                            return `<span style="${style}font-weight:600;">${value}</span>`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseNumber(value).toLocaleString('en-US')}</strong>`;
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
                                const kwSpent = parseNumber(row['KW Spent'] || 0);
                                const pmtSpent = parseNumber(row['PMT Spent'] || 0);
                                const hlSpent = parseNumber(row['HL Spent'] || 0);
                                const walmartSpent = parseNumber(row['Walmart Spent'] || 0);
                                return kwSpent + pmtSpent + hlSpent + walmartSpent;
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
                            
                            // For Walmart, use Walmart Spent as total
                            // For Temu and Shopify B2C, use KW Spent as total (Google Ads/Temu ad spend is stored in KW Spent field)
                            let totalSpent = 0;
                            if (channel === 'walmart') {
                                totalSpent = walmartSpent;
                            } else if (channel === 'temu' || channel === 'shopifyb2c') {
                                totalSpent = kwSpent;
                            } else {
                                totalSpent = kwSpent + pmtSpent + hlSpent;
                            }
                            
                            if (totalSpent === 0) return '-';
                            
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
                                
                                // For Walmart, Temu, and Shopify B2C, only count their specific spend field to avoid double counting
                                if (channel === 'walmart') {
                                    total += walmartSpent;
                                } else if (channel === 'temu' || channel === 'shopifyb2c') {
                                    total += kwSpent; // Temu/Google Ads spend is stored in KW Spent
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
                                    
                                    // Populate modal
                                    $('#editChannelName').val(channel);
                                    $('#editChannelUrl').val(sheetUrl);
                                    $('#editType').val(type);
                                    $('#editBase').val(base);
                                    $('#editTarget').val(target);
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
                let totalClicks = 0;
                let totalPft = 0;
                let totalCogs = 0;
                let totalAdSpend = 0;
                let totalMap = 0;
                let totalMiss = 0;
                let gprofitSum = 0;
                let groiSum = 0;
                let npftSum = 0;
                let nroiSum = 0;
                let validChannels = 0;
                
                data.forEach(row => {
                    const channel = (row['Channel '] || '').trim().toLowerCase();
                    const l30Sales = parseNumber(row['L30 Sales'] || 0);
                    const l30Orders = parseNumber(row['L30 Orders'] || 0);
                    const clicks = parseNumber(row['clicks'] || 0);
                    const gprofitPercent = parseNumber(row['Gprofit%'] || 0);
                    const groi = parseNumber(row['G Roi'] || 0);
                    const npft = parseNumber(row['N PFT'] || 0);
                    const nroi = parseNumber(row['N ROI'] || 0);
                    const cogs = parseNumber(row['cogs'] || 0);
                    const mapCount = parseNumber(row['Map'] || 0);
                    const missCount = parseNumber(row['Miss'] || 0);
                    
                    // Ad spend - handle Walmart, Temu, and Shopify B2C separately to avoid double counting
                    const kwSpent = parseNumber(row['KW Spent'] || 0);
                    const pmtSpent = parseNumber(row['PMT Spent'] || 0);
                    const hlSpent = parseNumber(row['HL Spent'] || 0);
                    const walmartSpent = parseNumber(row['Walmart Spent'] || 0);
                    
                    let adSpend = 0;
                    if (channel === 'walmart') {
                        adSpend = walmartSpent;
                    } else if (channel === 'temu' || channel === 'shopifyb2c') {
                        adSpend = kwSpent; // Temu/Google Ads spend is stored in KW Spent
                    } else {
                        adSpend = kwSpent + pmtSpent + hlSpent;
                    }
                    
                    totalL30Sales += l30Sales;
                    totalL30Orders += l30Orders;
                    totalClicks += clicks;
                    totalAdSpend += adSpend;
                    totalCogs += cogs;
                    totalMap += mapCount;
                    totalMiss += missCount;
                    
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
                $('#total-clicks').text(Math.round(totalClicks).toLocaleString('en-US'));
                $('#avg-gprofit').text(avgGprofit.toFixed(1) + '%');
                $('#avg-groi').text(avgGroi.toFixed(1) + '%');
                $('#total-ad-spend').text('$' + Math.round(totalAdSpend).toLocaleString('en-US'));
                $('#avg-npft').text(avgNpft.toFixed(1) + '%');
                $('#avg-nroi').text(avgNroi.toFixed(1) + '%');
                $('#total-map').text(Math.round(totalMap).toLocaleString('en-US'));
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
                    
                    // Populate modal fields
                    $('#editChannelName').val(channel);
                    $('#editChannelUrl').val(sheetUrl);
                    $('#editType').val(type);
                    $('#editBase').val(base);
                    $('#editTarget').val(target);
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
                    html += `
                        <tr>
                            <td>${formatDate(row.snapshot_date)}</td>
                            <td class="text-end">$${parseNumber(summaryData.l30_sales).toLocaleString()}</td>
                            <td class="text-end">${parseNumber(summaryData.l30_orders).toLocaleString()}</td>
                            <td class="text-end">${clicks > 0 ? clicks.toLocaleString() : '-'}</td>
                            <td class="text-end">${parseNumber(summaryData.gprofit_percent).toFixed(1)}%</td>
                            <td class="text-end">${npft.toFixed(1)}%</td>
                        </tr>
                    `;
                });
                
                if (html === '') {
                    html = '<tr><td colspan="6" class="text-center">No data available</td></tr>';
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
                    ['Date', 'L30 Sales', 'L30 Orders', 'Clicks', 'Gprofit%', 'NPFT%']
                ];

                const reversedData = [...data].reverse();

                reversedData.forEach(row => {
                    const summaryData = row.summary_data || {};
                    const npft = toNum(summaryData.gprofit_percent || 0) - toNum(summaryData.tcos_percent || 0);
                    const clicks = toNum(summaryData.clicks || 0);
                    
                    chartData.push([
                        formatDate(row.snapshot_date),
                        toNum(summaryData.l30_sales),
                        toNum(summaryData.l30_orders),
                        clicks,
                        toNum(summaryData.gprofit_percent),
                        npft
                    ]);
                });

                // Calculate min/max for dynamic axis scaling
                let minSales = Infinity, maxSales = -Infinity;
                let minOrders = Infinity, maxOrders = -Infinity;
                let minClicks = Infinity, maxClicks = -Infinity;
                let minGprofit = Infinity, maxGprofit = -Infinity;
                let minNPFT = Infinity, maxNPFT = -Infinity;

                for (let i = 1; i < chartData.length; i++) {
                    const row = chartData[i];
                    const sales = row[1];
                    const orders = row[2];
                    const clicks = row[3];
                    const gprofit = row[4];
                    const npft = row[5];

                    if (sales < minSales) minSales = sales;
                    if (sales > maxSales) maxSales = sales;
                    if (orders < minOrders) minOrders = orders;
                    if (orders > maxOrders) maxOrders = orders;
                    if (clicks < minClicks) minClicks = clicks;
                    if (clicks > maxClicks) maxClicks = clicks;
                    if (gprofit < minGprofit) minGprofit = gprofit;
                    if (gprofit > maxGprofit) maxGprofit = gprofit;
                    if (npft < minNPFT) minNPFT = npft;
                    if (npft > maxNPFT) maxNPFT = npft;
                }

                // Calculate combined min/max for axes with 15% buffer
                const leftMin = Math.min(minSales, minOrders, minClicks);
                const leftMax = Math.max(maxSales, maxOrders, maxClicks);
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
                                color: '#9c27b0',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 0,
                                visibleInLegend: true
                            },
                            3: { 
                                color: '#e53935',
                                lineWidth: 3,
                                pointSize: 6,
                                pointShape: 'circle',
                                targetAxisIndex: 1,
                                visibleInLegend: true
                            },
                            4: { 
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
                                title: 'Sales, Orders & Clicks',
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
