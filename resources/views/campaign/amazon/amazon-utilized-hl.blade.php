@extends('layouts.vertical', ['title' => 'Amazon HL - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        #budget-under-table .tabulator {
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

        .utilization-type-btn {
            padding: 8px 16px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .utilization-type-btn.active {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white;
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .utilization-type-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .utilization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        body {
            zoom: 90%;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon HL - Utilized',
        'sub_title' => 'Amazon HL - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Amazon HL Utilized's
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Utilization Type Selector -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="fw-bold me-2">Type:</span>
                                    <button class="utilization-type-btn active" data-type="over">Over Utilized <span class="btn-count fs-4 fw-bold" id="over-btn-count"></span></button>
                                    <button class="utilization-type-btn" data-type="under">Under Utilized <span class="btn-count fs-4 fw-bold" id="under-btn-count"></span></button>
                                    <button class="utilization-type-btn" data-type="correctly">Correctly Utilized <span class="btn-count fs-4 fw-bold" id="correctly-btn-count"></span></button>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end align-items-center flex-wrap">
                                    <!-- Count Cards -->
                                    <div class="d-flex gap-2">
                                        <div class="card shadow-sm border-0 utilization-card" data-type="7ub" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); cursor: pointer; min-width: 120px; transition: transform 0.2s;">
                                            <div class="card-body text-center text-white p-2">
                                                <h6 class="card-title mb-1" style="font-size: 0.75rem; font-weight: 600;">7UB</h6>
                                                <h5 class="mb-0 fw-bold" id="seven-ub-count" style="font-size: 1.2rem;">0</h5>
                                            </div>
                                        </div>
                                        <div class="card shadow-sm border-0 utilization-card" data-type="7ub-1ub" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); cursor: pointer; min-width: 120px; transition: transform 0.2s;">
                                            <div class="card-body text-center text-white p-2">
                                                <h6 class="card-title mb-1" style="font-size: 0.75rem; font-weight: 600;">7UB + 1UB</h6>
                                                <h5 class="mb-0 fw-bold" id="seven-ub-one-ub-count" style="font-size: 1.2rem;">0</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none shadow-sm">
                                        <i class="fa-solid fa-check-double me-1"></i>
                                        APR ALL SBID
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fa-solid fa-search text-muted"></i>
                                    </span>
                                    <input type="text" id="global-search" class="form-control form-control-md" 
                                           placeholder="Search campaign...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select id="status-filter" class="form-select form-select-md">
                                    <option value="">All Status</option>
                                    <option value="RUNNING">Running</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="ENDED">Ended</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="inv-filter" class="form-select form-select-md">
                                    <option value="">All Inventory</option>
                                    <option value="ALL">ALL</option>
                                    <option value="INV_0">0 INV</option>
                                    <option value="OTHERS">OTHERS</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select id="nra-filter" class="form-select form-select-md">
                                    <option value="">All NRA</option>
                                    <option value="NRA">NRA</option>
                                    <option value="RA">RA</option>
                                    <option value="LATER">LATER</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Modal -->
    <div class="modal fade" id="utilizationChartModal" tabindex="-1" aria-labelledby="utilizationChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="utilizationChartModalLabel">
                        <i class="fa-solid fa-chart-line me-2"></i>
                        <span id="chart-title">Utilization Trend</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <canvas id="utilizationChart" height="80"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="progress-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: white; font-size: 1.2rem; font-weight: 500;">
                Updating campaigns...
            </div>
            <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentUtilizationType = 'over'; // Default to over
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts - shows all counts by default
            // Counts are based on filtered data (respects INV, NRA, status, search filters)
            // but shows all utilization types (not filtered by utilization type button)
            let countUpdateTimeout = null;
            function updateButtonCounts() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                // Debounce for performance
                if (countUpdateTimeout) {
                    clearTimeout(countUpdateTimeout);
                }
                countUpdateTimeout = setTimeout(function() {
                    // Get all data and apply filters (except utilization type filter)
                    const allData = table.getData('all');
                    
                    // Count for each type (mutually exclusive like controller)
                    let overCount = 0;
                    let underCount = 0;
                    let correctlyCount = 0;

                    allData.forEach(function(row) {
                        // Apply all filters except utilization type filter
                        // Global search filter
                        let searchVal = $("#global-search").val()?.toLowerCase() || "";
                        if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal))) {
                            return;
                        }
                        
                        // Status filter
                        let statusVal = $("#status-filter").val();
                        if (statusVal && row.campaignStatus !== statusVal) {
                            return;
                        }
                        
                        // Inventory filter (HL: no default filter, show all by default)
                        let invFilterVal = $("#inv-filter").val();
                        let inv = parseFloat(row.INV || 0);
                        
                        if (!invFilterVal || invFilterVal === '') {
                            // Default: show all (no filtering)
                        } else if (invFilterVal === "ALL") {
                            // ALL option shows everything
                        } else if (invFilterVal === "INV_0") {
                            // Show only INV = 0
                            if (inv !== 0) return;
                        } else if (invFilterVal === "OTHERS") {
                            // Show only INV > 0
                            if (inv <= 0) return;
                        }
                        
                        // NRA filter
                        let nraFilterVal = $("#nra-filter").val();
                        if (nraFilterVal) {
                            let rowVal = row.NRA || "";
                            if (rowVal !== nraFilterVal) return;
                        }
                        
                        // Now calculate utilization and count
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                        // Mutually exclusive categorization (same as controller)
                        let categorized = false;

                        // Over-utilized check (priority 1): ub7 > 90 && ub1 > 90
                        if (!categorized && ub7 > 90 && ub1 > 90) {
                            overCount++;
                            categorized = true;
                        }

                        // Under-utilized check (priority 2: only if not over-utilized): ub7 < 70
                        if (!categorized && ub7 < 70 && ub1 < 70) {
                            underCount++;
                            categorized = true;
                        }

                        // Correctly-utilized check (priority 3: only if not already categorized): ub7 >= 70 && ub7 <= 90
                        if (!categorized && ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90) {
                            correctlyCount++;
                        }
                    });

                    // Update all button counts by default
                    const overBtnCount = document.getElementById('over-btn-count');
                    const underBtnCount = document.getElementById('under-btn-count');
                    const correctlyBtnCount = document.getElementById('correctly-btn-count');
                    
                    // Show all counts by default
                    if (overBtnCount) overBtnCount.textContent = `( ${overCount} )`;
                    if (underBtnCount) underBtnCount.textContent = `( ${underCount} )`;
                    if (correctlyBtnCount) correctlyBtnCount.textContent = `( ${correctlyCount} )`;
                }, 150);
            }

            // Utilization type button handlers
            document.querySelectorAll('.utilization-type-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.utilization-type-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentUtilizationType = this.getAttribute('data-type');
                    
                    if (typeof table !== 'undefined' && table) {
                        table.setFilter(combinedFilter);
                        // Redraw cells to update formatter colors based on new type
                        table.redraw(true);
                        // Update column visibility for SBID and APR BID
                        table.hideColumn('sbid');
                        table.hideColumn('apr_bid');
                        if (currentUtilizationType !== 'correctly') {
                            table.showColumn('sbid');
                            table.showColumn('apr_bid');
                        }
                        // Update all button counts after filter is applied
                        setTimeout(function() {
                            updateButtonCounts();
                        }, 200);
                    }
                });
            });

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/amazon/utilized/hl/ads/data",
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
                        visible: false
                    },
                    {
                        title: "FBA INV",
                        field: "FBA_INV",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return `<div class="text-center">${value || 0}</div>`;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false
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
                        visible: false
                    },
                    {
                        title: "AL 30",
                        field: "A_L30",
                        visible: false
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
                        visible: false
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "NRL") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (value === "RL") {
                                bgColor = "background-color:#28a745;color:#fff;"; // green
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRL"
                                        style="width: 90px; ${bgColor}">
                                    <option value="RL" ${value === 'RL' ? 'selected' : ''}>RL</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>NRL</option>
                                </select>
                            `;
                        },
                        visible: false,
                        hozAlign: "center"
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue()?.trim();

                            let bgColor = "";
                            if (value === "NRA") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (value === "RA") {
                                bgColor = "background-color:#28a745;color:#fff;"; // green
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
                        hozAlign: "center",
                        visible: false
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
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0)
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var acosRaw = row.acos; 
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (acos === 0) {
                                td.classList.add('red-bg');
                                return "100%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                            }
                            return acos.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            // Color logic based on UB7 only (Amazon rules)
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 70) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "1 UB%",
                        field: "l1_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 70) {
                                td.classList.add('red-bg');
                            }
                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        visible: function() {
                            return currentUtilizationType !== 'correctly';
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }
                            
                            var sbid = '';
                            if (currentUtilizationType === 'over') {
                                // Over-utilized: l7_cpc * 0.90 (if l7_cpc === 0, then 0.75)
                                if (l7_cpc === 0) {
                                    sbid = 0.75;
                                } else {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                }
                            } else if (currentUtilizationType === 'under') {
                                // Under-utilized: Complex logic based on ub7 and l7_cpc
                                if (ub7 < 70) {
                                    if (ub7 < 10 || l7_cpc === 0) {
                                        sbid = 0.75;
                                    } else if (l7_cpc > 0 && l7_cpc < 0.30) {
                                        sbid = (l7_cpc + 0.20).toFixed(2);
                                    } else {
                                        sbid = (Math.floor((l7_cpc * 1.10) * 100) / 100).toFixed(2);
                                    }
                                } else {
                                    sbid = '';
                                }
                            } else {
                                // Correctly-utilized: Usually no SBID (empty)
                                sbid = '';
                            }
                            return sbid === '' ? '' : (typeof sbid === 'string' ? sbid : sbid.toFixed(2));
                        }
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        visible: function() {
                            return currentUtilizationType !== 'correctly';
                        },
                        formatter: function(cell) {
                            return `
                                <div style="align-items:center; gap:5px;">
                                    <button class="btn btn-primary update-row-btn">APR BID</button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-row-btn")) {
                                var rowData = cell.getRow().getData();
                                var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = 0;
                                if (budget > 0) {
                                    ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                                }
                                
                                var sbid = '';
                                if (currentUtilizationType === 'over') {
                                    if (l7_cpc === 0) {
                                        sbid = 0.75;
                                    } else {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    }
                                } else if (currentUtilizationType === 'under') {
                                    if (ub7 < 70) {
                                        if (ub7 < 10 || l7_cpc === 0) {
                                            sbid = 0.75;
                                        } else if (l7_cpc > 0 && l7_cpc < 0.30) {
                                            sbid = parseFloat((l7_cpc + 0.20).toFixed(2));
                                        } else {
                                            sbid = Math.floor((l7_cpc * 1.10) * 100) / 100;
                                        }
                                    } else {
                                        sbid = '';
                                    }
                                } else {
                                    sbid = '';
                                }
                                if (sbid !== '') {
                                    updateBid(sbid, rowData.campaign_id);
                                }
                            }
                        }
                    },
                    {
                        title: "Status",
                        field: "campaignStatus"
                    },
                    {
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
                        formatter: function(cell){
                            let value = parseFloat(cell.getValue()) || 0;
                            let percent = value.toFixed(0);
                            let color = "";

                            if (value < 10) {
                                color = "red";
                            } else if (value >= 10 && value < 15) {
                                color = "#ffc107";
                            } else if (value >= 15 && value < 20) {
                                color = "blue";
                            } else if (value >= 20 && value <= 40) {
                                color = "green";
                            } else if (value > 40) {
                                color = "#e83e8c";
                            }

                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${percent}%
                                </span>
                            `;
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    totalACOSValue = parseFloat(response.total_acos) || 0;
                    totalL30Spend = parseFloat(response.total_l30_spend) || 0;
                    totalL30Sales = parseFloat(response.total_l30_sales) || 0;
                    return response.data;
                }
            });

            // Combined filter function
            function combinedFilter(data) {
                let acos = parseFloat(data.acos || 0);
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend) || 0;
                let l1_spend = parseFloat(data.l1_spend) || 0;

                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                let rowAcos = parseFloat(acos) || 0;
                if (isNaN(rowAcos) || rowAcos === 0) {
                    rowAcos = 100;
                }

                // Apply utilization type filter (Amazon rules)
                if (currentUtilizationType === 'over') {
                    // Over-utilized: ub7 > 90 && ub1 > 90
                    if (!(ub7 > 90 && ub1 > 90)) return false;
                } else if (currentUtilizationType === 'under') {
                    // Under-utilized: ub7 < 70
                    if (!(ub7 < 70 && ub1 < 70)) return false;
                } else if (currentUtilizationType === 'correctly') {
                    // Correctly-utilized: ub7 >= 70 && ub7 <= 90
                    if (!(ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90)) return false;
                }

                // Global search filter
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                // Status filter
                let statusVal = $("#status-filter").val();
                if (statusVal && data.campaignStatus !== statusVal) {
                    return false;
                }

                // Inventory filter (HL: no default filter, show all by default)
                let invFilterVal = $("#inv-filter").val();
                let inv = parseFloat(data.INV || 0);
                
                // By default (no filter selected), show all campaigns
                if (!invFilterVal || invFilterVal === '') {
                    // No filtering - show all
                } else if (invFilterVal === "ALL") {
                    // ALL option shows everything
                } else if (invFilterVal === "INV_0") {
                    // Show only INV = 0
                    if (inv !== 0) return false;
                } else if (invFilterVal === "OTHERS") {
                    // Show only INV > 0 (exclude INV = 0 and negative)
                    if (inv <= 0) return false;
                }

                // NRA filter
                let nraFilterVal = $("#nra-filter").val();
                if (nraFilterVal) {
                    let rowVal = data.NRA || "";
                    if (rowVal !== nraFilterVal) return false;
                }

                return true;
            }

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);
                
                // Set initial column visibility based on current utilization type
                if (currentUtilizationType === 'correctly') {
                    table.hideColumn('sbid');
                    table.hideColumn('apr_bid');
                } else {
                    table.showColumn('sbid');
                    table.showColumn('apr_bid');
                }

                // Update counts when data is filtered (debounced)
                let filterTimeout = null;
                table.on("dataFiltered", function(filteredRows) {
                    if (filterTimeout) clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        updateButtonCounts();
                    }, 200);
                });

                // Debounced search
                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                $("#status-filter, #inv-filter, #nra-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                // Initial update of all button counts after data loads
                setTimeout(function() {
                    updateButtonCounts();
                }, 1000);
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-amazon-nr-nrl-fba', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            sku: sku,
                            field: field,
                            value: value
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log(data);
                    })
                    .catch(err => console.error(err));
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let colsToToggle = ["INV", "L30", "DIL %", "NR", "A_L30", "ADIL %", "NRL", "NRA", "FBA"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();
                var campaignIds = [];
                var bids = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if (rowEl && rowEl.offsetParent !== null) {
                        var rowData = row.getData();
                        var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                        var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                        var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                        var ub7 = 0;
                        if (budget > 0) {
                            ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                        }
                        
                        var sbid = '';
                        if (currentUtilizationType === 'over') {
                            if (l7_cpc === 0) {
                                sbid = 0.75;
                            } else {
                                sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                            }
                        } else if (currentUtilizationType === 'under') {
                            if (ub7 < 70) {
                                if (ub7 < 10 || l7_cpc === 0) {
                                    sbid = 0.75;
                                } else if (l7_cpc > 0 && l7_cpc < 0.30) {
                                    sbid = parseFloat((l7_cpc + 0.20).toFixed(2));
                                } else {
                                    sbid = Math.floor((l7_cpc * 1.10) * 100) / 100;
                                }
                            } else {
                                sbid = '';
                            }
                        } else {
                            sbid = '';
                        }

                        if (sbid !== '') {
                            campaignIds.push(rowData.campaign_id);
                            bids.push(sbid);
                        }
                    }
                });

                fetch('/update-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        bids: bids
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        let errorMsg = data.message || "Something went wrong";
                        if (errorMsg.includes("Premium Ads")) {
                            alert("Error: " + errorMsg);
                        } else {
                            alert("Something went wrong: " + errorMsg);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error updating bids");
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            });

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: [campaignId],
                        bids: [aprBid]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        let errorMsg = data.message || "Something went wrong";
                        if (errorMsg.includes("Premium Ads")) {
                            alert("Error: " + errorMsg);
                        } else {
                            alert("Something went wrong: " + errorMsg);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error updating bid");
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            }

            // Load counts
            loadUtilizationCounts();

            // Add click handlers to utilization cards
            document.querySelectorAll('.utilization-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    showUtilizationChart(type);
                });
            });
        });

        let utilizationChartInstance = null;

        function loadUtilizationCounts() {
            fetch('/amazon/get-utilization-counts?type=HL')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        // 7UB count: sum of all utilization types based on 7UB only
                        const count7ub = (data.over_utilized_7ub || 0) + (data.under_utilized_7ub || 0) + (data.correctly_utilized_7ub || 0);
                        // 7UB + 1UB count: sum of all utilization types based on both 7UB and 1UB
                        const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data.under_utilized_7ub_1ub || 0) + (data.correctly_utilized_7ub_1ub || 0);
                        
                        document.getElementById('seven-ub-count').textContent = count7ub || 0;
                        document.getElementById('seven-ub-one-ub-count').textContent = count7ub1ub || 0;
                    }
                })
                .catch(err => console.error('Error loading counts:', err));
        }

        function showUtilizationChart(type) {
            const chartTitle = document.getElementById('chart-title');
            const modal = new bootstrap.Modal(document.getElementById('utilizationChartModal'));
            
            const titles = {
                '7ub': '7UB Utilization Trend (Last 30 Days)',
                '7ub-1ub': '7UB + 1UB Utilization Trend (Last 30 Days)'
            };
            chartTitle.textContent = titles[type] || 'Utilization Trend';

            modal.show();

            fetch('/amazon/get-utilization-chart-data?type=HL&condition=' + type)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.data && data.data.length > 0) {
                        const chartData = data.data;
                        const dates = chartData.map(d => d.date);
                        
                        const ctx = document.getElementById('utilizationChart').getContext('2d');
                        
                        if (utilizationChartInstance) {
                            utilizationChartInstance.destroy();
                        }

                        // Show all 3 lines (over, under, correctly) based on condition type
                        const datasets = [];
                        
                        if (type === '7ub') {
                            datasets.push({
                                label: 'Over Utilized',
                                data: chartData.map(d => d.over_utilized_7ub || 0),
                                borderColor: '#ff01d0',
                                backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Under Utilized',
                                data: chartData.map(d => d.under_utilized_7ub || 0),
                                borderColor: '#ff2727',
                                backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Correctly Utilized',
                                data: chartData.map(d => d.correctly_utilized_7ub || 0),
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                        } else if (type === '7ub-1ub') {
                            datasets.push({
                                label: 'Over Utilized',
                                data: chartData.map(d => d.over_utilized_7ub_1ub || 0),
                                borderColor: '#ff01d0',
                                backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Under Utilized',
                                data: chartData.map(d => d.under_utilized_7ub_1ub || 0),
                                borderColor: '#ff2727',
                                backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Correctly Utilized',
                                data: chartData.map(d => d.correctly_utilized_7ub_1ub || 0),
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                        }

                        utilizationChartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        enabled: true,
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            title: function(context) {
                                                return 'Date: ' + context[0].label;
                                            },
                                            label: function(context) {
                                                return context.dataset.label + ': ' + context.parsed.y;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(err => console.error('Error loading chart:', err));
        }
    </script>
@endsection

