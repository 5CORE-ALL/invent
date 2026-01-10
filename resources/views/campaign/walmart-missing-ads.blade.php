@extends('layouts.vertical', ['title' => 'Walmart Missing Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
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

        /* .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        } */

        /* .tabulator-row:hover {
            background-color: #dbeafe !important;
        } */

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
        .stats-box {
            padding: 12px 16px;
            min-width: 120px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
            flex: 0 1 auto;
        }

        .stats-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stats-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }

        .stats-value {
            font-size: 20px;
            font-weight: 700;
        }

        .stats-value.primary { color: #2563eb; }
        .stats-value.danger { color: #dc2626; }
        .stats-value.success { color: #16a34a; }

        @media (max-width: 768px) {
            .stats-box {
                min-width: 100px;
                padding: 10px 12px;
            }
            
            .stats-value {
                font-size: 18px;
            }
            
            .stats-label {
                font-size: 12px;
            }
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Walmart Missing Ads',
        'sub_title' => 'Walmart Missing Ads',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Walmart Missing Ads - <span class="text-danger ms-1 fs-3" id="total-missing-ads"></span>
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Left side controls -->
                            <div class="col-12 col-lg-8">
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <input type="text" id="global-search" class="form-control form-control-md" placeholder="Search campaign..." style="min-width: 200px; flex: 1 1 auto; max-width: 300px;">

                                    <button id="export-btn" class="btn btn-success btn-md d-flex align-items-center gap-2" style="white-space: nowrap;">
                                        <i class="fa-solid fa-file-excel"></i>
                                        <span class="d-none d-md-inline">Export to Excel</span>
                                        <span class="d-md-none">Export</span>
                                    </button>

                                    <select id="status-filter" class="form-select form-select-md" style="width: 140px;">
                                        <option value="">All Status</option>
                                        <option value="ENABLED">Enabled</option>
                                        <option value="PAUSED">Paused</option>
                                    </select>

                                    <select id="inv-filter" class="form-select form-select-md" style="width: 200px;">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="missingAds-filter" class="form-select form-select-md" style="width: 180px;">
                                        <option value="">Select Missing Ads</option>
                                        <option value="KW Running">KW Running</option>
                                        <option value="KW Missing">KW Missing</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Right side - Stats Boxes -->
                            <div class="col-12 col-lg-4">
                                <div class="d-flex flex-wrap gap-2 justify-content-lg-end justify-content-start">
                                    <div class="stats-box">
                                        <div class="stats-label">Total SKUs</div>
                                        <div id="total-campaigns" class="stats-value primary">0</div>
                                    </div>
                                    
                                    <div class="stats-box">
                                        <div class="stats-label">KW Missing</div> 
                                        <div id="kw-missing" class="stats-value danger">0</div>
                                    </div>

                                    <div class="stats-box">
                                        <div class="stats-label">KW Running</div>
                                        <div id="kw-running" class="stats-value success">0</div>
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
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Global map to track NRA values for all SKUs
            const nraValuesMap = {};

            const invFilter  = document.querySelector("#inv-filter");
            const nrlFilter  = document.querySelector("#nrl-filter");
            const nraFilter  = document.querySelector("#nra-filter");
            const fbaFilter  = document.querySelector("#fba-filter");


            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/walmart/missing/ads/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [
                    {
                        title: "Parent",
                        field: "parent"
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-sku="${sku}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: true
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: true
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);

                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: true
                    },
                    {
                        title: "WA L30",
                        field: "WA_L30",
                        visible: true
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        download: true,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const rowData = row.getData();
                            const sku = rowData.sku;
                            
                            // Get value from rowData.NRA (this is the source of truth)
                            // cell.getValue() can sometimes return stale values
                            let value = rowData.NRA || cell.getValue() || '';
                            
                            // Handle null, undefined, or empty values and normalize
                            if (value) {
                                value = String(value).trim().toUpperCase();
                            } else {
                                value = '';
                            }

                            // Normalize value to match options (RA, NRA, LATER)
                            let normalizedValue = '';
                            if (value === 'RA' || value === 'NRA' || value === 'LATER') {
                                normalizedValue = value;
                            } else {
                                normalizedValue = ''; // Empty if not matching
                            }

                            let bgColor = "";
                            if (normalizedValue === "NRA") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (normalizedValue === "RA") {
                                bgColor = "background-color:#28a745;color:#fff;"; // green
                            } else if (normalizedValue === "LATER") {
                                bgColor = "background-color:#ffc107;color:#000;"; // yellow
                            }

                            // Build select with proper selected attribute
                            const selectHTML = `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        data-value="${normalizedValue}"
                                        style="width: 100px; ${bgColor}">
                                    <option value="RA" ${normalizedValue === 'RA' ? 'selected="selected"' : ''}>RA</option>
                                    <option value="NRA" ${normalizedValue === 'NRA' ? 'selected="selected"' : ''}>NRA</option>
                                    <option value="LATER" ${normalizedValue === 'LATER' ? 'selected="selected"' : ''}>LATER</option>
                                </select>
                            `;
                            return selectHTML;
                        },
                        titleDownload: "NRA",
                        accessorDownload: function(value, data, type, params, column) {
                            // Priority 1: Check global map (most reliable)
                            let nraValue = '';
                            if (data.sku && typeof nraValuesMap !== 'undefined' && nraValuesMap[data.sku]) {
                                nraValue = nraValuesMap[data.sku];
                            }
                            
                            // Priority 2: Get value from row data
                            if (!nraValue) {
                                nraValue = data.NRA || '';
                            }
                            
                            // Priority 3: Try to get from DOM element
                            if (!nraValue && data.sku) {
                                const allSelects = document.querySelectorAll('select.editable-select');
                                const selectElement = Array.from(allSelects).find(select => {
                                    return select.getAttribute('data-sku') === data.sku;
                                });
                                if (selectElement && selectElement.value) {
                                    nraValue = selectElement.value;
                                }
                            }
                            
                            // Return the value, ensuring it's not null or undefined
                            return nraValue || '';
                        },
                        hozAlign: "center",
                        visible: true
                    },
                    {
                        title: "Missing Ads",
                        field: "missing_ads",
                        formatter: function(cell){
                            var row = cell.getRow().getData();
                            var campaign = row.campaignName || '';
                            var sku = row.sku || '';
                            
                            if(campaign){
                                return `
                                    <i class="fa fa-circle" style="color: #28a745; font-size: 12px;"></i>
                                `;
                            }else{
                                return `
                                    <i class="fa fa-circle" style="color: #dc3545; font-size: 12px;"></i>
                                `;
                            } 
                            
                        },
                        accessorDownload: function(value, data, type, params, column) {
                            return data.campaignName ? 'KW Running' : 'KW Missing';
                        },
                    },
                    {
                        title: "Campaign",
                        field: "campaignName",
                        visible: false
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    // Populate map with all NRA values from server response
                    if (response && response.data && Array.isArray(response.data)) {
                        response.data.forEach(row => {
                            if (row.sku && row.NRA) {
                                nraValuesMap[row.sku] = row.NRA;
                            }
                        });
                    }
                    return response.data;
                }
            });

            $(document).on("change", ".editable-select", function () {
                let select = this;
                let sku = select.getAttribute("data-sku");
                let field = select.getAttribute("data-field");
                let value = select.value;

                console.log(`SKU: ${sku}, Field: ${field}, Value: ${value}`);

                // Store in global map immediately
                if (sku) {
                    nraValuesMap[sku] = value;
                }

                fetch('/walmart/save-nr', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, nr: value })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update Tabulator row data
                        let row = table.getRow(sku);
                        if (row) {
                            row.update({NRA: value});
                        }
                        
                        // Ensure map is updated
                        if (sku) {
                            nraValuesMap[sku] = value;
                        }
                        
                        let bgColor = "";
                        if (value === "NRA") {
                            bgColor = "background-color:#dc3545;color:#fff;"; 
                        } else if (value === "RA") {
                            bgColor = "background-color:#28a745;color:#fff;";
                        } else if (value === "LATER") {
                            bgColor = "background-color:#ffc107;color:#000;";
                        }
                        select.style = `width: 100px; ${bgColor}`;
                    } else {
                        console.error('Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });

            // Function to sync dropdown values to map
            function syncDropdownsToMap() {
                const allSelects = document.querySelectorAll('select.editable-select');
                allSelects.forEach(select => {
                    const sku = select.getAttribute('data-sku');
                    const value = select.value || '';  // Empty string भी valid value है
                    if (sku) {
                        nraValuesMap[sku] = value;
                    }
                });
            }

            table.on("tableBuilt", function () {
                // Initialize nraValuesMap with ALL initial data (including empty NRA)
                const initialData = table.getData();
                initialData.forEach(row => {
                    if (row.sku) {
                        // Store initial value, even if empty (will be updated when user selects)
                        nraValuesMap[row.sku] = row.NRA || '';
                    }
                });
                
                // Sync dropdowns after a short delay to ensure DOM is ready
                setTimeout(syncDropdownsToMap, 500);

                function combinedFilter(data) {
                    // 🔍 Global Search
                    let searchVal = ($("#global-search").val() || "").toLowerCase().trim();
                    if (searchVal) {
                        let fieldsToSearch = [
                            data.sku,
                            data.parent,
                            data.campaignName,
                        ].map(f => (f || "").toLowerCase());

                        if (!fieldsToSearch.some(f => f.includes(searchVal))) {
                            return false;
                        }
                    }

                    // 🔹 Status Filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) return false;

                    // 🔹 INV Filter
                    let invFilterVal = $("#inv-filter").val();
                    let inv = parseFloat(data.INV) || 0;

                    if (invFilterVal === "INV_0" && inv !== 0) return false;
                    if (invFilterVal === "OTHERS" && inv === 0) return false;
                    if (invFilterVal === "ALL") {
                        // show all
                    } else if (!invFilterVal && inv === 0) return false; // default hide 0 INV

                    // 🔹 Missing Ads Filter
                    let missingVal = $("#missingAds-filter").val();
                    let kw = data.campaignName || "";

                    if (missingVal === "KW Running" && !kw) return false;
                    if (missingVal === "KW Missing" && kw) return false;

                    return true;
                }


                table.setFilter(combinedFilter);

                function updateCampaignStats() {
                    let visibleData = table.getData("active");

                    let kwMissing = 0;
                    let kwRunning = 0;

                    visibleData.forEach(row => {
                        let kw = row.campaignName || "";

                        if (kw) kwRunning++;
                        else kwMissing++;
                    });

                    let totalMissingAds = `( ${kwMissing} )`;

                    $("#total-campaigns").text(visibleData.length);
                    $("#kw-missing").text(kwMissing);
                    $("#total-missing-ads").text(totalMissingAds);
                    $("#kw-running").text(kwRunning);
                }

                // ✅ Trigger Update on Every Filter / Search Change
                function reapplyFiltersAndUpdate() {
                    table.setFilter(combinedFilter);
                    updateCampaignStats();
                }

                // ✅ Events
                $("#global-search").on("keyup", reapplyFiltersAndUpdate);
                $("#status-filter, #inv-filter, #missingAds-filter").on("change", reapplyFiltersAndUpdate);

                table.on("dataFiltered", function() {
                    updateCampaignStats();
                    setTimeout(syncDropdownsToMap, 100);
                });
                table.on("pageLoaded", function() {
                    updateCampaignStats();
                    setTimeout(syncDropdownsToMap, 100);
                });
                table.on("dataProcessed", function() {
                    updateCampaignStats();
                    setTimeout(syncDropdownsToMap, 100);
                });
                table.on("dataLoaded", function() {
                    setTimeout(syncDropdownsToMap, 100);
                });

                // ✅ Initial Stats Load
                updateCampaignStats();
            });

            // ✅ Export Functionality - Custom Manual Export
            document.getElementById("export-btn").addEventListener("click", function() {
                // Get all active row data from Tabulator
                let allRowData = table.getData("active");
                
                // STEP 1: Collect all visible dropdowns and their selected values
                const allSelects = document.querySelectorAll('select.editable-select');
                const dropdownValuesMap = {}; // Map to store SKU -> selected value from dropdowns
                
                allSelects.forEach(select => {
                    const sku = select.getAttribute('data-sku');
                    const value = select.value; // This will be "RA", "NRA", or "LATER"
                    if (sku && value) {
                        dropdownValuesMap[sku] = value;
                    }
                });
                
                // STEP 2: Prepare export data
                const exportData = [];
                
                // Headers
                exportData.push([
                    "Parent",
                    "SKU", 
                    "INV",
                    "OV L30",
                    "DIL %",
                    "WA L30",
                    "NRA",
                    "Missing Ads"
                ]);
                
                // STEP 3: Process each row
                allRowData.forEach((rowData, index) => {
                    const sku = rowData.sku;
                    let nraValue = '';
                    
                    // Priority 1: If dropdown exists (visible row), use its selected value
                    if (sku && dropdownValuesMap.hasOwnProperty(sku)) {
                        nraValue = dropdownValuesMap[sku];
                    }
                    // Priority 2: If rowData.NRA has value (from server), use it
                    else if (rowData.NRA) {
                        nraValue = rowData.NRA;
                    }
                    // Priority 3: If no value found and dropdown not visible, use "RA" as default
                    // (Because RA is the first option in dropdown, so it's the default selected)
                    else {
                        nraValue = 'RA'; // Default value when dropdown is not visible and no server value
                    }
                    
                    // Calculate DIL %
                    const l30 = parseFloat(rowData.L30) || 0;
                    const inv = parseFloat(rowData.INV) || 0;
                    let dilPercent = '0%';
                    if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                        dilPercent = Math.round((l30 / inv) * 100) + '%';
                    }
                    
                    // Missing Ads status
                    const missingAds = rowData.campaignName ? 'KW Running' : 'KW Missing';
                    
                    exportData.push([
                        rowData.parent || '',
                        sku || '',
                        rowData.INV || 0,
                        rowData.L30 || 0,
                        dilPercent,
                        rowData.WA_L30 || 0,
                        nraValue || '',  // This should now have the correct value
                        missingAds
                    ]);
                });
                
                // Create workbook using SheetJS
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(exportData);
                
                // Set column widths
                ws['!cols'] = [
                    { wch: 15 }, // Parent
                    { wch: 25 }, // SKU
                    { wch: 10 }, // INV
                    { wch: 10 }, // OV L30
                    { wch: 10 }, // DIL %
                    { wch: 10 }, // WA L30
                    { wch: 10 }, // NRA
                    { wch: 15 }  // Missing Ads
                ];
                
                XLSX.utils.book_append_sheet(wb, ws, "Missing Ads");
                
                // Download file
                XLSX.writeFile(wb, "walmart-missing-ads.xlsx");
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "WA_L30", "NRA"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-missingAds-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["campaignName"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.body.style.zoom = "78%";
        });
    </script>
@endsection
