@extends('layouts.vertical', ['title' => 'Amazon Missing Ads (FBM)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
            min-width: 130px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
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
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon Missing Ads (FBM)',
        'sub_title' => 'Amazon Missing Ads (FBM)',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Amazon Missing Ads (FBM) - <span class="text-danger ms-1 fs-3" id="total-missing-ads"></span>
                        </h4>

                        <!-- Filters Row -->
                        <!-- Stats Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                    <!-- Left side controls -->
                                    <div class="d-flex gap-3 flex-wrap">
                                        <!-- Search input -->
                                        <div class="flex-grow-1" style="min-width: 200px; max-width: 300px;">
                                            <input type="text" 
                                                   id="global-search" 
                                                   class="form-control" 
                                                   placeholder="Search SKU or campaign..."
                                                   style="height: 38px;">
                                        </div>

                                        <!-- Filter dropdowns -->
                                        <div class="d-flex gap-2 flex-wrap">
                                            <select id="status-filter" 
                                                    class="form-select" 
                                                    style="width: 130px; height: 38px;">
                                                <option value="">select status</option>
                                                <option value="ENABLED">Enabled</option>
                                                <option value="PAUSED">Paused</option>
                                                <option value="ARCHIVED">Archived</option>
                                            </select>

                                            <select id="inv-filter" 
                                                    class="form-select" 
                                                    style="width: 130px; height: 38px;">
                                                <option value="">select inv</option>
                                                <option value="ALL">All</option>
                                                <option value="INV_0">0 INV</option>
                                                <option value="OTHERS">Others</option>
                                            </select>

                                            <select id="nra-filter" 
                                                    class="form-select" 
                                                    style="width: 130px; height: 38px;">
                                                <option value="">select NRA</option>
                                                <option value="ALL">All</option>
                                                <option value="RA">RA</option>
                                                <option value="NRA">NRA</option>
                                                <option value="LATER">Later</option>
                                            </select>

                                            <select id="nrl-filter" 
                                                    class="form-select" 
                                                    style="width: 130px; height: 38px;">
                                                <option value="">select NRL</option>
                                                <option value="ALL">All</option>
                                                <option value="REQ">RL</option>
                                                <option value="NR">NRL</option>
                                            </select>

                                            <select id="missingAds-filter" 
                                                    class="form-select" 
                                                    style="width: 150px; height: 38px;">
                                                <option value="">select missing</option>
                                                <option value="Both Running">Both Running</option>
                                                <option value="KW Missing">KW Missing</option>
                                                <option value="PT Missing">PT Missing</option>
                                                <option value="Both Missing">KW & PT Missing</option>
                                            </select>

                                            <button id="all-missing-btn" class="btn btn-primary text-black fw-bold" 
                                                    style="height: 38px;">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                All Missing
                                            </button>
                                            <button id="show-all-btn" class="btn btn-info text-black fw-bold" 
                                                    style="height: 38px;">
                                                <i class="fas fa-list me-1"></i>
                                                Show All
                                            </button>
                                            <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success d-flex align-items-center justify-content-center">
                                                <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Right side - Stats Boxes -->
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="stats-box">
                                            <div class="stats-label">Total SKU</div>
                                            <div id="total-campaigns" class="stats-value primary">0</div>
                                        </div>
                                        <div class="stats-box">
                                            <div class="stats-label">Total RA</div>
                                            <div id="total-ra" class="stats-value success">0</div>
                                        </div>
                                        <div class="stats-box">
                                            <div class="stats-label">Total NRA</div>
                                            <div id="total-nra" class="stats-value danger">0</div>
                                        </div>

                                        <div class="stats-box">
                                            <div class="stats-label">KW Missing</div> 
                                            <div id="kw-missing" class="stats-value danger">0</div>
                                        </div>

                                        <div class="stats-box">
                                            <div class="stats-label">PT Missing</div>
                                            <div id="pt-missing" class="stats-value danger">0</div>
                                        </div>
                                        <div class="stats-box">
                                            <div class="stats-label">KW Running</div>
                                            <div id="kw-running" class="stats-value success">0</div>
                                        </div>
                                         <div class="stats-box">
                                            <div class="stats-label">PT Running</div>
                                            <div id="pt-running" class="stats-value success">0</div>
                                        </div>
                                        <div class="stats-box">
                                            <div class="stats-label">Both Ads Running</div>
                                            <div id="both-running" class="stats-value success">0</div>
                                        </div>
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
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/amazon/missing/ads/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                virtualDomBuffer: 300,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Parent",
                        field: "parent"
                    },
                    {
                        title: "SKU",
                        field: "sku"
                    },
                    {
                        title: "INV",
                        field: "INV"
                    },
                    {
                        title: "OV L30",
                        field: "L30"
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
                    },
                    {
                        title: "AL 30",
                        field: "A_L30"
                    },
                    {
                        title: "A DIL %",
                        field: "A DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const al30 = parseFloat(data.A_L30);
                            const inv = parseFloat(data.INV);

                            if (!isNaN(al30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (al30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue() || 'REQ'; // Default to REQ if no value

                            let bgColor = "background-color:#28a745;color:#000;"; // Default green for REQ
                            if (value === "NR") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRL"
                                        style="width: 90px; ${bgColor}">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>RL</option>
                                    <option value="NR" ${value === 'NR' ? 'selected' : ''}>NRL</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const nrlValue = row.getData().NRL || 'REQ';
                            let value = cell.getValue()?.trim() || 'RA'; // Default to RA if no value
                            
                            // If NRL is NR, force NRA to be NRA
                            if (nrlValue === 'NR') {
                                value = 'NRA';
                            }

                            let bgColor = "background-color:#28a745;color:#000;"; // Default green for RA
                            if (value === "NRA") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (value === "LATER") {
                                bgColor = "background-color:#ffc107;color:#000;"; // yellow
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRA"
                                        style="width: 100px; ${bgColor}">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "FBA",
                        field: "FBA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "FBA") {
                                bgColor = "background-color:#007bff;color:#fff;"; // blue
                            } else if (value === "FBM") {
                                bgColor = "background-color:#6f42c1;color:#fff;"; // purple
                            } else if (value === "BOTH") {
                                bgColor = "background-color:#90ee90;color:#000;"; // light green
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="FBA"
                                        style="width: 90px; ${bgColor}">
                                    <option value="FBA" ${value === 'FBA' ? 'selected' : ''}>FBA</option>
                                    <option value="FBM" ${value === 'FBM' ? 'selected' : ''}>FBM</option>
                                    <option value="BOTH" ${value === 'BOTH' ? 'selected' : ''}>BOTH</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Missing KW Ads",
                        field: "missing_kw_ads",
                        formatter: function(cell){
                            var row = cell.getRow().getData();
                            var kwCampaign = row.kw_campaign_name || '';
                            var nra = (row.NRA || '').trim();
                            var sku = row.sku || '';
                            var inv = parseFloat(row.INV) || 0;
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if(!isParent && inv > 0 && nra !== 'NRA' && nra !== 'LATER'){
                                if(!kwCampaign){
                                    return `<span style="color: red;">KW Missing</span>`;
                                } else {
                                    return `<span style="color: green;">KW Running</span>`;
                                }
                            }
                            return '';
                        }
                    },
                    {
                        title: "Missing PT Ads",
                        field: "missing_pt_ads",
                        formatter: function(cell){
                            var row = cell.getRow().getData();
                            var ptCampaign = row.pt_campaign_name || '';
                            var nra = (row.NRA || '').trim();
                            var sku = row.sku || '';
                            var inv = parseFloat(row.INV) || 0;
                            const isParent = sku.toUpperCase().includes("PARENT");

                            if(!isParent && inv > 0 && nra !== 'NRA' && nra !== 'LATER'){
                                if(!ptCampaign){
                                    return `<span style="color: red;">PT Missing</span>`;
                                } else {
                                    return `<span style="color: green;">PT Running</span>`;
                                }
                            }
                            return '';
                        }
                    },
                    {
                        title: "KW Campaign",
                        field: "kw_campaign_name"
                    },
                    {
                        title: "PT Campaign",
                        field: "pt_campaign_name"
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });


            document.addEventListener("change", function(e){
                if(e.target.classList.contains("editable-select")){
                    let sku   = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    // Set background color based on field and value
                    if(field === "NRA") {
                        if(value === "NRA") {
                            e.target.style.backgroundColor = "#dc3545";
                            e.target.style.color = "#fff";
                        } else if(value === "RA") {
                            e.target.style.backgroundColor = "#28a745";
                            e.target.style.color = "#000";
                        } else if(value === "LATER") {
                            e.target.style.backgroundColor = "#ffc107";
                            e.target.style.color = "#000";
                        }
                    } else if(field === "NRL") {
                        if(value === "NR") {
                            e.target.style.backgroundColor = "#dc3545";
                            e.target.style.color = "#fff";
                        } else if(value === "REQ") {
                            e.target.style.backgroundColor = "#28a745";
                            e.target.style.color = "#000";
                        }
                    } else if(field === "FBA") {
                        if(value === "FBA") {
                            e.target.style.backgroundColor = "#007bff";
                            e.target.style.color = "#fff";
                        } else if(value === "FBM") {
                            e.target.style.backgroundColor = "#6f42c1";
                            e.target.style.color = "#fff";
                        } else if(value === "BOTH") {
                            e.target.style.backgroundColor = "#90ee90";
                            e.target.style.color = "#000";
                        }
                    }

                    // Determine the correct endpoint based on field
                    let endpoint = field === "NRL" ? '/listing_amazon/save-status' : '/update-amazon-nr-nrl-fba';
                    
                    // Prepare the request body based on the endpoint
                    let requestBody = field === "NRL" 
                        ? { sku: sku, nr_req: value }
                        : { sku: sku, field: field, value: value };

                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(requestBody)
                    })
                    .then(res => res.json())
                    .then(data => {
                        // Update the row data in the table instead of replacing all data
                        let row = table.getRow(sku);
                        if(row) {
                            let rowData = row.getData();
                            rowData[field] = value;
                            row.update(rowData);
                            
                            // If NRL is changed to NR, automatically set NRA to NRA
                            if(field === "NRL" && value === "NR") {
                                // Find the NRA select element for this row
                                let nraSelect = document.querySelector(`select[data-sku="${sku}"][data-field="NRA"]`);
                                if(nraSelect && nraSelect.value !== "NRA") {
                                    nraSelect.value = "NRA";
                                    nraSelect.style.backgroundColor = "#dc3545";
                                    nraSelect.style.color = "#fff";
                                    
                                    // Save NRA value to backend
                                    fetch('/update-amazon-nr-nrl-fba', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            sku: sku,
                                            field: "NRA",
                                            value: "NRA"
                                        })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        rowData.NRA = "NRA";
                                        row.update(rowData);
                                        console.log('✅ Auto-updated NRA to NRA for SKU:', sku);
                                    })
                                    .catch(err => {
                                        console.error('❌ Failed to auto-update NRA:', err);
                                    });
                                }
                            }
                            
                            // Immediately update stats to reflect the change
                            if(typeof window.updateCampaignStats === 'function') {
                                window.updateCampaignStats();
                            }
                        }
                        
                        console.log('✅ Saved:', field, '=', value, 'for SKU:', sku);
                    })
                    .catch(err => {
                        console.error('❌ Save failed:', err);
                        alert('Failed to save changes. Please try again.');
                    });
                }
            });

            // ✅ Update Stats Based on *Visible (Filtered)* Data - Make it globally accessible
            window.updateCampaignStats = function() {
                let visibleData = table.getData("active");

                let stats = {
                    bothMissing: 0,
                    kwMissing: 0,
                    ptMissing: 0,
                    bothRunning: 0,
                    kwRunning: 0,
                    ptRunning: 0,
                    totalNRA: 0,
                    totalRA: 0
                };

                visibleData.forEach(row => {
                    let kw = row.kw_campaign_name || "";
                    let pt = row.pt_campaign_name || "";
                    let nra = (row.NRA || "").trim();
                    let inv = parseFloat(row.INV) || 0;

                    // Total NRA Count (all NRA regardless of inventory)
                    if(nra === "NRA") stats.totalNRA++;

                    // Total RA Count (all non-NRA with INV > 0)
                    if(nra !== "NRA" && inv > 0) stats.totalRA++;

                    // Only process remaining counts for non-NRA with INV > 0
                    if(nra !== "NRA" && nra !== "LATER" && inv > 0) {
                        const hasKW = !!kw;
                        const hasPT = !!pt;
                        
                        // Count running campaigns
                        if (hasKW) stats.kwRunning++;
                        if (hasPT) stats.ptRunning++;
                        
                        // Count missing campaigns independently (includes both missing in each count)
                        if (!hasKW) {
                            stats.kwMissing++;  // KW is missing (includes both missing)
                        }
                        if (!hasPT) {
                            stats.ptMissing++;  // PT is missing (includes both missing)
                        }
                        
                        // Count combined statuses
                        if (hasKW && hasPT) {
                            stats.bothRunning++;
                        } else if (!hasKW && !hasPT) {
                            stats.bothMissing++;  // Both are missing
                        }
                    }
                });

                // Total Missing Ads Count (unique SKUs with at least one missing ad)
                // Since kwMissing and ptMissing now include bothMissing, we calculate unique missing SKUs
                let totalMissingAds2 = stats.bothMissing + (stats.kwMissing - stats.bothMissing) + (stats.ptMissing - stats.bothMissing);

                // Batch update all DOM elements at once
                const updates = {
                    "#total-campaigns": visibleData.length,
                    "#both-missing": stats.bothMissing,
                    "#kw-missing": stats.kwMissing,
                    "#pt-missing": stats.ptMissing,
                    "#both-running": stats.bothRunning,
                    "#total-missing-ads": `( ${totalMissingAds2} ) `,
                    "#kw-running": stats.kwRunning,
                    "#pt-running": stats.ptRunning,
                    "#total-nra": stats.totalNRA,
                    "#total-ra": stats.totalRA
                };

                // Single DOM update
                requestAnimationFrame(() => {
                    Object.entries(updates).forEach(([selector, value]) => {
                        $(selector).text(value);
                    });
                });

                // Send to backend (non-blocking, debounced)
                clearTimeout(window.statsUpdateTimer);
                window.statsUpdateTimer = setTimeout(() => {
                    $.ajax({
                        url: "{{ route('adv-amazon.missing.save-data') }}",
                        method: 'GET',
                        data: {
                            totalMissingAds: totalMissingAds2,
                            kwMissing: stats.kwMissing,
                            ptMissing: stats.ptMissing,
                            bothMissing: stats.bothMissing
                        }
                    });
                }, 500);
            };

            table.on("tableBuilt", function () {

                // ✅ Combined Filter Function
                function combinedFilter(data) {
                    const sku = data.sku || '';
                    const isParent = sku.toUpperCase().includes("PARENT");
                    if (isParent) return false; // Exclude parent rows

                    // 🔹 Show only INV > 0 by default (unless filter says otherwise)
                    let inv = parseFloat(data.INV) || 0;
                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal && inv <= 0) return false;

                    // 🔹 Exclude "Both Running" by default (unless missingAds filter is applied)
                    let missingVal = $("#missingAds-filter").val();
                    if (!missingVal) {
                        let kwCamp = data.kw_campaign_name || "";
                        let ptCamp = data.pt_campaign_name || "";
                        let nraDefault = (data.NRA || "").trim();
                        if (kwCamp && ptCamp && nraDefault !== "NRA" && nraDefault !== "LATER") return false;
                    }

                    // 🔹 Global Search
                    let searchVal = ($("#global-search").val() || "").toLowerCase().trim();
                    if (searchVal) {
                        let fieldsToSearch = [
                            data.sku,
                            data.parent,
                            data.kw_campaign_name,
                            data.pt_campaign_name
                        ].map(f => (f || "").toLowerCase());

                        if (!fieldsToSearch.some(f => f.includes(searchVal))) {
                            return false;
                        }
                    }

                    // 🔹 Status Filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) return false;

                    // 🔹 INV Filter
                    if (invFilterVal === "INV_0" && inv !== 0) return false;
                    if (invFilterVal === "OTHERS" && inv === 0) return false;

                    // 🔹 NRA Filter
                    let nraFilterVal = $("#nra-filter").val();
                    let nra = (data.NRA || "").trim();
                    if (nraFilterVal) {
                        if (nraFilterVal === "ALL") {
                            // Show all records
                        } else if (nraFilterVal === "RA") {
                            // RA filter should show RA and LATER, exclude only NRA
                            if (nra === "NRA") return false;
                        } else if (nraFilterVal === "LATER") {
                            // LATER filter shows only LATER
                            if (nra !== "LATER") return false;
                        } else if (nraFilterVal === "NRA") {
                            // NRA filter shows only NRA
                            if (nra !== "NRA") return false;
                        }
                    } else {
                        // 🔹 By default, hide NRA SKUs when no filter is selected
                        if (nra === "NRA") return false;
                    }

                    // 🔹 NRL Filter
                    let nrlFilterVal = $("#nrl-filter").val();
                    let nrl = (data.NRL || "").trim();
                    if (nrlFilterVal) {
                        if (nrlFilterVal === "ALL") {
                            // Show all records
                        } else if (nrlFilterVal === "REQ") {
                            // RL filter should show only RL (REQ)
                            if (nrl !== "REQ") return false;
                        } else if (nrlFilterVal === "NR") {
                            // NR filter shows only NR
                            if (nrl !== "NR") return false;
                        }
                    } else {
                        // 🔹 By default, hide NR SKUs when no filter is selected
                        if (nrl === "NR") return false;
                    }
                    
                    // 🔹 Missing Ads Filter
                    let kw = (data.kw_campaign_name || "").toString().trim();
                    let pt = (data.pt_campaign_name || "").toString().trim();
                    const hasKW = kw !== "";
                    const hasPT = pt !== "";
                    const invVal = parseFloat(data.INV) || 0;
                    const nraVal = (data.NRA || "").toString().trim();

                    // Only include rows where INV > 0 and not marked NRA for missing-ads checks
                    if (missingVal === "Both Running" && !(hasKW && hasPT && invVal > 0 && nraVal !== 'NRA' && nraVal !== 'LATER')) return false;
                    if (missingVal === "KW Missing" && !( !hasKW && hasPT && invVal > 0 && nraVal !== 'NRA')) return false;
                    if (missingVal === "PT Missing" && !( hasKW && !hasPT && invVal > 0 && nraVal !== 'NRA')) return false;
                    if (missingVal === "Both Missing" && !( !hasKW && !hasPT && invVal > 0 && nraVal !== 'NRA')) return false;

                    return true;
                }

                // ✅ Apply Filter
                table.setFilter(combinedFilter);

                // ✅ Trigger Update on Every Filter / Search Change
                function reapplyFiltersAndUpdate() {
                    table.setFilter(combinedFilter);
                    // Debounce stats update
                    clearTimeout(window.filterUpdateTimer);
                    window.filterUpdateTimer = setTimeout(() => {
                        window.updateCampaignStats();
                    }, 150);
                }

                // ✅ Events
                $("#global-search").on("keyup", reapplyFiltersAndUpdate);
                $("#status-filter, #inv-filter, #nra-filter, #nrl-filter, #missingAds-filter").on("change", reapplyFiltersAndUpdate);

                // Remove redundant event listeners - only keep dataProcessed
                table.on("dataProcessed", window.updateCampaignStats);

                // ✅ Initial Stats Load
                window.updateCampaignStats();

                // Update All Missing Button Handler
                $("#all-missing-btn").off("click").on("click", function() {
                    // Clear all filters first
                    $("#global-search").val("");
                    $("#status-filter").val("");
                    $("#inv-filter").val("");
                    $("#nra-filter").val("RA");
                    $("#nrl-filter").val("");
                    $("#missingAds-filter").val("");
                    
                    // Custom filter to show only rows with missing ads
                    table.setFilter(function(data) {
                        const sku = data.sku || '';
                        const isParent = sku.toUpperCase().includes("PARENT");
                        
                        let kw = data.kw_campaign_name || "";
                        let pt = data.pt_campaign_name || "";
                        let nra = (data.NRA || "").trim();
                        let nrl = (data.NRL || "").trim();
                        let inv = parseFloat(data.INV) || 0;
                        
                        // Show only non-parent, non-NRA, non-NR, INV > 0, and missing at least one ad
                        return !isParent && nra !== "NRA" && nra !== "LATER" && nrl !== "NR" && inv > 0 && (!kw || !pt);
                    });
                    
                    // Update stats
                    window.updateCampaignStats();
                });

                // Show All Button Handler
                $("#show-all-btn").off("click").on("click", function() {
                    // Clear all filters and search
                    $("#global-search").val("");
                    $("#status-filter").val("");
                    $("#inv-filter").val("ALL");
                    $("#nra-filter").val("ALL");
                    $("#nrl-filter").val("ALL");
                    $("#missingAds-filter").val("");
                    
                    // Show all SKUs except parent rows
                    table.setFilter(function(data) {
                        const sku = data.sku || '';
                        const isParent = sku.toUpperCase().includes("PARENT");
                        return !isParent;
                    });
                    
                    // Update stats
                    window.updateCampaignStats();
                });
            });

            document.getElementById("export-btn").addEventListener("click", function () {
                let allData = table.getData("active"); 

                if (allData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let exportData = allData.map(row => {
                    let sku = row.sku || '';
                    let isParent = sku.toUpperCase().includes("PARENT");
                    let missingStatus = '';
                    
                    if (!isParent) {
                        let kwCampaign = row.kw_campaign_name || '';
                        let ptCampaign = row.pt_campaign_name || '';
                        let nra = (row.NRA || '').trim();
                        let inv = row.INV || 0;

                        if (inv > 0) {
                            if (nra === 'NRA') {
                                missingStatus = 'NRA';
                            } else if (nra === 'LATER') {
                                missingStatus = 'LATER';
                            } else {
                                if (kwCampaign && ptCampaign) {
                                    missingStatus = 'Both Running';
                                } else if (kwCampaign) {
                                    missingStatus = 'PT Missing';
                                } else if (ptCampaign) {
                                    missingStatus = 'KW Missing';
                                } else {
                                    missingStatus = 'KW & PT Missing';
                                }
                            }
                        }
                    } else if (row.NRA === 'NRA') {
                        missingStatus = 'NRA';
                    } else if ((row.NRA || '').trim() === 'LATER') {
                        missingStatus = 'LATER';
                    }

                    return {
                        "SKU": row.sku,
                        "INV": row.INV,
                        "Missing Status": missingStatus
                    };
                });

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "amazon_missing_ads.xlsx");
            });


            document.body.style.zoom = "78%";
        });
    </script>
@endsection
