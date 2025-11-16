@extends('layouts.vertical', ['title' => 'Sales & Analysis', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #000000;
            --secondary-color: #0f0d35;
            --accent-color: #042345;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa !important;
            color: var(--dark-color) !important;
        }

        .container {
            max-width: 1200px !important;
            margin-top: 30px !important;
            margin-bottom: 50px !important;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .header h4 {
            font-weight: 600;
            margin: 0;
        }

        .metric-value {
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Negative growth (Red) */
        .negative-growth {
            background-color: rgb(255, 0, 0);
            color: rgb(0, 0, 0);
            width: 60px;
            text-align: center;
        }

        /* Zero growth (Yellow) */
        .zero-growth {
            background-color: rgb(255, 196, 0);
            color: rgb(0, 0, 0);
            width: 60px;
            text-align: center;
        }

        /* EXACTLY 100% (Magenta) */
        .exact-100 {
            background-color: #ff5100;
            color: rgb(0, 0, 0);
            width: 60px;
            text-align: center;
        }

        .search-box {
            max-width: 350px;
            margin-left: auto;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 5px;
            transition: var(--transition);
        }

        .dataTables_wrapper .dataTables_filter input {
            border: none;
            border-radius: 50px;
            padding: 8px 15px;
            margin-left: 10px;
        }

        .table>thead {
            vertical-align: bottom;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        th.sorting {
            color: white !important;
            font-size: 10px;
        }

        /* Rest of your existing styles... */
        /* Keep all your existing styles, just add these new ones below */

        /* DataTables custom styling */
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #000000;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5em 1em;
            margin: 0 2px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary-color);
            color: white !important;
            border: 1px solid var(--primary-color);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
            border: 1px solid #ddd;
        }

        /* Loading indicator */
        .dataTables_processing {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        /* Responsive adjustments for DataTables */
        @media (max-width: 768px) {
            .dataTables_wrapper .dataTables_filter {
                float: none;
                text-align: left;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
    <style>
        /* Right-to-Left Modal Animation */
        .modal.right-to-left .modal-dialog {
            margin: 0;
            right: 0;
            width: 400px;
            max-width: 80%;
            height: 100%;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        }

        .modal.right-to-left.show .modal-dialog {
            transform: translateX(0);
        }

        .modal.right-to-left .modal-content {
            height: 100%;
            overflow-y: auto;
            border-radius: 0;
            border: none;
        }

        /* Keep your existing modal styling */
        .modal.right-to-left .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal.right-to-left .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Sticky Dashboard Cards */
        .dashboard-header {
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 1030;
            padding-top: 10px;
        }

        /* Scrollable table */
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }

        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
        }

        /* Sticky Table Header */
        thead.sticky-top th {
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: #fff;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.05);
        }

        /* Optional Cleanup */
        table th,
        table td {
            white-space: nowrap;
        }

        .dropdown-search-item {
            padding: 6px 10px;
            cursor: pointer;
        }

        .dropdown-search-item:hover {
            background-color: #eee;
        }

        /* ========== PLAY/PAUSE NAVIGATION BUTTONS ========== */
        .time-navigation-group {
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }

        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        /* Play button */
        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        /* Pause button */
        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        /* Navigation buttons */
        #play-backward,
        #play-forward {
            color: #000000;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #0d2a48 !important;
            color: white !important;
        }

        /* Button state colors - must come after hover styles */
        #play-auto.btn-success,
        #play-pause.btn-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning,
        #play-pause.btn-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger,
        #play-pause.btn-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        #play-auto.btn-light,
        #play-pause.btn-light {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }

        /* Ensure hover doesn't override state colors */
        #play-auto.btn-success:hover,
        #play-pause.btn-success:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning:hover,
        #play-pause.btn-warning:hover {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger:hover,
        #play-pause.btn-danger:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }

        /* Active state styling */
        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .time-navigation-group button {
                width: 36px;
                height: 36px;
            }

            .time-navigation-group button i {
                font-size: 1rem;
            }
        }

        /* Add to your CSS file or style section */
        .hide-column {
            display: none !important;
        }

        .dataTables_length,
        .dataTables_filter {
            display: none;
        }

        #play-auto.green-btn {
            background-color: green !important;
            color: white;
        }

        #play-auto.red-btn {
            background-color: red !important;
            color: white;
        }

        th small.badge {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .dataTables_processing {
            top: 150px !important;
            /* Try 80–100px depending on your header height */
            z-index: 1000 !important;
            background: none !important;
            border: none;
        }

        #channelTable {
            width: 100% !important;
            table-layout: fixed;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Pricing increase/decrease',
        'sub_title' => 'Master Pricing Increase/Decrease',
    ])
    <div class="container-fluid">

        <div class="col-md-12 mt-0 pt-0 mb-1 pb-1">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <!-- play backward forward -->
                <div class="d-flex align-items-center gap-2 flex-nowrap" style="flex: 1 1 auto; min-width: 0;">
                    <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                        <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button id="play-pause" class="btn btn-light rounded-circle" title="Show all products"
                            style="display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button id="play-auto" class="btn btn-light rounded-circle" title="Show all products">
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                </div>

                <div class="col-auto">
                    <div class="dropdown-search-container" style="position: relative;" style="min-width: 220px;">
                        <input type="text" class="form-control form-control-sm channel-search"
                            placeholder="Search Channel" id="channelSearchInput">
                        <div class="dropdown-search-results" id="channelSearchDropdown"
                            style="position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; background: #fff; border: 1px solid #ccc; display: none; max-height: 200px; overflow-y: auto;">
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div id="customLoader" style="display: flex; justify-content: center; align-items: center; height: 300px;">
            <div class="spinner-border text-info" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <span class="ms-2">Loading datatable, please wait...</span>
        </div>

        <!-- Table Container -->
        <div class="table-container" id="channelTableWrapper" style="display: none;">
            <div class="table-responsive" style="max-height: 500px; overflow: auto;">
                <table class="table table-hover table-striped mb-0" id="channelTable">
                    <thead class="table sticky-top">
                        <tr>
                            <th>Channel</th>
                            <th>R&A</th>
                            {{-- <th>Link</th> --}}
                            {{-- <th class="text-center align-middle">
                                <small id="l60SalesCountBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    ₹ 0
                                </small><br>
                                L-60 Sales
                            </th> --}}
                            <th class="text-center align-middle">
                                <small id="l30SalesCountBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    ₹ 0
                                </small><br>
                                L30 Sales
                            </th>
                            <th class="text-center align-middle">
                                <small id="growthPercentageBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0%
                                </small><br>
                                Growth
                            </th>



                            
                            

                            {{-- <th class="text-center align-middle">
                                <small id="l60OrdersCountBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0
                                </small><br>
                                L60 Orders
                            </th>
                            <th class="text-center align-middle">
                                <small id="l30OrdersCountBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0
                                </small><br>
                                L30 Orders
                            </th> --}}

                            <th class="text-center align-middle">
                                <small id="gprofitPercentage" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0%
                                </small><br>
                                Gprofit%
                            </th>
                            <th class="text-center align-middle">
                                <small id="groiPercentageBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0%
                                </small><br>
                                G ROI%
                            </th>

                            {{-- <th class="text-center align-middle">
                                <small id="groiPercentageBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0%
                                </small><br>
                                Tprofit%
                            </th>

                            <th class="text-center align-middle">
                                <small id="groiPercentageBadge" class="badge bg-dark text-white mb-1"
                                    style="font-size: 13px;">
                                    0%
                                </small><br>
                                TROI%
                            </th> --}}
                            </th>
                            <!-- <th>Update</th> -->
                            <!-- <th>Ac Health</th> -->
                            <!-- <th class="text-white">Action</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@section('script')
    <!-- 1. Load jQuery FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- 2. Then load DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- 3. Then load other dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

    <script>
        // Use jQuery.noConflict() if needed
        var jq = jQuery.noConflict(true);

        // Global variables
        let originalChannelData = [];
        let allChannelData = [];
        let table;
        let isPlaying = false;
        let currentChannelIndex = 0;
        let uniqueChannels = [];
        let uniqueChannelRows = [];
        let selectedChannel = '';

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

                    // Add to totals
                    l60SalesTotal += l60Sales;
                    l30SalesTotal += l30Sales;
                    l60OrdersTotal += l60Orders;
                    l30OrdersTotal += l30Orders;

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
            const l60Badge = document.getElementById('l60SalesCountBadge');
            const l30Badge = document.getElementById('l30SalesCountBadge');
            const l60OrdersBadge = document.getElementById('l60OrdersCountBadge');
            const l30OrdersBadge = document.getElementById('l30OrdersCountBadge');
            const growthBadge = document.getElementById('growthPercentageBadge');
            const gprofitBadge = document.getElementById('gprofitPercentage');
            const groiBadge = document.getElementById('groiPercentageBadge');

            if (l60Badge) l60Badge.textContent = Math.round(l60SalesTotal).toLocaleString('en-US');
            if (l30Badge) l30Badge.textContent = Math.round(l30SalesTotal).toLocaleString('en-US');
            if (l60OrdersBadge) l60OrdersBadge.textContent = Math.round(l60OrdersTotal).toLocaleString('en-US');
            if (l30OrdersBadge) l30OrdersBadge.textContent = Math.round(l30OrdersTotal).toLocaleString('en-US');
            if (growthBadge) growthBadge.textContent = growthTotal.toFixed(0) + '%';

            // Calculate G profit and G roi using totalPft, totalL30Sales, totalCogs
            let totalPft = 0;
            let totalCogs = 0;
            let totalL30Sales = 0;
            data.forEach(function(row) {
                const pft = parseNumber(row['pft']);
                totalPft += pft;
                const cogs = parseNumber(row['COGS']);
                totalCogs += cogs;
                const l30Sales = parseNumber(row['L30 Sales']);
                totalL30Sales += l30Sales;
            });
            let gProfit = totalL30Sales !== 0 ? (totalPft / totalL30Sales * 100) : null;
            let gRoi = totalCogs !== 0 ? (totalPft / totalCogs * 100) : null;
            if (gprofitBadge) gprofitBadge.textContent = gProfit !== null ? gProfit.toFixed(1) + '%' : 'N/A';
            if (groiBadge) groiBadge.textContent = gRoi !== null ? gRoi.toFixed(1) + '%' : 'N/A';
        }

        // Initialize DataTable
        function initializeDataTable() {
            try {
                if (!jq('#channelTable').length) {
                    console.error('Table element not found');
                    return null;
                }

                return jq('#channelTable').DataTable({
                    processing: true,
                    serverSide: false,
                    ordering: true,
                    searching: true,
                    pageLength: 25,
                    ajax: {
                        url: '/channels-master-data',
                        type: "GET",
                        data: function(d) {
                            $('#customLoader').hide();
                            $('#channelTableWrapper').show();
                            d.channel = jq('#channelSearchInput').val();
                            d.search = jq('#searchInput').val();
                            d.sort_by = jq('#sort_by').val();
                            d.sort_order = jq('#sort_order').val();
                            d.sort_by = currentSortColumn;
                            d.sort_order = currentSortOrder;
                        },
                        dataSrc: function(json) {
                            allChannelData = json.data;
                            if (!originalChannelData.length) {
                                originalChannelData = json.data;
                            }

                            const selectedChannel = jq('#channelSearchInput').val()?.toLowerCase()?.trim();
                            const search = jq('#searchInput').val()?.toLowerCase()?.trim();

                            let filteredData = json.data;

                            if (selectedChannel) {
                                filteredData = filteredData.filter(item =>
                                    item['Channel ']?.toLowerCase()?.trim() === selectedChannel
                                );
                            }

                            if (search) {
                                filteredData = filteredData.filter(item =>
                                    item['Channel ']?.toLowerCase()?.trim() === search
                                );
                            }

                            return filteredData;
                        },
                        error: function(xhr, error, thrown) {
                            console.log("AJAX error:", error, thrown);
                        }
                    },
                    columns: [{
                            data: 'Channel ',
                            render: function(data, type, row) {
                                if (!data) return '';

                                // Lowercase for matching
                                const channelName = data.toLowerCase();
                                let link = '';

                                // Add more conditions as needed
                                if (channelName.includes('ebay')) {
                                    
                                    link = `https://inventory.5coremanagement.com/public/ebay`;
                                } else if (channelName.includes('amazon')) {
                                    link = `https://inventory.5coremanagement.com/public/overall-amazon`;
                                } else if (channelName.includes('shopify')) {
                                    link = `https://inventory.5coremanagement.com/public/shopifyB2C`;
                                } else if (channelName.includes('doba')) {
                                    link = `https://inventory.5coremanagement.com/public/doba`;
                                } else if (channelName.includes('aliexpress')) {
                                    link = ``;
                                } else if (channelName.includes('newegg')) {
                                    link = `https://inventory.5coremanagement.com/public/neweggB2C`;
                                } else if (channelName.includes('poshmark')) {
                                    link = ``;
                                } else if (channelName.includes('business')) {
                                    link = ``;
                                } else if (channelName.includes('temu')) {
                                    link = `https://inventory.5coremanagement.com/public/temu`;
                                } else if (channelName.includes('ebay3')) {
                                    link = `https://inventory.5coremanagement.com/public/ebayThreePricingCVR`;
                                } else if (channelName.includes('reverb')) {
                                    link = `https://inventory.5coremanagement.com/public/reverb`;
                                }
                                else if (channelName.includes('fbmarketplace')) {
                                    link = `http://127.0.0.1:8004/FB Marketplace`;
                                } else if (channelName.includes('amazonfba')) {
                                    link = `https://inventory.5coremanagement.com/public/overall-amazon-fba`;
                                }


                                if (link) {
                                    return `<a href="${link}" target="_blank" style="
                background: #f4f4f4;
                border: 1px solid #ccc;
                padding: 3px 8px;
                border-radius: 6px;
                color:#ff0303;
                font-size: 0.875rem;
                text-decoration: none;
                display: inline-block;
                cursor: pointer;
            ">${data}</a>`;
                                } else {
                                    return `<span style="
                background: #f4f4f4;
                border: 1px solid #ccc;
                padding: 3px 8px;
                border-radius: 6px;
                color:#ff0303;
                font-size: 0.875rem;
                text-decoration: none;
                display: inline-block;
                cursor: default;
            ">${data}</span>`;
                                }
                            }
                        }

                        ,
                        {
                            data: 'R&A',
                            visible: false,
                            render: function(data, type, row) {
                                const isChecked = data ? 'checked' : '';
                                return `<div class="ra-edit-container d-flex align-items-center">
                                        <input type="checkbox" class="ra-checkbox" ${isChecked}>
                                    </div>`;
                            }
                        },
                        // {
                        //     data: 'URL LINK',
                        //     render: function(data, type, row) {
                        //         return data ?
                        //             `<a href="${data}" target="_blank"><i class="bi bi-box-arrow-up-right link-icon"></i></a>` :
                        //             '';
                        //     }
                        // },
                        // {
                        //     data: 'L-60 Sales',
                        //     className: 'metric-cell',
                        //     render: function(data, type, row) {
                        //         try {
                        //             const num = parseFloat(data.toString().replace(/,/g, ''));
                        //             const roundedNum = Math.round(num);
                        //             return `<span class="metric-value">${roundedNum.toLocaleString('en-US')}</span>`;
                        //         } catch (e) {
                        //             return '<span class="metric-value">N/A</span>';
                        //         }
                        //     }
                        // },
                        {
                            data: 'L30 Sales',
                            className: 'metric-cell',
                            render: function(data, type, row) {
                                if (data === undefined || data === null || data === '#DIV/0!') {
                                    return '<span class="metric-value"></span>';
                                }

                                const num = parseFloat(data.toString().replace(/,/g, ''));

                                if (isNaN(num)) {
                                    return '<span class="metric-value"></span>';
                                }

                                const roundedNum = Math.round(num);

                                return `<span class="metric-value">${roundedNum.toLocaleString('en-US', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                })}</span>`;
                            }
                        },
                        {
                            data: 'Growth',
                            className: 'metric-cell',
                            render: function(data, type, row) {
                                const l30 = parseFloat(row['L30 Sales']);
                                let l60 = parseFloat(row['L-60 Sales']);

                                if (isNaN(l60)) l60 = 0;

                                if (!l30 || isNaN(l30) || l30 === 0) {
                                    return '<span class="growth-value">-</span>';
                                }

                                const growth = (l30 - l60) / l30;
                                const percent = (growth * 100).toFixed(0);
                                const value = `${percent}%`;

                                let bgColor = '#e0e0e0';
                                let textColor = '#000';

                                if (growth < 0) {
                                    bgColor = '#e63946'; // vibrant red
                                    textColor = '#fff';
                                } else if (growth < 0.10) {
                                    bgColor = '#ffb703'; // strong amber/yellow
                                    textColor = '#000';
                                } else if (growth < 0.20) {
                                    bgColor = '#3a86ff'; // vibrant blue
                                    textColor = '#fff';
                                } else if (growth < 0.50) {
                                    bgColor = '#06d6a0'; // bright teal/green
                                    textColor = '#000';
                                } else {
                                    bgColor = '#9d4edd'; // rich violet
                                    textColor = '#fff';
                                }
                                return `<span class="growth-value" style="background-color:${bgColor}; color:${textColor}; padding:2px 6px; border-radius:4px;">${value}</span>`;
                            }
                        },
                        // {
                        //     data: 'L60 Orders',
                        //     className: 'metric-cell',
                        //     render: function(data, type, row) {
                        //         if (data === undefined || data === null || data === '#DIV/0!') {
                        //             return '<span class="metric-value"></span>';
                        //         }
                        //         return `<span class="metric-value">${data}</span>`;
                        //     }
                        // },
                        // {
                        //     data: 'L30 Orders',
                        //     className: 'metric-cell',
                        //     render: function(data, type, row) {
                        //         if (data === undefined || data === null || data === '#DIV/0!') {
                        //             return '<span class="metric-value"></span>';
                        //         }
                        //         return `<span class="metric-value">${data}</span>`;
                        //     }
                        // },
                        {
                            data: 'Gprofit%',
                            className: 'metric-cell',
                            render: function(data, type, row) {
                                if (data === undefined || data === null || data === '#DIV/0!') {
                                    return '<span class="metric-value"></span>';
                                }

                                const num = parseFloat(data);
                                if (isNaN(num)) {
                                    return '<span class="metric-value"></span>';
                                }

                                let percentage = Math.round(num * 100);
                                let bgColor = '';
                                let textColor = 'black';

                                if (percentage < 25) {
                                    bgColor = '#ff0000';
                                    textColor = 'white';
                                } else if (percentage >= 25 && percentage < 33) {
                                    bgColor = '#00ff00';
                                    textColor = 'black';
                                } else {
                                    bgColor = '#ff00ff';
                                    textColor = 'white';
                                }

                                // Dynamic link based on channel name
                                const channelName = (row['Channel '] || '').toLowerCase();
                                let link = '';
                                if (channelName.includes('ebay')) {
                                    
                                    link = `https://inventory.5coremanagement.com/public/ebay`;
                                } else if (channelName.includes('amazon')) {
                                    link = `https://inventory.5coremanagement.com/public/overall-amazon`;
                                } else if (channelName.includes('shopify')) {
                                    link = `https://inventory.5coremanagement.com/public/shopifyB2C`;
                                } else if (channelName.includes('doba')) {
                                    link = `https://inventory.5coremanagement.com/public/doba`;
                                } else if (channelName.includes('aliexpress')) {
                                    link = ``;
                                } else if (channelName.includes('newegg')) {
                                    link = `https://inventory.5coremanagement.com/public/neweggB2C`;
                                } else if (channelName.includes('poshmark')) {
                                    link = ``;
                                } else if (channelName.includes('business')) {
                                    link = ``;
                                } else if (channelName.includes('temu')) {
                                    link = `https://inventory.5coremanagement.com/public/temu`;
                                } else if (channelName.includes('ebay3')) {
                                    link = `https://inventory.5coremanagement.com/public/ebayThreePricingCVR`;
                                } else if (channelName.includes('reverb')) {
                                    link = `https://inventory.5coremanagement.com/public/reverb`;
                                }
                                else if (channelName.includes('fbmarketplace')) {
                                    link = `http://127.0.0.1:8004/FB Marketplace`;
                                } else if (channelName.includes('amazonfba')) {
                                    link = `https://inventory.5coremanagement.com/public/overall-amazon-fba`;
                                }


                                if (link) {
                                    return `<a href="${link}" target="_blank" style="text-decoration:none;">
                <span class="gprofit-value" style="background-color:${bgColor}; color:${textColor}; padding:2px 6px; border-radius:4px;">
                    ${percentage}%
                </span>
            </a>`;
                                } else {
                                    return `<span class="gprofit-value" style="background-color:${bgColor}; color:${textColor}; padding:2px 6px; border-radius:4px;">
                ${percentage}%
            </span>`;
                                }
                            }
                        },
                        {
                            data: 'G Roi%',
                            className: 'metric-cell',
                            render: function(data, type, row) {
                                if (data === undefined || data === null || data === '#DIV/0!') {
                                    return '<span class="metric-value"></span>';
                                }

                                const num = parseFloat(data);

                                if (isNaN(num)) {
                                    return '<span class="metric-value"></span>';
                                }

                                let percentage = Math.round(num * 100);
                                let bgColor = '';
                                let textColor = 'black';

                                if (percentage <= 50) {
                                    bgColor = '#ff0000';
                                    textColor = 'white';
                                }

                                return `<span class="groi-value" style="background-color:${bgColor}; color:${textColor}; padding:2px 6px; border-radius:4px;">${percentage}%</span>`;
                            }
                        },
                        // {
                        //     data: 'Checked',
                        //     render: function(data, type, row) {
                        //         var isChecked = data ? 'checked' : '';
                        //         var dateDisplay = data ? new Date(data).toLocaleDateString() : '';

                        //         return '<input type="checkbox" ' + isChecked +
                        //             ' title="' + dateDisplay + '" disabled>';
                        //     }
                        // },
                        // {
                        //     data: 'Account health',
                        //     render: function(data, type, row) {
                        //         return data ?
                        //             `<a href="${data}" target="_blank"><i class="bi bi-box-arrow-up-right link-icon"></i></a>` :
                        //             '';
                        //     }
                        // },
                        // {
                        //     data: null,
                        //     orderable: true,
                        //     searchable: false,
                        //     render: function(data, type, row) {
                        //         return `
                    //         <div class="d-flex justify-content-center">
                    //             <button class="btn btn-sm btn-outline-primary edit-btn me-1" title="Edit">
                    //                 <i class="fas fa-edit"></i>
                    //             </button>
                    //             <button class="btn btn-sm btn-outline-danger delete-btn" title="Archive">
                    //                 <i class="fa fa-archive"></i>
                    //             </button>
                    //         </div>`;
                        //     }
                        // }
                    ],
                    responsive: true,
                    language: {
                        processing: "Loading data, please wait...",
                        emptyTable: "",
                        zeroRecords: "",
                    },
                    initComplete: function() {
                        var buttons = new jq.fn.dataTable.Buttons(table, {
                            buttons: ['excel', 'print']
                        }).container().appendTo(jq('#channelTable_wrapper .col-md-6:eq(0)'));
                    }
                });
            } catch (e) {
                console.error('Error initializing DataTable:', e);
                return null;
            }
        }


        function updatePlayButtonColor() {
            try {
                if (!table || !table.rows) return;

                const visibleRow = table.rows({
                    search: 'applied'
                }).nodes().to$();
                if (!visibleRow || !visibleRow.length) return;

                const raCheckbox = visibleRow.find('.ra-checkbox');
                if (raCheckbox.length) {
                    const isChecked = raCheckbox.prop('checked');
                    console.log('Checkbox checked (updatePlayButtonColor):', isChecked);

                    const playPauseBtn = jq('#play-pause');
                    if (playPauseBtn.length) {
                        playPauseBtn
                            .removeClass('btn-light btn-success btn-danger')
                            .addClass(isChecked ? 'btn-success' : 'btn-danger')
                            .css('color', 'white');
                    }
                }
            } catch (e) {
                console.error('Error in updatePlayButtonColor:', e);
            }
        }

        // Play/Pause functionality
        function startPlayback() {
            try {
                console.log('Starting playback...');

                if (!originalChannelData.length) {
                    console.error('No channel data available');
                    return;
                }

                // Get unique channels
                uniqueChannels = [...new Set(originalChannelData.map(item => item['Channel ']?.trim()))].filter(Boolean);
                console.log('Found unique channels:', uniqueChannels);

                uniqueChannelRows = uniqueChannels.map(channel => {
                    return originalChannelData.find(item => item['Channel ']?.trim() === channel);
                });

                if (uniqueChannelRows.length === 0) {
                    console.error('No unique channels found');
                    return;
                }

                currentChannelIndex = 0;
                isPlaying = true;

                // Configure table for single-channel view
                table.page.len(1).draw();
                table.search('').columns().search('').draw();

                // Show first channel
                showCurrentChannel();

                // Update UI
                const playAutoBtn = document.getElementById('play-auto');
                const playPauseBtn = document.getElementById('play-pause');

                if (playAutoBtn) playAutoBtn.style.display = 'none';
                if (playPauseBtn) playPauseBtn.style.display = 'block';

                table.column(1).visible(true);

                console.log('Playback started. Current channel:', uniqueChannelRows[currentChannelIndex]['Channel ']);

                setTimeout(() => {
                    updatePlayButtonColor();
                }, 500);
            } catch (e) {
                console.error('Error in startPlayback:', e);
            }
        }

        function stopPlayback() {
            try {
                console.log('Stopping playback...');
                isPlaying = false;

                // Restore table to normal view
                table.clear().rows.add(originalChannelData).draw();
                table.page.len(25).draw();

                // Update UI
                const playAutoBtn = document.getElementById('play-auto');
                const playPauseBtn = document.getElementById('play-pause');

                if (playAutoBtn) playAutoBtn.style.display = 'block';
                if (playPauseBtn) playPauseBtn.style.display = 'none';

                table.column(1).visible(false);
            } catch (e) {
                console.error('Error in stopPlayback:', e);
            }
        }

        function showCurrentChannel() {
            try {
                if (!isPlaying || !uniqueChannelRows.length) return;

                const currentRow = uniqueChannelRows[currentChannelIndex];
                console.log('Showing channel:', currentRow['Channel ']);

                if (currentRow) {
                    // Clear table and add only the current row
                    table.clear().rows.add([currentRow]).draw();

                    // Update search input to show current channel
                    const searchInput = document.getElementById('channelSearchInput');
                    if (searchInput) {
                        searchInput.value = currentRow['Channel ']?.trim() || '';
                    }

                    // Scroll to the top of the table
                    const tableContainer = document.getElementById('channelTable')?.parentElement;
                    if (tableContainer) {
                        tableContainer.scrollTop = 0;
                    }

                    setTimeout(() => {
                        updatePlayButtonColor();
                    }, 500);
                }
            } catch (e) {
                console.error('Error in showCurrentChannel:', e);
            }
        }

        function nextChannel() {
            try {
                if (!isPlaying) return;

                if (currentChannelIndex < uniqueChannelRows.length - 1) {
                    currentChannelIndex++;
                    console.log('Moving to next channel. New index:', currentChannelIndex);
                    showCurrentChannel();
                } else {
                    console.log('Reached end of channel list');
                    stopPlayback();
                }
            } catch (e) {
                console.error('Error in nextChannel:', e);
            }
        }

        function previousChannel() {
            try {
                if (!isPlaying) return;

                if (currentChannelIndex > 0) {
                    currentChannelIndex--;
                    console.log('Moving to previous channel. New index:', currentChannelIndex);
                    showCurrentChannel();
                }
            } catch (e) {
                console.error('Error in previousChannel:', e);
            }
        }

        // Sorting toggle logic
        let currentSortColumn = 'Channel ';
        let currentSortOrder = 'asc';

        function setupSorting() {
            try {
                let sortDirection = 'asc';

                const toggleSortBtn = document.getElementById('toggleSort');
                const sortDirectionText = document.getElementById('sortDirectionText');
                const sortMetricSelect = document.getElementById('sortMetric');

                if (toggleSortBtn && sortDirectionText) {
                    toggleSortBtn.addEventListener('click', function() {
                        sortDirection = (sortDirection === 'asc') ? 'desc' : 'asc';
                        sortDirectionText.textContent = sortDirection === 'asc' ? 'Low to High' : 'High to Low';
                        const metricIndex = parseInt(document.getElementById('sortMetric').value);
                        table.order([metricIndex, sortDirection]).draw();
                    });
                }

                if (sortMetricSelect) {
                    sortMetricSelect.addEventListener('change', function() {
                        const metricIndex = parseInt(this.value);
                        table.order([metricIndex, sortDirection]).draw();
                    });
                }

                // Initial sort
                if (sortMetricSelect) {
                    sortMetricSelect.value = '3';
                }
                table.order([3, sortDirection]).draw();
            } catch (e) {
                console.error('Error in setupSorting:', e);
            }
        }

        // Dropdown functionality
        function populateChannelDropdown(searchTerm = '') {
            try {
                const channelData = originalChannelData.map(row => row['Channel ']);
                const uniqueChannels = [...new Set(channelData)].filter(ch => ch && ch.trim() !== '');
                uniqueChannels.sort();

                const lowerSearch = searchTerm.toLowerCase();

                const sortedChannels = uniqueChannels.sort((a, b) => {
                    const aMatch = a.toLowerCase().includes(lowerSearch);
                    const bMatch = b.toLowerCase().includes(lowerSearch);
                    if (aMatch && !bMatch) return -1;
                    if (!aMatch && bMatch) return 1;
                    return a.localeCompare(b);
                });

                const dropdown = document.getElementById('channelSearchDropdown');
                if (!dropdown) return;

                dropdown.innerHTML = '';

                uniqueChannels.forEach(channel => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-search-item';
                    item.dataset.value = channel;
                    item.textContent = channel;
                    dropdown.appendChild(item);
                });

                dropdown.style.display = 'block';
            } catch (e) {
                console.error('Error in populateChannelDropdown:', e);
            }
        }

        window.csrfToken = '{{ csrf_token() }}';

        // Initialize when DOM is ready
        jq(document).ready(function() {
            try {
                // First load the data
                jq.ajax({
                    url: '/channels-master-data',
                    type: "GET",
                    success: function(json) {
                        originalChannelData = json.data;

                        // Now initialize the DataTable
                        table = initializeDataTable();

                        if (!table) {
                            console.error('Failed to initialize DataTable');
                            return;
                        }

                        // Set up event handlers
                        const playAutoBtn = document.getElementById('play-auto');
                        const playPauseBtn = document.getElementById('play-pause');
                        const playForwardBtn = document.getElementById('play-forward');
                        const playBackwardBtn = document.getElementById('play-backward');
                        const channelSearchInput = document.getElementById('channelSearchInput');
                        const channelSearchDropdown = document.getElementById('channelSearchDropdown');

                        if (playAutoBtn) {
                            playAutoBtn.addEventListener('click', startPlayback);
                        }

                        if (playPauseBtn) {
                            playPauseBtn.addEventListener('click', stopPlayback);
                        }

                        if (playForwardBtn) {
                            playForwardBtn.addEventListener('click', nextChannel);
                        }

                        if (playBackwardBtn) {
                            playBackwardBtn.addEventListener('click', previousChannel);
                        }

                        // Hide pause button initially
                        if (playPauseBtn) {
                            playPauseBtn.style.display = 'none';
                        }

                        // Hide R&A column initially
                        table.column(1).visible(false);

                        // Setup sorting
                        setupSorting();

                        // Setup dropdown
                        if (channelSearchInput) {
                            channelSearchInput.addEventListener('focus', function() {
                                populateChannelDropdown();
                            });

                            channelSearchInput.addEventListener('input', function() {
                                const val = this.value.trim();
                                if (val === '') {
                                    table.column(0).search('').draw();
                                }
                                populateChannelDropdown(val);
                            });
                        }

                        if (channelSearchDropdown) {
                            channelSearchDropdown.addEventListener('click', function(e) {
                                if (e.target.classList.contains('dropdown-search-item')) {
                                    const selectedChannel = e.target.dataset.value;
                                    const searchInput = document.getElementById(
                                        'channelSearchInput');
                                    if (searchInput) {
                                        searchInput.value = selectedChannel;
                                    }
                                    this.style.display = 'none';
                                    table.column(0).search(selectedChannel, true, false).draw();
                                }
                            });
                        }

                        // Hide dropdown if clicked outside
                        document.addEventListener('click', function(e) {
                            if (!e.target.closest('.dropdown-search-container')) {
                                const dropdown = document.getElementById(
                                    'channelSearchDropdown');
                                if (dropdown) {
                                    dropdown.style.display = 'none';
                                }
                            }
                        });

                        // Update totals when table is filtered/searched
                        table.on('draw', function() {
                            var data = table.rows({
                                search: 'applied'
                            }).data().toArray();
                            updateAllTotals(data);
                        });

                        // Handle checkbox changes
                        jq(document).on('change', '.ra-checkbox', function() {
                            const checkbox = this;
                            const isChecked = checkbox.checked;

                            const row = jq(checkbox).closest('tr');
                            const rowData = table.row(row).data();
                            const channel = rowData['Channel '] || null;

                            if (!channel) {
                                console.error('Channel not found in row data.');
                                return;
                            }

                            const confirmMsg =
                                `Are you sure you want to ${isChecked ? 'check' : 'uncheck'} the R&A box for "${channel}"?`;
                            if (!confirm(confirmMsg)) {
                                checkbox.checked = !isChecked;
                                return;
                            }

                            fetch('/update-checkbox', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': window.csrfToken,
                                    },
                                    body: JSON.stringify({
                                        channel: channel,
                                        checked: isChecked
                                    })
                                })
                                .then(res => res.json())
                                .then(data => {
                                    console.log('Laravel proxy response:', data);
                                    alert('Google Sheet updated successfully');
                                })
                                .catch(err => {
                                    console.error('Error:', err);
                                    alert('Error updating checkbox in Google Sheet');
                                    checkbox.checked = !isChecked;
                                });
                        });
                    },
                    error: function(xhr, error, thrown) {
                        console.error('Error loading data:', error, thrown);
                    }
                });
            } catch (e) {
                console.error('Error in document ready:', e);
            }
        });
    </script>
@endsection
