@extends('layouts.vertical', ['title' => 'Meta - ALL ADS', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 16px 15px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
            white-space: nowrap;
            overflow: visible;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        #account-health-master .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {

            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
        }

        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'ALL ADS',
        'sub_title' => 'ALL ADS',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            META ALL ADS
                            @if(isset($latestUpdatedAt))
                                <small class="text-muted ms-3" style="font-size: 0.75rem;">
                                    Last Updated: {{ $latestUpdatedAt }}
                                </small>
                            @endif
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="status-filter" class="form-select form-select-md">
                                        <option value="">All Status</option>
                                        <option value="ACTIVE">Active</option>
                                        <option value="INACTIVE">Inactive</option>
                                        <option value="NOT_DELIVERING">Not Delivering</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-success" id="sync-btn">
                                        <i class="fa fa-sync me-1"></i>Sync from Google Sheets
                                    </button>
                                    <button class="btn btn-success btn-md">
                                        <i class="fa fa-bullhorn me-1"></i>
                                        Total Ads: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
                                    </button>
                                    <button class="btn btn-primary btn-md">
                                        <i class="fa fa-percent me-1"></i>
                                        Filtered: <span id="percentage-campaigns" class="fw-bold ms-1 fs-4">0%</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search campaign...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var table = new Tabulator("#budget-under-table", {
                index: "campaign_id",
                ajaxURL: "/meta-all-ads-control/data",
                layout: "fitColumns",
                pagination: "local",
                paginationSize: 25,
                movableColumns: true,
                resizableColumns: true,
                columns: [
                    {
                        title: "Campaign Name",
                        field: "campaign_name",
                        minWidth: 320,
                        headerSort: true
                    },
                    {
                        title: "Campaign ID",
                        field: "campaign_id",
                        minWidth: 200,
                        headerSort: true
                    },
                    {
                        title: "AD Type",
                        field: "ad_type",
                        minWidth: 180,
                        headerSort: true,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const campaignName = row.getData().campaign_name;
                            const value = cell.getValue() || '';

                            let bgColor = "";
                            if (value === "Single Image") {
                                bgColor = "background-color:#17a2b8;color:#fff;";
                            } else if (value === "Single Video") {
                                bgColor = "background-color:#6610f2;color:#fff;";
                            } else if (value === "Carousal") {
                                bgColor = "background-color:#fd7e14;color:#fff;";
                            } else if (value === "Existing Post") {
                                bgColor = "background-color:#20c997;color:#fff;";
                            } else if (value === "Catalogue Ad") {
                                bgColor = "background-color:#e83e8c;color:#fff;";
                            }

                            return `
                                <select class="form-select form-select-sm editable-ad-type" 
                                        data-campaign-name="${campaignName}" 
                                        style="width: 160px; ${bgColor} cursor:pointer;">
                                    <option value="">Select Type</option>
                                    <option value="Single Image" ${value === 'Single Image' ? 'selected' : ''}>Single Image</option>
                                    <option value="Single Video" ${value === 'Single Video' ? 'selected' : ''}>Single Video</option>
                                    <option value="Carousal" ${value === 'Carousal' ? 'selected' : ''}>Carousal</option>
                                    <option value="Existing Post" ${value === 'Existing Post' ? 'selected' : ''}>Existing Post</option>
                                    <option value="Catalogue Ad" ${value === 'Catalogue Ad' ? 'selected' : ''}>Catalogue Ad</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "BGT",
                        field: "budget",
                        minWidth: 100,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        }
                    },
                    {
                        title: "IMP L30",
                        field: "impressions_l30",
                        minWidth: 135,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const formatted = value ? parseInt(value).toLocaleString() : '0';
                            return `
                                <span>${formatted}</span>
                                <i class="fa fa-info-circle text-primary toggle-imp-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "IMP L60",
                        field: "impressions_l60",
                        minWidth: 135,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseInt(value).toLocaleString() : '0';
                        },
                        visible: false
                    },
                    {
                        title: "IMP L7",
                        field: "impressions_l7",
                        minWidth: 135,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseInt(value).toLocaleString() : '0';
                        },
                        visible: false
                    },
                    {
                        title: "SPENT L30",
                        field: "spend_l30",
                        width: 155,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const formatted = value ? parseFloat(value).toFixed(2) : '0.00';
                            return `
                                <span>${formatted}</span>
                                <i class="fa fa-info-circle text-primary toggle-spent-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "SPENT L60",
                        field: "spend_l60",
                        width: 155,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        },
                        visible: false
                    },
                    {
                        title: "SPENT L7",
                        field: "spend_l7",
                        width: 155,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        },
                        visible: false
                    },
                    {
                        title: "CLKS L30",
                        field: "clicks_l30",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const formatted = value ? parseInt(value).toLocaleString() : '0';
                            return `
                                <span>${formatted}</span>
                                <i class="fa fa-info-circle text-primary toggle-clks-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CLKS L60",
                        field: "clicks_l60",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseInt(value).toLocaleString() : '0';
                        },
                        visible: false
                    },
                    {
                        title: "CLKS L7",
                        field: "clicks_l7",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseInt(value).toLocaleString() : '0';
                        },
                        visible: false
                    },
                    {
                        title: "AD SLS L30",
                        field: "sales_l30",
                        width: 160,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const formatted = value ? parseFloat(value).toFixed(2) : '0.00';
                            return `
                                <span>${formatted}</span>
                                <i class="fa fa-info-circle text-primary toggle-sales-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "AD SLS L60",
                        field: "sales_l60",
                        width: 160,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        },
                        visible: false
                    },
                    {
                        title: "AD SLS L7",
                        field: "sales_l7",
                        width: 160,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        },
                        visible: false
                    },
                    {
                        title: "AD SLD L30",
                        field: "sales_delivered_l30",
                        width: 160,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const formatted = value ? parseFloat(value).toFixed(2) : '0.00';
                            return `
                                <span>${formatted}</span>
                                <i class="fa fa-info-circle text-primary toggle-sld-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "AD SLD L60",
                        field: "sales_delivered_l60",
                        width: 160,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        },
                        visible: false
                    },
                    {
                        title: "AD SLD L7",
                        field: "sales_delivered_l7",
                        width: 160,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) : '0.00';
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L30",
                        field: "acos_l30",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const formatted = value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                            return `
                                <span>${formatted}</span>
                                <i class="fa fa-info-circle text-primary toggle-acos-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "ACOS L60",
                        field: "acos_l60",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L7",
                        field: "acos_l7",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                        },
                        visible: false
                    },
                    {
                        title: "CVR L30",
                        field: "cvr_l30",
                        width: 145,
                        headerSort: true,
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            let cvr = Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1);
                            let color = "";

                            if (value < 5) {
                                color = "red";
                            } else if (value >= 5 && value <= 10) {
                                color = "green";
                            } else if (value > 10) {
                                color = "pink";
                            }

                            if (color == "pink") {
                                return `
                                    <span class="dil-percent-value ${color}">
                                        ${cvr}%
                                    </span>
                                    <i class="fa fa-info-circle text-primary toggle-cvr-cols-btn" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                            } else {
                                return `
                                    <span style="font-weight:600; color:${color};">
                                        ${cvr}%
                                    </span>
                                    <i class="fa fa-info-circle text-primary toggle-cvr-cols-btn" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                            }
                        }
                    },
                    {
                        title: "CVR L60",
                        field: "cvr_l60",
                        width: 145,
                        headerSort: true,
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            let cvr = Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1);
                            let color = "";

                            if (value < 5) {
                                color = "red";
                            } else if (value >= 5 && value <= 10) {
                                color = "green";
                            } else if (value > 10) {
                                color = "pink";
                            }

                            if (color == "pink") {
                                return `
                                    <span class="dil-percent-value ${color}">
                                        ${cvr}%
                                    </span>
                                `;
                            } else {
                                return `
                                    <span style="font-weight:600; color:${color};">
                                        ${cvr}%
                                    </span>
                                `;
                            }
                        },
                        visible: false
                    },
                    {
                        title: "CVR L7",
                        field: "cvr_l7",
                        width: 145,
                        headerSort: true,
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            let cvr = Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1);
                            let color = "";

                            if (value < 5) {
                                color = "red";
                            } else if (value >= 5 && value <= 10) {
                                color = "green";
                            } else if (value > 10) {
                                color = "pink";
                            }

                            if (color == "pink") {
                                return `
                                    <span class="dil-percent-value ${color}">
                                        ${cvr}%
                                    </span>
                                `;
                            } else {
                                return `
                                    <span style="font-weight:600; color:${color};">
                                        ${cvr}%
                                    </span>
                                `;
                            }
                        },
                        visible: false
                    },
                    {
                        title: "Status",
                        field: "status",
                        width: 150,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            let bgColor = '#6c757d';
                            let displayText = value;
                            
                            if (value === 'ACTIVE') {
                                bgColor = '#28a745';
                                displayText = 'Active';
                            } else if (value === 'INACTIVE') {
                                bgColor = '#dc3545';
                                displayText = 'Inactive';
                            } else if (value === 'NOT_DELIVERING') {
                                bgColor = '#ffc107';
                                displayText = 'Not Delivering';
                            }
                            
                            return `<span class="badge" style="background-color: ${bgColor}; color: white; font-size: 0.85rem; padding: 6px 12px;">${displayText}</span>`;
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });

            table.on("tableBuilt", function() {
                function combinedFilter(data) {
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.campaign_name?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.status !== statusVal) {
                        return false;
                    }

                    return true;
                }

                table.setFilter(combinedFilter);

                function updateCampaignStats() {
                    let allRows = table.getData();
                    let filteredRows = allRows.filter(combinedFilter);

                    let total = allRows.length;
                    let filtered = filteredRows.length;

                    let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                    document.getElementById("total-campaigns").innerText = filtered;
                    document.getElementById("percentage-campaigns").innerText = percentage + "%";
                }

                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                $("#global-search").on("keyup", function() {
                    table.setFilter(combinedFilter);
                });

                $("#status-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            // Sync from Google Sheets
            $('#sync-btn').on('click', function () {
                if (!confirm('This will sync data from Google Sheets. Continue?')) {
                    return;
                }

                // Show loading state
                $('#sync-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Syncing...');

                $.ajax({
                    url: "{{ route('meta.ads.sync') }}",
                    type: "POST",
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function (response) {
                        $('#sync-btn').prop('disabled', false).html('<i class="fa fa-sync me-1"></i>Sync from Google Sheets');
                        
                        alert('Sync successful!\nL30 synced: ' + response.l30_synced + ' campaigns\nL7 synced: ' + response.l7_synced + ' campaigns');
                        table.replaceData();
                    },
                    error: function (xhr) {
                        $('#sync-btn').prop('disabled', false).html('<i class="fa fa-sync me-1"></i>Sync from Google Sheets');
                        
                        let message = 'Sync failed';

                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            message = xhr.responseJSON.error;
                        }

                        alert(message);
                    }
                });
            });

            // Toggle handlers for column visibility
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-imp-cols-btn")) {
                    let colsToToggle = ["impressions_l60", "impressions_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spent-cols-btn")) {
                    let colsToToggle = ["spend_l60", "spend_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-clks-cols-btn")) {
                    let colsToToggle = ["clicks_l60", "clicks_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-sales-cols-btn")) {
                    let colsToToggle = ["sales_l60", "sales_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-sld-cols-btn")) {
                    let colsToToggle = ["sales_delivered_l60", "sales_delivered_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-acos-cols-btn")) {
                    let colsToToggle = ["acos_l60", "acos_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cvr-cols-btn")) {
                    let colsToToggle = ["cvr_l60", "cvr_l7"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            // Handle AD Type dropdown changes
            $(document).on('change', '.editable-ad-type', function() {
                const campaignName = $(this).data('campaign-name');
                const newAdType = $(this).val();
                const selectElement = $(this);

                $.ajax({
                    url: "/meta-all-ads-control/update-ad-type",
                    type: "POST",
                    data: {
                        campaign_name: campaignName,
                        ad_type: newAdType
                    },
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function(response) {
                        console.log('Ad Type updated successfully');
                        
                        // Update background color based on selection
                        let bgColor = "";
                        if (newAdType === "Single Image") {
                            bgColor = "background-color:#17a2b8;color:#fff;";
                        } else if (newAdType === "Single Video") {
                            bgColor = "background-color:#6610f2;color:#fff;";
                        } else if (newAdType === "Carousal") {
                            bgColor = "background-color:#fd7e14;color:#fff;";
                        } else if (newAdType === "Existing Post") {
                            bgColor = "background-color:#20c997;color:#fff;";
                        } else if (newAdType === "Catalogue Ad") {
                            bgColor = "background-color:#e83e8c;color:#fff;";
                        }
                        
                        selectElement.attr('style', `width: 160px; ${bgColor} cursor:pointer;`);
                    },
                    error: function(xhr) {
                        console.error('Failed to update Ad Type');
                        alert('Failed to update Ad Type');
                    }
                });
            });

            document.body.style.zoom = "70%";
        });
    </script>
@endsection
