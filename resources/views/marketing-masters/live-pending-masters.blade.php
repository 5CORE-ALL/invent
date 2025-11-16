@extends('layouts.vertical', ['title' => 'Live Pending Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        .chart-icon {
            cursor: pointer;
            color: #007bff;
            font-size: 18px;
        }
        .chart-icon:hover {
            color: #0056b3;
        }
        .modal-lg {
            max-width: 800px;
        }
        .date-filter-container {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .date-filter-container input {
            flex: 1;
        }
    
        
        /* Beautiful Cards Styling */
        .stats-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            background: white;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }
        
        .stats-card.card-info {
            border-left: 4px solid #667eea;
        }
        
        .stats-card.card-success.positive {
            border-left: 4px solid #38ef7d;
        }
        
        .stats-card.card-success.negative {
            border-left: 4px solid #f45c43;
        }
        
        .stats-card .card-body {
            padding: 20px;
            position: relative;
        }
        
        .stats-card .card-title {
            color: #666;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-card .badge {
            font-size: 26px;
            font-weight: 700;
            padding: 8px 16px;
            background: #f8f9fa !important;
            border-radius: 8px;
        }
        
        .stats-card.card-info .badge {
            color: #667eea !important;
        }
        
        .stats-card.card-success.positive .badge {
            color: #38ef7d !important;
        }
        
        .stats-card.card-success.negative .badge {
            color: #f45c43 !important;
        }
        
        .stats-card .card-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 40px;
            opacity: 0.15;
        }
        
        .stats-card.card-info .card-icon {
            color: #667eea;
        }
        
        .stats-card.card-success.positive .card-icon {
            color: #38ef7d;
        }
        
        .stats-card.card-success.negative .card-icon {
            color: #f45c43;
        }
        
        /* All Channels Chart Styling */
        .chart-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #e9ecef;
            position: relative;
            z-index: 1;
        }
        
        .chart-controls .btn {
            border-radius: 6px;
            font-weight: 500;
        }
        
        .chart-controls .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        #allChannelsChart {
            background: white;
            border-radius: 6px;
            padding: 10px;
        }
        
        .card-title {
            color: #495057;
            font-weight: 600;
        }
        
        /* Fix table visibility */
        .table-container {
            position: relative;
            z-index: 10;
            background: white;
        }
        
        /* Channel color legend */
        .channel-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .channel-color-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .color-square {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .channel-color-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            background: rgba(255,255,255,0.8);
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
    </style>

@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Live Pending Master',
        'sub_title' => 'Live Pending master Analysis',
    ])

    <!-- Summary Badges -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stats-card card-info">
                <div class="card-body text-center">
                    <i class="fas fa-clock card-icon"></i>
                    <h5 class="card-title">Live Pending</h5>
                    <span class="badge" id="livePendingCountBadge">0</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stats-card card-success {{ ($todayUpdates ?? 0) >= 0 ? 'positive' : 'negative' }}">
                <div class="card-body text-center">
                    <i class="fas {{ ($todayUpdates ?? 0) >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }} card-icon"></i>
                    <h5 class="card-title">Total Diff ({{ date('d M Y') }})</h5>
                    <span class="badge" id="todayUpdatesBadge">
                        {{ ($todayUpdates ?? 0) >= 0 ? '+' : '' }}{{ $todayUpdates ?? 0 }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- All Channels Chart Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line text-primary me-2"></i>
                            Daily Changes - All Channels
                        </h5>
                        <div class="chart-controls">
                            <button class="btn btn-sm btn-success me-2" onclick="refreshAllChannelsChart()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="openFullscreenChart()">
                                <i class="fas fa-expand"></i> Fullscreen
                            </button>
                        </div>
                    </div>
                    
                    <!-- Date Filter Controls -->
                    <div class="date-filter-container mb-3">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Start Date:</label>
                                <input type="date" id="allChannelsStartDate" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">End Date:</label>
                                <input type="date" id="allChannelsEndDate" class="form-control">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-primary me-2" onclick="applyAllChannelsDateFilter()">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <button class="btn btn-outline-secondary" onclick="clearAllChannelsDateFilter()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                          
                        </div>
                    </div>
                    
                    <!-- Channel Color Legend -->
                    <div class="channel-legend" id="channelLegend" style="display: none;">
                        <!-- Color squares will be populated by JavaScript -->
                    </div>
                    
                    <div class="chart-container" style="position: relative; height: 500px; background: #f8f9fa; border-radius: 10px; padding: 20px;">
                        <canvas id="allChannelsChart"></canvas>
                    </div>
                    
                    <!-- Summary Cards -->
                    <div class="row mt-3" id="allChannelsChartSummary" style="display: none;">
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Increases</h6>
                                    <h4 id="positiveChanges">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Decreases</h6>
                                    <h4 id="negativeChanges">0</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h6 class="card-title">No Change</h6>
                                    <h4 id="zeroChanges">0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Chart Modal -->
    <div class="modal fade" id="fullscreenChartModal" tabindex="-1" aria-labelledby="fullscreenChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="fullscreenChartModalLabel">
                        <i class="fas fa-chart-line me-2"></i>
                        Daily Changes - All Channels (Fullscreen)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="chart-container" style="position: relative; height: 80vh; background: #f8f9fa; border-radius: 10px; padding: 20px;">
                        <canvas id="fullscreenChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action & Correction Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="actionModalLabel">
                        <i class="fas fa-edit me-2"></i>
                        Action & Correction - <span id="modalChannelName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="actionForm">
                        <div class="mb-3">
                            <label for="actionInput" class="form-label fw-bold">
                                <i class="fas fa-tasks me-1"></i> Action
                            </label>
                            <textarea class="form-control" id="actionInput" rows="3" placeholder="Enter action details..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="correctionInput" class="form-label fw-bold">
                                <i class="fas fa-wrench me-1"></i> Correction Action
                            </label>
                            <textarea class="form-control" id="correctionInput" rows="3" placeholder="Enter correction action details..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-success" onclick="saveActionData()">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">

      

        <div id="customLoader" style="display: flex; justify-content: center; align-items: center; height: 300px;">
            <div class="spinner-border text-info" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <span class="ms-2">Loading datatable, please wait...</span>
        </div>



        <!-- Table Container -->
        <div class="table-container" id="channelTableWrapper"  style="display: none;">
            <div id="channelTable"></div>
        </div>

      
    </div>

    <!-- Chart Modal -->
    <div class="modal fade" id="chartModal" tabindex="-1" aria-labelledby="chartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="chartModalLabel">Live Pending Trend - <span id="channelNameDisplay"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" id="chartSummary" style="display: none;">
                        <strong>Summary:</strong> <span id="summaryText"></span>
                    </div>
                    <div class="date-filter-container">
                        <label>Start Date:</label>
                        <input type="date" id="startDateFilter" class="form-control">
                        <label>End Date:</label>
                        <input type="date" id="endDateFilter" class="form-control">
                        <button class="btn btn-primary" onclick="applyDateFilter()">Apply Filter</button>
                        <button class="btn btn-secondary" onclick="clearDateFilter()">Clear</button>
                    </div>
                    <canvas id="livePendingChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <!-- 1. Load jQuery FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- 2. Load Tabulator -->
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>

    <!-- 3. Then load other dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Chart.js Data Labels Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

    <script>
        window.totalSkuCount = {{ $totalSkuCount }};
        window.zeroInvCount = {{ $zeroInvCount }};
    </script>


    <script>
        // Use jQuery.noConflict() if needed
        var jq = jQuery.noConflict(true);

        // Global variables
        let originalChannelData = [];
        let table;
        let selectedExec = '';

        // Debounce function to limit function calls
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Debounced table reload
        const debouncedTableReload = debounce(() => table.setData(), 300);

        // Handle R&A checkbox change
        

        // Enhanced number parsing function
        function parseNumber(value) {
            if (value === null || value === undefined || value === '' ||
                value === '#DIV/0!' || value === 'N/A') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        // Function to update all summary cards
        function updateAllTotals(data) {

            let l60SalesTotal = 0;
            let l30SalesTotal = 0;
            let l60OrdersTotal = 0;
            let l30OrdersTotal = 0;
            let growthValues = [];
            let gprofitValues = [];
            let groiValues = [];
            let livePendingTotal = 0;

            data.forEach(row => {
                try {
                    // Process sales - try multiple possible column names
                    const l60Sales = parseNumber(
                        row['L-60 Sales'] || row['L40 Sales'] || row['L40-Dollar'] || row['L40-Dollars'] || 0
                    );
                    const l30Sales = parseNumber(
                        row['L30 Sales'] || row['L30-Dollar'] || row['L30-Dollars'] || 0
                    );

                    // Process orders - try multiple possible column names
                    const l60Orders = parseNumber(
                        row['L60 Orders'] || row['L40-Dollars'] || 0
                    );
                    const l30Orders = parseNumber(
                        row['L30 Orders'] || row['L30-Dollars'] || 0
                    );

                    // Process metrics
                    const growth = parseNumber(row['Growth'] || 0);
                    const gprofit = parseNumber(row['Gprofit%'] || row['Growth%'] || 0);
                    const groi = parseNumber(row['G Roi%'] || row['G.Rents'] || 0);

                    // Process live pending
                    const livePending = parseNumber(row['Live Pending'] || 0);

                    // Add to totals
                    l60SalesTotal += l60Sales;
                    l30SalesTotal += l30Sales;
                    l60OrdersTotal += l60Orders;
                    l30OrdersTotal += l30Orders;
                    livePendingTotal += livePending;

                    // Collect for averages
                    if (!isNaN(growth)) growthValues.push(growth);
                    if (!isNaN(gprofit)) gprofitValues.push(gprofit);
                    if (!isNaN(groi)) groiValues.push(groi);

                } catch (e) {
                    console.error("Error processing row:", row, e);
                }
            });

            let growthTotal = 0;
            if (l30SalesTotal !== 0) {
                growthTotal = ((l30SalesTotal - l60SalesTotal) / l60SalesTotal) * 100;
            }

            // Update the cards with formatted values
            jq('#l60SalesCountBadge').text(Math.round(l60SalesTotal).toLocaleString('en-US'));
            jq('#l30SalesCountBadge').text(Math.round(l30SalesTotal).toLocaleString('en-US'));
            jq('#l60OrdersCountBadge').text(Math.round(l60OrdersTotal).toLocaleString('en-US'));
            jq('#l30OrdersCountBadge').text(Math.round(l30OrdersTotal).toLocaleString('en-US'));
            jq('#growthPercentageBadge').text(growthTotal.toFixed(0) + '%');
           
            // Calculate averages for percentages
            const calculateAverage = arr => arr.length ?
                (arr.reduce((a, b) => a + b, 0) / arr.length * 100) : 0;

            // jq('#growthPercentageBadge').text(Math.round(calculateAverage(growthValues)) + '%');
            jq('#gprofitPercentage').text(Math.round(calculateAverage(gprofitValues)) + '%');
            jq('#groiPercentageBadge').text(Math.round(calculateAverage(groiValues)) + '%');

            // Update live pending badge
            jq('#livePendingCountBadge').text(Math.round(livePendingTotal).toLocaleString('en-US'));

        }

       

        function initializeDataTable() {
            return new Tabulator("#channelTable", {
                data: @json($data),
                layout: "fitData",
                pagination: true,
                paginationSize: 50,
                footer: true,
                frozen: true,
                columns: [{
                        title: "SL",
                        headerSort: false,
                        frozen: true,
                        formatter: function(cell) {
                            return cell.getRow().getPosition(true);
                        }
                    },
                    {
                        title: "Channel",
                        field: "Channel ",
                        formatter: function(cell) {
                            const data = cell.getValue();
                            if (!data) return '';
                            const channelName = data.trim().toLowerCase();
                            const routeMap = {
                                'amazon': '/amazon-zero-view',
                                'ebay': '/ebay-zero-view',
                                'ebaytwo': '/zero-ebay2',
                                'ebaythree': '/zero-ebay3',
                                'ebayvariation': '/zero-ebayvariation',
                                'temu': '/temu-zero-view',
                                'macys': '/macys-zero-view',
                                'wayfair': '/Wayfair-zero-view',
                                'reverb': '/reverb/zero/view',
                                'shopifyb2c': '/shopifyB2C-zero-view',
                                'doba': '/zero-doba',
                                'walmart': '/zero-walmart',
                                'aliexpress': '/zero-aliexpress',
                                'tiktokshop': '/zero-tiktokshop',
                                'shein': '/zero-shein',
                                'faire': '/zero-faire',
                                'mercariwship': '/zero-mercariwship',
                                'fbmarketplace': '/zero-fbmarketplace',
                                'business5core': '/zero-business5core',
                                'pls': '/zero-pls',
                                'auto ds': '/zero-autods',
                                'mercariwoship': '/zero-mercariwoship',
                                'tiendamia': '/zero-tiendamia',
                                'syncee': '/zero-syncee',
                                'fbshop': '/zero-fbshop',
                                'instagramshop': '/zero-instagramshop',
                                'yamibuy': '/zero-yamibuy',
                                'dhgate': '/zero-dhgate',
                                'bestbuyusa': '/zero-bestbuyusa',
                                'swgearexchange': '/zero-swgearexchange',
                                'shopifywholesale/ds': '/zero-shopifywholesale',
                            };

                            const routeUrl = routeMap[channelName];

                            if (routeUrl) {
                                return `<a href="${routeUrl}" class="channel-name" target="_blank" style="color: #007bff; text-decoration: underline;">${data}</a>`;
                            } else {
                                return `<div class="d-flex align-items-center channel-name"><span>${data}</span></div>`;
                            }
                        }
                    },
                    {
                        title: "R&A",
                        field: "R&A",
                        visible: false,
                        formatter: function(cell) {
                            const isChecked = cell.getValue() ? 'checked' : '';
                            const channel = cell.getRow().getData()['Channel '];
                            return `<div class="ra-edit-container d-flex align-items-center">
                                <input type="checkbox" class="ra-checkbox" ${isChecked} onclick="handleRACheckbox(this, '${channel}')">
                            </div>`;
                        }
                    },
                    {
                        title: "Live Pending",
                        field: "Live Pending",
                        bottomCalc: "sum",
                        formatter: function(cell) {
                            return `<span class="live-pending" data-row="${cell.getRow().getPosition()}">${cell.getValue() ?? 0}</span>`;
                        }
                    },
                    {
                        title: "Updated Today",
                        field: "Updated Today",
                        headerSort: false,
                        hozAlign: "center",
                        formatter: function(cell) {
                            const isUpdated = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const channel = (rowData['Channel '] || '').trim().toLowerCase();
                            const listedNotPending = rowData['Listed Not Pending'];
                            const pendingCount = rowData['Pending Count'];
                            
                            let html = '';
                            
                            if (isUpdated) {
                                html += '<span style="color: green; font-size: 18px;">✓</span>';
                            } else {
                                html += '<span style="color: red; font-size: 18px;">❌</span>';
                            }
                            
                            // Show detailed breakdown for Amazon only
                            if (channel === 'amazon' && listedNotPending !== null && listedNotPending !== undefined && pendingCount !== null && pendingCount !== undefined) {
                                const subtraction = listedNotPending - pendingCount;
                                html += `<br><span style="font-size: 10px; color: #666; font-weight: bold;">Listed: ${listedNotPending} | Pending: ${pendingCount}</span>`;
                                html += `<br><span style="font-size: 10px; color: ${subtraction >= 0 ? 'green' : 'red'}; font-weight: bold;">Diff: ${subtraction}</span>`;
                            }
                            
                            return html;
                        }
                    },
                    {
                        title: "Diff",
                        field: "Diff",
                        hozAlign: "center",
                        bottomCalc: "sum",
                        formatter: function(cell) {
                            const diff = cell.getValue() ?? 0;
                            let color = 'black';
                            let symbol = '';
                            
                            if (diff > 0) {
                                color = 'green';
                                symbol = '+';
                            } else if (diff < 0) {
                                color = 'red';
                            }
                            
                            return `<span style="color: ${color}; font-weight: bold;">${symbol}${diff}</span>`;
                        }
                    },
                    {
                        title: "Action & Correction",
                        field: "Action",
                        headerSort: false,
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const channel = rowData['Channel '];
                            const actionVal = rowData['Action'] || '';
                            const correctionVal = rowData['Correction action'] || '';
                            
                            let displayText = '';
                            if (actionVal && correctionVal) {
                                displayText = `Action: ${actionVal} | Correction: ${correctionVal}`;
                            } else if (actionVal) {
                                displayText = `Action: ${actionVal}`;
                            } else if (correctionVal) {
                                displayText = `Correction: ${correctionVal}`;
                            } else {
                                displayText = 'Click to add action';
                            }
                            
                            return `<button class="btn btn-sm btn-outline-primary" onclick="openActionModal('${channel}', '${actionVal}', '${correctionVal}')" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                                ${displayText}
                            </button>`;
                        }
                    },
                    {
                        title: "Trend",
                        headerSort: false,
                        formatter: function(cell) {
                            const channel = cell.getRow().getData()['Channel '];
                            return `<i class="fas fa-chart-line chart-icon" onclick="showChart('${channel}')"></i>`;
                        }
                    }
                ]
            });
        }


      
        //search
        jq(document).ready(function() {

            // Search Channel
            jq('#searchInput').on('input', debouncedTableReload);
        });


        // Dropdown functionality
        function populateChannelDropdown(searchTerm = '') {
            const channelData = originalChannelData.map(row => row['Channel ']);
            const uniqueChannels = [...new Set(channelData)].filter(ch => ch && ch.trim() !== '');

            const lowerSearch = searchTerm.toLowerCase();

            // Filter & sort: matched channels first
            const sortedChannels = uniqueChannels
                .filter(channel => channel.toLowerCase().includes(lowerSearch))
                .sort((a, b) => a.localeCompare(b));

            const $dropdown = jq('#channelSearchDropdown');
            $dropdown.empty();

            sortedChannels.forEach(channel => {
                $dropdown.append(`<div class="dropdown-search-item" data-value="${channel}">${channel}</div>`);
            });

            $dropdown.toggle(sortedChannels.length > 0); // Show only if matches
        }


        function populateExecDropdown(searchTerm = '') {
            const execData = originalChannelData.map(row => row['Exec']);
            const uniqueExecs = [...new Set(execData)].filter(exec => exec && exec.trim() !== '');
            uniqueExecs.sort();

            const lowerSearch = searchTerm.toLowerCase();

            // Sort: matched items first
            const sortedExecs = uniqueExecs.sort((a, b) => {
                const aMatch = a.toLowerCase().includes(lowerSearch);
                const bMatch = b.toLowerCase().includes(lowerSearch);
                if (aMatch && !bMatch) return -1;
                if (!aMatch && bMatch) return 1;
                return a.localeCompare(b); // fallback alphabetical
            });

            const $dropdown = jq('#execSearchDropdown');
            $dropdown.empty();

            uniqueExecs.forEach(exec => {
                $dropdown.append(`<div class="dropdown-search-item" data-value="${exec}">${exec}</div>`);
            });

            $dropdown.show();
        }

        jq('#channelSearchInput').on('input', function() {
            const val = jq(this).val().trim();
            console.log('[channelSearchInput] Input value:', val);
            populateChannelDropdown(val); // Pass the search term to sort dropdown

            if (val === '') {
                table.clearFilter(); // Clear the search if input is cleared
            }
        });

        jq('#execSearchInput').on('input', function() {
            const val = jq(this).val().trim();
            if (val === '') {
                table.clearFilter(); // Clear filter if empty
            }
            populateExecDropdown(val); // Pass the search term to sort dropdown
        });


        // Chart related variables
        let currentChart = null;
        let currentChannelName = '';
        let chartModal = null;
        let allChannelsChart = null;
        let fullscreenChart = null;

        // Show chart function
        window.showChart = function(channelName) {
            currentChannelName = channelName;
            jq('#channelNameDisplay').text(channelName);
            
            // Clear date filters
            jq('#startDateFilter').val('');
            jq('#endDateFilter').val('');
            
            // Load chart data
            loadChartData(channelName);
            
            // Show modal
            if (!chartModal) {
                chartModal = new bootstrap.Modal(document.getElementById('chartModal'));
            }
            chartModal.show();
        };

        // Load chart data
        function loadChartData(channelName, startDate = null, endDate = null) {
            console.log('Loading chart data for:', channelName);
            const params = new URLSearchParams({
                channel: channelName
            });
            
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);

            const url = `/api/channel-chart-data?${params.toString()}`;
            console.log('Fetching from URL:', url);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('Received data:', data);
                    if (!data.dates || data.dates.length === 0) {
                        jq('#chartSummary').html('<strong>No data available for this channel</strong>').show();
                        return;
                    }
                    renderChart(data.dates, data.counts);
                })
                .catch(error => {
                    console.error('Error loading chart data:', error);
                    jq('#chartSummary').html('<strong class="text-danger">Error loading chart data</strong>').show();
                });
        }

        // Render chart
        function renderChart(dates, counts) {
            console.log('Rendering chart with dates:', dates, 'and counts:', counts);
            const ctx = document.getElementById('livePendingChart').getContext('2d');
            
            // Calculate summary
            if (dates.length > 0) {
                const firstCount = counts[0];
                const lastCount = counts[counts.length - 1];
                const difference = lastCount - firstCount;
                const percentChange = firstCount !== 0 ? ((difference / firstCount) * 100).toFixed(2) : 0;
                const changeText = difference >= 0 ? `increased by ${difference}` : `decreased by ${Math.abs(difference)}`;
                const arrow = difference >= 0 ? '↑' : '↓';
                const colorClass = difference >= 0 ? 'text-success' : 'text-danger';
                
                jq('#summaryText').html(
                    `From <strong>${dates[0]}</strong> (${firstCount}) to <strong>${dates[dates.length - 1]}</strong> (${lastCount}): ` +
                    `<span class="${colorClass}">${arrow} ${changeText} (${percentChange}%)</span>`
                );
                jq('#chartSummary').show();
            } else {
                jq('#chartSummary').hide();
            }
            
            // Destroy previous chart if exists
            if (currentChart) {
                currentChart.destroy();
            }
            
            console.log('Creating new chart...');
            currentChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Live Pending Count',
                        data: counts,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: 'rgb(75, 192, 192)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    layout: {
                        padding: {
                            top: 35
                        }
                    },
                    plugins: {
                        datalabels: {
                            display: true,
                            align: function(context) {
                                return 'top';
                            },
                            offset: 10,
                            backgroundColor: function(context) {
                                if (context.dataIndex === 0) {
                                    return 'rgba(75, 192, 192, 0.9)';
                                }
                                const currentValue = context.dataset.data[context.dataIndex];
                                const prevValue = context.dataset.data[context.dataIndex - 1];
                                const diff = currentValue - prevValue;
                                return diff >= 0 ? 'rgba(40, 167, 69, 0.9)' : 'rgba(220, 53, 69, 0.9)';
                            },
                            borderRadius: 5,
                            color: 'white',
                            font: {
                                weight: 'bold',
                                size: 11
                            },
                            padding: 8,
                            formatter: function(value, context) {
                                if (context.dataIndex === 0) {
                                    // First point - only show count
                                    return value;
                                }
                                // Subsequent points - show count and difference
                                const prevValue = context.dataset.data[context.dataIndex - 1];
                                const diff = value - prevValue;
                                const arrow = diff >= 0 ? '↑' : '↓';
                                return value + '\n' + arrow + Math.abs(diff);
                            }
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Count: ' + context.parsed.y;
                                },
                                afterLabel: function(context) {
                                    if (context.dataIndex > 0) {
                                        const prevCount = context.dataset.data[context.dataIndex - 1];
                                        const currentCount = context.parsed.y;
                                        const diff = currentCount - prevCount;
                                        return diff >= 0 ? `↑ +${diff} from previous day` : `↓ ${diff} from previous day`;
                                    }
                                    return '';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Count'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
        }

        // Apply date filter
        window.applyDateFilter = function() {
            const startDate = jq('#startDateFilter').val();
            const endDate = jq('#endDateFilter').val();
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            if (startDate > endDate) {
                alert('Start date must be before end date');
                return;
            }
            
            loadChartData(currentChannelName, startDate, endDate);
        };

        // Clear date filter
        window.clearDateFilter = function() {
            jq('#startDateFilter').val('');
            jq('#endDateFilter').val('');
            loadChartData(currentChannelName);
        };

        // All Channels Chart Functions
        function loadAllChannelsChartData(startDate = null, endDate = null) {
            console.log('Loading all channels chart data...');
            
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            
            const url = `/api/all-channels-chart-data?${params.toString()}`;
            console.log('Fetching from URL:', url);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('Received all channels data:', data);
                    if (!data.dates || data.dates.length === 0) {
                        jq('#allChannelsChartSummary').html('<strong>No data available for any channels</strong>').show();
                        return;
                    }
                    renderAllChannelsChart(data.dates, data.datasets);
                })
                .catch(error => {
                    console.error('Error loading all channels chart data:', error);
                    jq('#allChannelsChartSummary').html('<strong class="text-danger">Error loading chart data</strong>').show();
                });
        }

        function renderAllChannelsChart(dates, datasets) {
            console.log('Rendering all channels chart with dates:', dates, 'and datasets:', datasets);
            const ctx = document.getElementById('allChannelsChart').getContext('2d');
            
            // Extend dates array to include future dates up to 25th of next month
            const extendedDates = [...dates];
            const extendedDatasets = datasets.map(dataset => ({
                ...dataset,
                data: [...dataset.data]
            }));
            
            if (dates.length > 0) {
                const lastDate = new Date(dates[dates.length - 1]);
                const targetDate = new Date(lastDate);
                
                // Calculate the 25th of the next month
                const nextMonth25 = new Date(lastDate.getFullYear(), lastDate.getMonth() + 1, 25);
                
                // Add dates from last date + 1 day until 25th of next month
                let currentDate = new Date(lastDate);
                currentDate.setDate(currentDate.getDate() + 1);
                
                while (currentDate <= nextMonth25) {
                    const dateStr = currentDate.toISOString().split('T')[0];
                    extendedDates.push(dateStr);
                    
                    // Add null values for all datasets for future dates
                    extendedDatasets.forEach(dataset => {
                        dataset.data.push(0);
                    });
                    
                    currentDate.setDate(currentDate.getDate() + 1);
                }
            }
            
            // Filter datasets to show only channels with data updates (non-zero differences)
            const filteredDatasets = extendedDatasets.filter(dataset => {
                return dataset.data.some(value => value !== 0);
            });
            
            // Calculate daily totals (sum of all channel COUNTS, not differences)
            // First, get the current Live Pending count from the badge
            let currentTotal = parseInt(jq('#livePendingCountBadge').text().replace(/,/g, '')) || 0;
            
            // Calculate the cumulative counts backwards from current total
            const dailyTotals = [];
            
            // Start from the last date with actual data (not the extended dates) and work backwards
            const actualDataLength = dates.length;
            
            for (let i = actualDataLength - 1; i >= 0; i--) {
                if (i === actualDataLength - 1) {
                    // Last date (today) - use current total
                    dailyTotals.unshift(currentTotal);
                } else {
                    // For previous days, subtract the next day's diff
                    const nextDayDiff = filteredDatasets.reduce((sum, dataset) => {
                        return sum + (dataset.data[i + 1] || 0);
                    }, 0);
                    currentTotal = currentTotal - nextDayDiff;
                    dailyTotals.unshift(currentTotal);
                }
            }
            
            // For future dates (beyond actual data), use null to not show data
            for (let i = actualDataLength; i < extendedDates.length; i++) {
                dailyTotals.push(null);
            }
            
            // Create a dataset for daily totals
            const totalDataset = {
                label: 'Total Live Pending',
                data: dailyTotals,
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderColor: 'rgb(102, 126, 234)',
                borderWidth: 2,
                order: 1,
                spanGaps: false // Don't connect null values
            };
            
            // Create color legend
            createColorLegend(filteredDatasets);
            
            // Calculate summary - show diffs
            if (filteredDatasets.length > 0 && dates.length > 0) {
                let totalPositiveChanges = 0;
                let totalNegativeChanges = 0;
                let totalZeroChanges = 0;
                
                // Calculate daily diffs
                filteredDatasets.forEach(dataset => {
                    dataset.data.forEach(value => {
                        if (value > 0) totalPositiveChanges += value;
                        else if (value < 0) totalNegativeChanges += Math.abs(value);
                        else totalZeroChanges++;
                    });
                });
                
                // Update summary cards
                jq('#positiveChanges').text(totalPositiveChanges);
                jq('#negativeChanges').text(totalNegativeChanges);
                jq('#zeroChanges').text(totalZeroChanges);
                jq('#allChannelsChartSummary').show();
            } else {
                jq('#allChannelsChartSummary').hide();
            }
            
            // Destroy previous chart if exists
            if (allChannelsChart) {
                allChannelsChart.destroy();
            }
            
            console.log('Creating new all channels chart...');
            allChannelsChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: extendedDates,
                    datasets: [totalDataset]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 10,
                            right: 10
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0,0,0,0.95)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: '#FF0000',
                            borderWidth: 3,
                            cornerRadius: 10,
                            displayColors: true,
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13,
                                weight: 'bold'
                            },
                            callbacks: {
                                title: function(context) {
                                    return '📅 ' + context[0].label;
                                },
                                label: function(context) {
                                    const value = context.parsed.y;
                                    return 'Total Live Pending: ' + value.toLocaleString() + ' listings';
                                },
                                afterBody: function(context) {
                                    // Calculate diff from previous day
                                    const currentIndex = context[0].dataIndex;
                                    if (currentIndex > 0) {
                                        const currentValue = context[0].parsed.y;
                                        const previousValue = context[0].dataset.data[currentIndex - 1];
                                        const diff = currentValue - previousValue;
                                        const symbol = diff > 0 ? '📈 Increase' : diff < 0 ? '📉 Decrease' : '➡️ No Change';
                                        return '\n' + symbol + ': ' + (diff > 0 ? '+' : '') + diff + ' from previous day';
                                    }
                                    return '';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Live Pending Count',
                                font: {
                                    size: 13,
                                    weight: 'bold',
                                    color: '#2C3E50'
                                }
                            },
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 11,
                                    weight: '500',
                                    color: '#34495E'
                                },
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            },
                            grid: {
                                color: function(context) {
                                    return context.tick.value === 0 ? '#FF0000' : '#F0F0F0';
                                },
                                lineWidth: function(context) {
                                    return context.tick.value === 0 ? 3 : 1;
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date',
                                font: {
                                    size: 13,
                                    weight: 'bold',
                                    color: '#2C3E50'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 10,
                                    weight: '500',
                                    color: '#34495E'
                                },
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45
                            },
                            grid: {
                                color: '#ECF0F1',
                                lineWidth: 1
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    barPercentage: 0.9,
                    categoryPercentage: 0.8
                }
            });
        }

        // Refresh all channels chart
        window.refreshAllChannelsChart = function() {
            const startDate = jq('#allChannelsStartDate').val();
            const endDate = jq('#allChannelsEndDate').val();
            loadAllChannelsChartData(startDate, endDate);
        };

        // Apply date filter for all channels chart
        window.applyAllChannelsDateFilter = function() {
            const startDate = jq('#allChannelsStartDate').val();
            const endDate = jq('#allChannelsEndDate').val();
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            if (startDate > endDate) {
                alert('Start date must be before end date');
                return;
            }
            
            loadAllChannelsChartData(startDate, endDate);
        };

        // Clear date filter for all channels chart
        window.clearAllChannelsDateFilter = function() {
            jq('#allChannelsStartDate').val('');
            jq('#allChannelsEndDate').val('');
            loadAllChannelsChartData();
        };

        // Create color legend for channels
        function createColorLegend(datasets) {
            const legendContainer = jq('#channelLegend');
            legendContainer.empty();
            
            if (datasets.length === 0) {
                legendContainer.hide();
                return;
            }
            
            datasets.forEach(dataset => {
                const legendItem = jq(`
                    <div class="channel-color-item">
                        <div class="color-square" style="background-color: ${dataset.borderColor}"></div>
                        <span>${dataset.label}</span>
                    </div>
                `);
                legendContainer.append(legendItem);
            });
            
            legendContainer.show();
        }

        // Global variables for action modal
        let currentChannelForAction = '';
        let currentActionData = {};

        // Open action modal
        window.openActionModal = function(channel, actionVal, correctionVal) {
            currentChannelForAction = channel;
            currentActionData = {
                action: actionVal,
                correction: correctionVal
            };
            
            // Set modal title
            jq('#modalChannelName').text(channel);
            
            // Fill form with existing values
            jq('#actionInput').val(actionVal);
            jq('#correctionInput').val(correctionVal);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('actionModal'));
            modal.show();
        };

        // Save action data
        window.saveActionData = function() {
            const actionVal = jq('#actionInput').val().trim();
            const correctionVal = jq('#correctionInput').val().trim();
            
            // Update the table data
            if (table) {
                const rows = table.getRows();
                rows.forEach(row => {
                    const data = row.getData();
                    if (data['Channel '] === currentChannelForAction) {
                        data['Action'] = actionVal;
                        data['Correction action'] = correctionVal;
                        row.update(data);
                    }
                });
            }
            
            // Save to server (you can implement this API call)
            saveChannelAction(currentChannelForAction, actionVal, correctionVal);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('actionModal'));
            modal.hide();
            
            // Show success message
            alert('Action and Correction data saved successfully!');
        };

        // Save channel action to server
        function saveChannelAction(channel, actionVal, correctionVal) {
            // You can implement API call here
            console.log('Saving action data:', {
                channel: channel,
                action: actionVal,
                correction: correctionVal
            });
            
            // Example API call (uncomment and modify as needed)
        
            fetch('/api/save-channel-action', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    channel: channel,
                    action: actionVal,
                    correction: correctionVal
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Action saved:', data);
            })
            .catch(error => {
                console.error('Error saving action:', error);
            });
       
        }

        // Open fullscreen chart
        window.openFullscreenChart = function() {
            const modal = new bootstrap.Modal(document.getElementById('fullscreenChartModal'));
            modal.show();
            
            // Load data for fullscreen chart
            const startDate = jq('#allChannelsStartDate').val();
            const endDate = jq('#allChannelsEndDate').val();
            loadFullscreenChartData(startDate, endDate);
        };

        // Load fullscreen chart data
        function loadFullscreenChartData(startDate = null, endDate = null) {
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            
            const url = `/api/all-channels-chart-data?${params.toString()}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.dates || data.dates.length === 0) {
                        return;
                    }
                    renderFullscreenChart(data.dates, data.datasets);
                })
                .catch(error => {
                    console.error('Error loading fullscreen chart data:', error);
                });
        }

        // Render fullscreen chart
        function renderFullscreenChart(dates, datasets) {
            const ctx = document.getElementById('fullscreenChart').getContext('2d');
            
            // Filter datasets to show only channels with data updates (non-zero differences)
            const filteredDatasets = datasets.filter(dataset => {
                return dataset.data.some(value => value !== 0);
            });
            
            // Calculate daily totals (sum of all channel COUNTS, not differences)
            // First, get the current Live Pending count from the badge
            let currentTotal = parseInt(jq('#livePendingCountBadge').text().replace(/,/g, '')) || 0;
            
            // Calculate the cumulative counts backwards from current total
            const dailyTotals = [];
            
            // Start from the last date (today) and work backwards
            for (let i = dates.length - 1; i >= 0; i--) {
                if (i === dates.length - 1) {
                    // Last date (today) - use current total
                    dailyTotals.unshift(currentTotal);
                } else {
                    // For previous days, subtract the next day's diff
                    const nextDayDiff = filteredDatasets.reduce((sum, dataset) => {
                        return sum + (dataset.data[i + 1] || 0);
                    }, 0);
                    currentTotal = currentTotal - nextDayDiff;
                    dailyTotals.unshift(currentTotal);
                }
            }
            
            // Create a dataset for daily totals
            const totalDataset = {
                label: 'Total Live Pending',
                data: dailyTotals,
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderColor: 'rgb(102, 126, 234)',
                borderWidth: 2
            };
            
            // Destroy previous chart if exists
            if (fullscreenChart) {
                fullscreenChart.destroy();
            }
            
            fullscreenChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates,
                    datasets: [totalDataset]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 40,
                            bottom: 30
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0,0,0,0.9)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: '#007bff',
                            borderWidth: 3,
                            callbacks: {
                                title: function(context) {
                                    return '📅 ' + context[0].label;
                                },
                                label: function(context) {
                                    const value = context.parsed.y;
                                    return 'Total Live Pending: ' + value.toLocaleString() + ' listings';
                                },
                                afterBody: function(context) {
                                    // Calculate diff from previous day
                                    const currentIndex = context[0].dataIndex;
                                    if (currentIndex > 0) {
                                        const currentValue = context[0].parsed.y;
                                        const previousValue = context[0].dataset.data[currentIndex - 1];
                                        const diff = currentValue - previousValue;
                                        const symbol = diff > 0 ? '📈 Increase' : diff < 0 ? '📉 Decrease' : '➡️ No Change';
                                        return '\n' + symbol + ': ' + (diff > 0 ? '+' : '') + diff + ' from previous day';
                                    }
                                    return '';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Live Pending Count',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                precision: 0,
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            },
                            grid: {
                                color: '#ddd',
                                lineWidth: 1
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 13,
                                    weight: 'bold'
                                },
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    barPercentage: 0.9,
                    categoryPercentage: 0.8
                }
            });
        }

        // Initialize when DOM is ready
        jq(document).ready(function() {
            // Set data
            originalChannelData = @json($data);

            // Initialize the table
            table = initializeDataTable();
            
            // Hide loader and show table
            $('#customLoader').hide();
            $('#channelTableWrapper').show();
            
            // Set default date range: from 25th of previous month to 25th of current month
            const today = new Date();
            const currentDay = today.getDate();
            
            let startDate, endDate;
            
            if (currentDay >= 25) {
                // If today is 25th or after, show from 25th of current month to 25th of next month
                startDate = new Date(today.getFullYear(), today.getMonth(), 25);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 25);
            } else {
                // If today is before 25th, show from 25th of previous month to 25th of current month
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 25);
                endDate = new Date(today.getFullYear(), today.getMonth(), 25);
            }
            
            // Format dates as YYYY-MM-DD
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            const startDateStr = formatDate(startDate);
            const endDateStr = formatDate(endDate);
            
            // Set the date input values
            jq('#allChannelsStartDate').val(startDateStr);
            jq('#allChannelsEndDate').val(endDateStr);
            
            // Load all channels chart with default date range
            loadAllChannelsChartData(startDateStr, endDateStr);
            
            // Update totals when table data is loaded
            table.on('tableBuilt', function() {
                updateAllTotals(table.getData());
            });
            
            table.on('dataFiltered', function(filters, rows) {
                updateAllTotals(rows.map(row => row.getData()));
            });
        });
    </script>
@endsection
