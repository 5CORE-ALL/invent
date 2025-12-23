@extends('layouts.vertical', ['title' => 'Ebay - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        'page_title' => 'Ebay - Utilized',
        'sub_title' => 'Ebay - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Ebay Utilized's
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
                                        <div class="card shadow-sm border-0 utilization-card" data-type="over" style="background: linear-gradient(135deg, #ff01d0 0%, #ff6ec7 100%); cursor: pointer; min-width: 100px; transition: transform 0.2s;">
                                            <div class="card-body text-center text-white p-2">
                                                <h6 class="card-title mb-1" style="font-size: 0.75rem; font-weight: 600;">Over</h6>
                                                <h5 class="mb-0 fw-bold" id="over-utilized-count" style="font-size: 1.2rem;">0</h5>
                                            </div>
                                        </div>
                                        <div class="card shadow-sm border-0 utilization-card" data-type="under" style="background: linear-gradient(135deg, #ff2727 0%, #ff6b6b 100%); cursor: pointer; min-width: 100px; transition: transform 0.2s;">
                                            <div class="card-body text-center text-white p-2">
                                                <h6 class="card-title mb-1" style="font-size: 0.75rem; font-weight: 600;">Under</h6>
                                                <h5 class="mb-0 fw-bold" id="under-utilized-count" style="font-size: 1.2rem;">0</h5>
                                            </div>
                                        </div>
                                        <div class="card shadow-sm border-0 utilization-card" data-type="correctly" style="background: linear-gradient(135deg, #28a745 0%, #5cb85c 100%); cursor: pointer; min-width: 100px; transition: transform 0.2s;">
                                            <div class="card-body text-center text-white p-2">
                                                <h6 class="card-title mb-1" style="font-size: 0.75rem; font-weight: 600;">Correctly</h6>
                                                <h5 class="mb-0 fw-bold" id="correctly-utilized-count" style="font-size: 1.2rem;">0</h5>
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

            // Function to update button counts - shows all counts on all buttons
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
                    // Get all data (not filtered) for accurate counts
                    const allData = table.getData('all');
                    
                    // Count for each type (mutually exclusive like controller)
                    let overCount = 0;
                    let underCount = 0;
                    let correctlyCount = 0;

                    allData.forEach(function(row) {
                        let acos = parseFloat(row.acos || 0);
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);
                        let price = parseFloat(row.price || 0);
                        let inv = parseFloat(row.INV || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                        let rowAcos = parseFloat(acos) || 0;
                        if (isNaN(rowAcos) || rowAcos === 0) {
                            rowAcos = 100;
                        }

                        // Check DIL color
                        let l30 = parseFloat(row.L30 || 0);
                        let dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                        let dilColor = getDilColor(dilDecimal);
                        let isPink = (dilColor === "pink");

                        // Mutually exclusive categorization (same as controller)
                        let categorized = false;

                        // Over-utilized check (priority 1)
                        if (totalACOSValue > 0 && !isPink && !categorized) {
                            let condition1 = (rowAcos > totalACOSValue && ub7 > 33);
                            let condition2 = (rowAcos <= totalACOSValue && ub7 > 90);
                            if (condition1 || condition2) {
                                overCount++;
                                categorized = true;
                            }
                        }

                        // Under-utilized check (priority 2: only if not over-utilized)
                        // Same as controller: ub7 < 70 && ub1 < 70 && price >= 30 && inv > 0 && !isPink
                        if (!categorized && ub7 < 70 && ub1 < 70 && price >= 30 && inv > 0 && !isPink) {
                            underCount++;
                            categorized = true;
                        }

                        // Correctly-utilized check (priority 3: only if not already categorized)
                        // Same as controller: ub7 >= 70 && ub7 <= 90 (only ub7, not ub1)
                        if (!categorized && ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90) {
                            correctlyCount++;
                        }
                    });

                    // Update all button counts - always show all counts
                    const overBtnCount = document.getElementById('over-btn-count');
                    const underBtnCount = document.getElementById('under-btn-count');
                    const correctlyBtnCount = document.getElementById('correctly-btn-count');
                    
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
                        // Update all button counts after filter is applied
                        setTimeout(function() {
                            updateButtonCounts();
                        }, 200);
                    }
                });
            });

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/ebay/utilized/ads/data",
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
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue() || '';
                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        style="width: 90px;">
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
                            
                            // Different color logic based on utilization type
                            if (currentUtilizationType === 'over') {
                                // Over-utilized: Check ACOS first, then UB7
                                var rowAcos = parseFloat(row.acos) || 0;
                                if (isNaN(rowAcos) || rowAcos === 0) {
                                    rowAcos = 100;
                                }
                                if (rowAcos > totalACOSValue) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 >= 70 && ub7 <= 90) {
                                    td.classList.add('green-bg');
                                } else if (ub7 > 90) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 < 70) {
                                    td.classList.add('red-bg');
                                }
                            } else {
                                // Under-utilized and Correctly-utilized: Only check UB7 (no ACOS check)
                                if (ub7 >= 70 && ub7 <= 90) {
                                    td.classList.add('green-bg');
                                } else if (ub7 > 90) {
                                    td.classList.add('pink-bg');
                                } else if (ub7 < 70) {
                                    td.classList.add('red-bg');
                                }
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
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }
                            
                            var sbid = 0;
                            if (currentUtilizationType === 'over') {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            } else if (currentUtilizationType === 'under') {
                                if (ub7 < 10) {
                                    sbid = 0.50;
                                } else if (ub7 >= 10 && ub7 <= 50) {
                                    sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                } else {
                                    sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                }
                            } else {
                                // Correctly-utilized: SBID = L1_CPC * 0.90 (same as correctly-utilized page)
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            }
                            return sbid.toFixed(2);
                        }
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
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
                                
                                var sbid = 0;
                                if (currentUtilizationType === 'over') {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                } else if (currentUtilizationType === 'under') {
                                    if (ub7 < 10) {
                                        sbid = 0.50;
                                    } else if (ub7 >= 10 && ub7 <= 50) {
                                        sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                                    } else {
                                        sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                                    }
                                } else {
                                    // Correctly-utilized: SBID = L1_CPC * 0.90 (same as correctly-utilized page)
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                }
                                updateBid(sbid, rowData.campaign_id);
                            }
                        }
                    },
                    {
                        title: "Status",
                        field: "campaignStatus"
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

                // Apply utilization type filter
                if (currentUtilizationType === 'over') {
                    if (totalACOSValue === 0 || isNaN(totalACOSValue)) {
                        return false;
                    }
                    let condition1 = (rowAcos > totalACOSValue && ub7 > 33);
                    let condition2 = (rowAcos <= totalACOSValue && ub7 > 90);
                    if (!(condition1 || condition2)) {
                        return false;
                    }
                    // Exclude pink DIL
                    let l30 = parseFloat(data.L30);
                    let inv = parseFloat(data.INV);
                    let dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                    let dilColor = getDilColor(dilDecimal);
                    if (dilColor === "pink") return false;
                } else if (currentUtilizationType === 'under') {
                    if (!(ub7 < 70 && ub1 < 70)) return false;
                    // Exclude pink DIL
                    let l30 = parseFloat(data.L30);
                    let inv = parseFloat(data.INV);
                    let dilDecimal = (!isNaN(l30) && !isNaN(inv) && inv !== 0) ? (l30 / inv) : 0;
                    let dilColor = getDilColor(dilDecimal);
                    if (dilColor === "pink") return false;
                } else if (currentUtilizationType === 'correctly') {
                    if (!((ub7 >= 70 && ub7 <= 90) && (ub1 >= 70 && ub1 <= 90))) return false;
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

                // Inventory filter - match individual pages exactly
                let invFilterVal = $("#inv-filter").val();
                if (currentUtilizationType === 'over') {
                    // Over-utilized: Show all campaigns by default (no filter)
                    if (invFilterVal === "OTHERS") {
                        if (parseFloat(data.INV) === 0) return false;
                    }
                    // ALL option shows everything, so no filtering needed
                } else if (currentUtilizationType === 'under') {
                    // Under-utilized: Show all campaigns when no filter or ALL is selected
                    if (invFilterVal && invFilterVal !== 'ALL') {
                        if (invFilterVal === "INV_0") {
                            if (parseFloat(data.INV) !== 0) return false;
                        } else if (invFilterVal === "OTHERS") {
                            if (parseFloat(data.INV) === 0) return false;
                        }
                    }
                } else if (currentUtilizationType === 'correctly') {
                    // Correctly-utilized: Default to INV > 0 (exclude INV = 0 when no filter)
                    if (!invFilterVal) {
                        if (parseFloat(data.INV) === 0) return false;
                    } else if (invFilterVal === "INV_0") {
                        if (parseFloat(data.INV) !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        if (parseFloat(data.INV) === 0) return false;
                    }
                }

                // NR filter
                let nraFilterVal = $("#nra-filter").val();
                if (nraFilterVal) {
                    let rowVal = data.NR || "";
                    if (rowVal !== nraFilterVal) return false;
                }

                return true;
            }

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);

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

                    fetch('/update-ebay-nr-data', {
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
                    let colsToToggle = ["INV", "L30", "DIL %", "NR"];
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
                        
                        var sbid = 0;
                        if (currentUtilizationType === 'over') {
                            sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                        } else if (currentUtilizationType === 'under') {
                            if (ub7 < 10) {
                                sbid = 0.50;
                            } else if (ub7 >= 10 && ub7 <= 50) {
                                sbid = Math.floor(l7_cpc * 1.20 * 100) / 100;
                            } else {
                                sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                            }
                        } else {
                            // Correctly-utilized: SBID = L1_CPC * 0.90 (same as correctly-utilized page)
                            sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                        }

                        campaignIds.push(rowData.campaign_id);
                        bids.push(sbid);
                    }
                });

                fetch('/update-ebay-keywords-bid-price', {
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

                fetch('/update-ebay-keywords-bid-price', {
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
            fetch('/ebay/get-utilization-counts')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        document.getElementById('over-utilized-count').textContent = data.over_utilized || 0;
                        document.getElementById('under-utilized-count').textContent = data.under_utilized || 0;
                        document.getElementById('correctly-utilized-count').textContent = data.correctly_utilized || 0;
                    }
                })
                .catch(err => console.error('Error loading counts:', err));
        }

        function showUtilizationChart(type) {
            const chartTitle = document.getElementById('chart-title');
            const modal = new bootstrap.Modal(document.getElementById('utilizationChartModal'));
            
            const titles = {
                'over': 'Over Utilized Trend (Last 30 Days)',
                'under': 'Under Utilized Trend (Last 30 Days)',
                'correctly': 'Correctly Utilized Trend (Last 30 Days)'
            };
            chartTitle.textContent = titles[type] || 'Utilization Trend';

            modal.show();

            fetch('/ebay/get-utilization-chart-data?type=' + type)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.data && data.data.length > 0) {
                        const chartData = data.data;
                        const dates = chartData.map(d => d.date);
                        
                        let dataset = [];
                        let label = '';
                        let color = '';
                        let bgColor = '';

                        if (type === 'over') {
                            dataset = chartData.map(d => d.over_utilized);
                            label = 'Over Utilized';
                            color = '#ff01d0';
                            bgColor = 'rgba(255, 1, 208, 0.1)';
                        } else if (type === 'under') {
                            dataset = chartData.map(d => d.under_utilized);
                            label = 'Under Utilized';
                            color = '#ff2727';
                            bgColor = 'rgba(255, 39, 39, 0.1)';
                        } else if (type === 'correctly') {
                            dataset = chartData.map(d => d.correctly_utilized);
                            label = 'Correctly Utilized';
                            color = '#28a745';
                            bgColor = 'rgba(40, 167, 69, 0.1)';
                        }

                        const ctx = document.getElementById('utilizationChart').getContext('2d');
                        
                        if (utilizationChartInstance) {
                            utilizationChartInstance.destroy();
                        }

                        utilizationChartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [{
                                    label: label,
                                    data: dataset,
                                    borderColor: color,
                                    backgroundColor: bgColor,
                                    tension: 0.4,
                                    fill: true,
                                    borderWidth: 2
                                }]
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
