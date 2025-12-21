@extends('layouts.vertical', ['title' => 'Walmart BGT Util.', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        'page_title' => 'Walmart BGT Util.',
        'sub_title' => 'Walmart BGT Util.',
    ])
    
    <!-- Stats Section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row text-center">
                        <!-- Total Spend -->
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Total Spend</div>
                                <div class="h3 mb-0 fw-bold text-success" id="total-spend">$0.00</div>
                            </div>
                        </div>

                        <!-- Total Sales -->
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Total Sales</div>
                                <div class="h3 mb-0 fw-bold text-info" id="total-sales">$0.00</div>
                            </div>
                        </div>

                        <!-- Avg ACOS -->
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Avg ACOS</div>
                                <div class="h3 mb-0 fw-bold text-danger" id="avg-acos">0.00%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Walmart BGT Util.
                        </h4>

                        <!-- Utilization Filter Buttons Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="d-flex gap-2 align-items-center">
                                    <button id="over-utilized-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #ff01d0 0%, #ff6ec7 100%); color: white; border: none; min-width: 150px;">
                                        <div>OVER UTILIZED</div>
                                        <div id="over-utilized-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="under-utilized-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #ff2727 0%, #ff6b6b 100%); color: white; border: none; min-width: 150px;">
                                        <div>UNDER UTILIZED</div>
                                        <div id="under-utilized-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="correctly-utilized-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #28a745 0%, #5cb85c 100%); color: white; border: none; min-width: 150px;">
                                        <div>CORRECTLY UTILIZED</div>
                                        <div id="correctly-utilized-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="nrl-filter" class="form-select form-select-md">
                                        <option value="">Select NRL</option>
                                        <option value="NRL">NRL</option>
                                        <option value="RL">RL</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end align-items-center">
                                    <button id="7ub-chart-btn" class="btn btn-primary btn-md">
                                        <i class="fa fa-chart-line me-1"></i>
                                        7UB
                                    </button>
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none">
                                        APR ALL SBID
                                    </button>
                                    <button class="btn btn-success btn-md">
                                        Total bids: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
                                    </button>
                                    <button class="btn btn-primary btn-md">
                                        <i class="fa fa-percent me-1"></i>
                                        of Total: <span id="percentage-campaigns" class="fw-bold ms-1 fs-4">0%</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md" placeholder="Search campaign...">
                                    </div>
                                    <select id="status-filter" class="form-select form-select-md" style="width: 140px;">
                                        <option value="">All Status</option>
                                        <option value="LIVE">Live</option>
                                        <option value="PAUSED">Paused</option>
                                    </select>
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

    <!-- Chart Modal -->
    <div class="modal fade" id="7ubChartModal" tabindex="-1" aria-labelledby="7ubChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="7ubChartModalLabel">
                        <i class="fa-solid fa-chart-line me-2"></i>
                        7UB Daily Counts Trend
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <canvas id="7ubChart" height="80"></canvas>
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
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

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

            // Variable to store avg_acos for use in column formatters
            var avgAcosValue = 0;
            
            // Variable to store current utilization filter (global)
            window.currentUtilizationFilter = null;

            // Helper function to calculate ALD BGT from ACOS
            function calculateAldBgt(acos) {
                if (avgAcosValue > 0) {
                    const halfAvgAcos = avgAcosValue / 2;
                    
                    // If ACOS > AVG ACOS then ALD BGT = 1
                    if (acos > avgAcosValue) {
                        return 1;
                    } 
                    // If AVG ACOS > ACOS > HALF OF AVG ACOS then ALD BGT = 3
                    else if (acos > halfAvgAcos && acos <= avgAcosValue) {
                        return 3;
                    } 
                    // If ACOS <= HALF OF AVG ACOS then ALD BGT = 5
                    else if (acos <= halfAvgAcos) {
                        return 5;
                    }
                }
                return 0;
            }

            window.table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/walmart/utilized/kw/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["Sku"] || '';

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
                        title: "WL 30",
                        field: "WA_L30",
                        visible: false
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "NRA") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (value === "RA") {
                                bgColor = "background-color:#28a745;color:#fff;"; // green
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        style="width: 90px; ${bgColor}">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                </select>
                            `;
                        },
                        visible: false,
                        hozAlign: "center"
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
                        title: "ACOS L30",
                        field: "acos_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(2) + "%"}</span>
                            `;
                        },
                        visible: true,
                    },
                    {
                        title: "ALD BGT",
                        field: "acos_l30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const acos = parseFloat(cell.getValue() || 0);
                            const aldBgt = calculateAldBgt(acos);
                            return `<span class="fw-bold">${aldBgt}</span>`;
                        },
                        visible: true,
                    },
                    {
                        title: "Clicks L30",
                        field: "clicks_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "spend_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_l7 = parseFloat(row.spend_l7) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (spend_l7 / (budget * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
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
                        field: "spend_l1",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_l1 = parseFloat(row.spend_l1) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (spend_l1 / budget) * 100 : 0;

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
                        title: "7 UB",
                        field: "spend_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_l7 = parseFloat(row.spend_l7) || 0;
                            var acos = parseFloat(row.acos_l30) || 0;
                            var aldBgt = calculateAldBgt(acos);
                            
                            // 7 UB = (L7 ad spend/(ald bgt*7))*100
                            var ub7 = (aldBgt > 0 && aldBgt * 7 > 0) ? (spend_l7 / (aldBgt * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            td.style.backgroundColor = '';
                            
                            var value = ub7.toFixed(2) + "%";
                            
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                                return value;
                            } else if (ub7 > 90) {
                                // Pink badge - background on text
                                return '<span style="background-color: #ff01d0; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">' + value + '</span>';
                            } else if (ub7 < 70) {
                                td.classList.add('red-bg');
                                return value;
                            }
                            
                            return value;
                        },
                        visible: true,
                    },
                    {
                        title: "1 UB",
                        field: "spend_l1",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_l1 = parseFloat(row.spend_l1) || 0;
                            var acos = parseFloat(row.acos_l30) || 0;
                            var aldBgt = calculateAldBgt(acos);
                            
                            // 1 UB = (L1 ad spend/(ald bgt))*100
                            var ub1 = aldBgt > 0 ? (spend_l1 / aldBgt) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            td.style.backgroundColor = '';
                            
                            var value = ub1.toFixed(2) + "%";
                            
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                                return value;
                            } else if (ub1 > 90) {
                                // Pink badge - background on text
                                return '<span style="background-color: #ff01d0; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">' + value + '</span>';
                            } else if (ub1 < 70) {
                                td.classList.add('red-bg');
                                return value;
                            }
                            
                            return value;
                        },
                        visible: true,
                    },
                    {
                        title: "L7 CPC",
                        field: "cpc_l7",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l7 = parseFloat(row.cpc_l7) || 0;
                            return cpc_l7.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "cpc_l1",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l1 = parseFloat(row.cpc_l1) || 0;
                            return cpc_l1.toFixed(2);
                        }
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_l7 = parseFloat(row.cpc_l7) || 0;
                            var spend_l7 = parseFloat(row.spend_l7) || 0;
                            var acos = parseFloat(row.acos_l30) || 0;
                            var aldBgt = calculateAldBgt(acos);
                            
                            // Calculate 7UB = (L7 ad spend/(ald bgt*7))*100
                            var ub7 = (aldBgt > 0 && aldBgt * 7 > 0) ? (spend_l7 / (aldBgt * 7)) * 100 : 0;
                            
                            var sbid;
                            
                            // If 7UB is pink (> 90%): SBID = L7cpc * 0.90 (minimum 0.31)
                            if (ub7 > 90) {
                                sbid = cpc_l7 * 0.90;
                                sbid = Math.max(sbid, 0.31); // Minimum value is 0.31
                            }
                            // If 7UB is between 30-70%: SBID = L7cpc + 0.5
                            else if (ub7 >= 30 && ub7 <= 70) {
                                sbid = cpc_l7 + 0.5;
                            }
                            // If 7UB is below 30%: SBID = L7cpc + 0.10
                            else if (ub7 < 30) {
                                sbid = cpc_l7 + 0.10;
                            }
                            // For 70-90% range (green), use same logic as 30-70%
                            else {
                                sbid = cpc_l7 + 0.5;
                            }
                            
                            return sbid.toFixed(2);
                        },
                        visible: true
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        formatter: function(cell, formatterParams, onRendered) {
                            var value = cell.getValue() || 0;
                            return `
                                <div style="align-items:center; gap:5px;">
                                    <button class="btn btn-primary update-row-btn">APR BID</button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-row-btn")) {
                                var row = cell.getRow().getData();
                                var cpc_l1 = parseFloat(row.cpc_l1) || 0;
                                var cpc_l7 = parseFloat(row.cpc_l7) || 0;
                                var sbid;
                                if(cpc_l1 > cpc_l7) {
                                    sbid = (cpc_l1 * 0.9).toFixed(2);
                                }else{
                                    sbid = (cpc_l7 * 0.9).toFixed(2);
                                }
                                updateBid(sbid, rowData.campaign_id);
                            }
                        },
                        visible: false
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = (cell.getValue() || '').toString().toUpperCase();
                            const isLive = value === 'LIVE' || value === 'ENABLED' || value === 'ACTIVE' || value === 'RUNNING';
                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="status"
                                        style="width: 110px;">
                                    <option value="PAUSED" ${!isLive ? 'selected' : ''}>PAUSED</option>
                                    <option value="LIVE" ${isLive ? 'selected' : ''}>LIVE</option>
                                </select>
                            `;
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    // Update totals from API response
                    if (response.total_spend !== undefined) {
                        document.getElementById("total-spend").innerText = "$" + parseFloat(response.total_spend).toFixed(2);
                    }
                    if (response.total_sales !== undefined) {
                        document.getElementById("total-sales").innerText = "$" + parseFloat(response.total_sales).toFixed(2);
                    }
                    if (response.avg_acos !== undefined) {
                        avgAcosValue = parseFloat(response.avg_acos);
                        document.getElementById("avg-acos").innerText = avgAcosValue.toFixed(2) + "%";
                    }
                    
                    // Update utilization counts after data is loaded
                    setTimeout(function() {
                        if (window.updateUtilizationCounts) {
                            window.updateUtilizationCounts();
                        }
                    }, 100);
                    
                    return response.data;
                }
            });

            window.table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            window.table.on("cellEdited", function(cell){
                if(cell.getField() === "crnt_bid"){
                    var row = cell.getRow();
                    var rowData = row.getData();
                    var newCrntBid = parseFloat(rowData.crnt_bid) || 0;

                    row.update({
                        sbid: (newCrntBid * 0.9).toFixed(2)
                    });

                    $.ajax({
                        url: '/update-amazon-sp-bid-price', 
                        method: 'POST',
                        data: {
                            id: rowData.campaign_id,
                            crnt_bid: newCrntBid,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response){
                            console.log(response);
                        },
                        error: function(xhr){
                            alert('Error updating CRNT BID');
                        }
                    });
                }
            });

            $(document).on("change", ".editable-select", function () {
                let select = this;
                let sku = select.getAttribute("data-sku");
                let field = select.getAttribute("data-field");
                let value = select.value;

                console.log(`SKU: ${sku}, Field: ${field}, Value: ${value}`);

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

            window.table.on("tableBuilt", function () {

                function combinedFilter(data) {
                    // Utilization filter (7UB) - always read current value
                    const currentFilter = window.currentUtilizationFilter;
                    if (currentFilter) {
                        const spend_l7 = parseFloat(data.spend_l7) || 0;
                        const acos = parseFloat(data.acos_l30) || 0;
                        
                        // Calculate ALD BGT
                        let aldBgt = 0;
                        if (avgAcosValue > 0) {
                            const halfAvgAcos = avgAcosValue / 2;
                            if (acos > avgAcosValue) {
                                aldBgt = 1;
                            } else if (acos > halfAvgAcos && acos <= avgAcosValue) {
                                aldBgt = 3;
                            } else if (acos <= halfAvgAcos) {
                                aldBgt = 5;
                            }
                        }
                        
                        // Calculate 7UB = (L7 ad spend/(ald bgt*7))*100
                        const ub7 = (aldBgt > 0 && aldBgt * 7 > 0) ? (spend_l7 / (aldBgt * 7)) * 100 : 0;
                        
                        // Match the type - use currentFilter variable to ensure we get latest value
                        if (currentFilter === 'pink' && ub7 <= 90) return false;
                        if (currentFilter === 'red' && ub7 >= 70) return false;
                        if (currentFilter === 'green' && (ub7 < 70 || ub7 > 90)) return false;
                    }

                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal) {
                        let campaignMatch = data.campaignName?.toLowerCase().includes(searchVal);
                        let skuMatch = data.sku?.toLowerCase().includes(searchVal);

                        if (!(campaignMatch || skuMatch)) {
                            return false;
                        }
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    let invFilterVal = $("#inv-filter").val();
                    let invVal = parseFloat(data.INV || 0);

                    if (invFilterVal === "INV_0") {
                        if (invVal !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        if (invVal === 0) return false;
                    }

                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowSelect = getRowSelectBySkuAndField(data.sku, "NRL");
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NRL || "";

                        if (rowVal !== nrlFilterVal) return false;
                    }

                    return true;
                }

                function updateCampaignStats() {
                    if (!window.table) return;
                    let total = window.table.getDataCount();                 
                    let filtered = window.table.getDataCount("active");      
                    let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                    document.getElementById("total-campaigns").innerText = filtered;
                    document.getElementById("percentage-campaigns").innerText = percentage + "%";
                    
                    // Update utilization counts
                    updateUtilizationCounts();
                }
                
                function updateUtilizationCounts() {
                    if (!window.table) return;
                    
                    const allData = window.table.getData();
                    let pinkCount = 0;
                    let redCount = 0;
                    let greenCount = 0;
                    
                    allData.forEach(row => {
                        const spend_l7 = parseFloat(row.spend_l7) || 0;
                        const acos = parseFloat(row.acos_l30) || 0;
                        
                        // Calculate ALD BGT
                        let aldBgt = 0;
                        if (avgAcosValue > 0) {
                            const halfAvgAcos = avgAcosValue / 2;
                            if (acos > avgAcosValue) {
                                aldBgt = 1;
                            } else if (acos > halfAvgAcos && acos <= avgAcosValue) {
                                aldBgt = 3;
                            } else if (acos <= halfAvgAcos) {
                                aldBgt = 5;
                            }
                        }
                        
                        // Calculate 7UB = (L7 ad spend/(ald bgt*7))*100
                        const ub7 = (aldBgt > 0 && aldBgt * 7 > 0) ? (spend_l7 / (aldBgt * 7)) * 100 : 0;
                        
                        // Categorize
                        if (ub7 > 90) {
                            pinkCount++;
                        } else if (ub7 < 70) {
                            redCount++;
                        } else if (ub7 >= 70 && ub7 <= 90) {
                            greenCount++;
                        }
                    });
                    
                    // Update button counts
                    const overCountEl = document.getElementById("over-utilized-count");
                    const underCountEl = document.getElementById("under-utilized-count");
                    const correctlyCountEl = document.getElementById("correctly-utilized-count");
                    
                    if (overCountEl) overCountEl.innerText = pinkCount;
                    if (underCountEl) underCountEl.innerText = redCount;
                    if (correctlyCountEl) correctlyCountEl.innerText = greenCount;
                }

                function refreshFilters() {
                    if (window.table) {
                        // Create a new function reference to force Tabulator to re-evaluate
                        // This ensures it reads the current window.currentUtilizationFilter value
                        const filterWrapper = function(data) {
                            return combinedFilter(data);
                        };
                        
                        // Set filter with new function reference
                        window.table.setFilter(filterWrapper);
                        updateCampaignStats(); 
                    }
                }

                // Make combinedFilter accessible globally
                window.combinedFilter = combinedFilter;
                window.updateCampaignStats = updateCampaignStats;
                window.updateUtilizationCounts = updateUtilizationCounts;
                window.refreshFilters = refreshFilters;

                window.table.setFilter(combinedFilter);

                window.table.on("dataFiltered", updateCampaignStats);
                window.table.on("pageLoaded", updateCampaignStats);
                window.table.on("dataProcessed", updateCampaignStats);
                window.table.on("dataLoaded", updateCampaignStats);

                $("#global-search").on("keyup", refreshFilters);
                $("#status-filter, #nrl-filter, #inv-filter").on("change", refreshFilters);

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "WA_L30", "NRL"];

                    colsToToggle.forEach(colName => {
                        let col = window.table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            function getRowSelectBySkuAndField(sku, field) {
                try {
                    let escapedSku = CSS.escape(sku); // escape special chars
                    return document.querySelector(`select[data-sku="${escapedSku}"][data-field="${field}"]`);
                } catch (e) {
                    console.warn("Invalid selector for SKU:", sku, e);
                    return null;
                }
            }

            document.body.style.zoom = "78%";

            // 7UB Chart Button Handler
            document.getElementById("7ub-chart-btn").addEventListener("click", function() {
                show7ubChart();
            });

            // Utilization Filter Button Handlers
            document.getElementById("over-utilized-btn").addEventListener("click", function() {
                if (window.currentUtilizationFilter === 'pink') {
                    filterByUtilization(null); // Clear filter
                } else {
                    filterByUtilization('pink');
                }
            });

            document.getElementById("under-utilized-btn").addEventListener("click", function() {
                if (window.currentUtilizationFilter === 'red') {
                    filterByUtilization(null); // Clear filter
                } else {
                    filterByUtilization('red');
                }
            });

            document.getElementById("correctly-utilized-btn").addEventListener("click", function() {
                if (window.currentUtilizationFilter === 'green') {
                    filterByUtilization(null); // Clear filter
                } else {
                    filterByUtilization('green');
                }
            });
        });

        let chart7ubInstance = null;

        function show7ubChart() {
            const modal = new bootstrap.Modal(document.getElementById('7ubChartModal'));
            modal.show();

            fetch('/walmart/utilized/bgt/7ub-chart-data')
                .then(res => {
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    return res.json();
                })
                .then(data => {
                    if(data.status === 200 && data.data && data.data.length > 0) {
                        const chartData = data.data;
                        const dates = chartData.map(d => d.date);
                        
                        const ctx = document.getElementById('7ubChart').getContext('2d');
                        
                        // Destroy existing chart if any
                        if(chart7ubInstance) {
                            chart7ubInstance.destroy();
                        }

                        chart7ubInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [
                                    {
                                        label: 'Pink (> 90%)',
                                        data: chartData.map(d => d.pink_count),
                                        borderColor: '#ff01d0',
                                        backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                        tension: 0.4,
                                        fill: true,
                                        borderWidth: 2
                                    },
                                    {
                                        label: 'Red (< 70%)',
                                        data: chartData.map(d => d.red_count),
                                        borderColor: '#ff2727',
                                        backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                        tension: 0.4,
                                        fill: true,
                                        borderWidth: 2
                                    },
                                    {
                                        label: 'Green (70-90%)',
                                        data: chartData.map(d => d.green_count),
                                        borderColor: '#05bd30',
                                        backgroundColor: 'rgba(5, 189, 48, 0.1)',
                                        tension: 0.4,
                                        fill: true,
                                        borderWidth: 2
                                    }
                                ]
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
                                        enabled: true
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
                    } else {
                        // Show message in modal
                        const ctx = document.getElementById('7ubChart').getContext('2d');
                        const canvas = document.getElementById('7ubChart');
                        const parent = canvas.parentElement;
                        parent.innerHTML = '<div class="text-center p-5"><p class="text-muted">No chart data available yet. Data will be collected starting from today.</p></div>';
                    }
                })
                .catch(err => {
                    console.error('Error loading chart:', err);
                    const canvas = document.getElementById('7ubChart');
                    if (canvas) {
                        const parent = canvas.parentElement;
                        parent.innerHTML = '<div class="text-center p-5"><p class="text-danger">Error loading chart data. Please try again later.</p><p class="text-muted small">' + err.message + '</p></div>';
                    }
                });
        }

        function filterByUtilization(type) {
            // Set the current utilization filter
            window.currentUtilizationFilter = type;
            
            console.log('Filter changed to:', type, 'Current filter value:', window.currentUtilizationFilter);
            
            // Refresh the table filter - use refreshFilters which preserves other filters
            if (window.table && window.refreshFilters) {
                // Use refreshFilters which will reapply the combinedFilter
                // The combinedFilter function reads window.currentUtilizationFilter dynamically
                window.refreshFilters();
            } else if (window.table && window.combinedFilter) {
                // Fallback: recreate filter function to force re-evaluation
                const filterWrapper = function(data) {
                    return window.combinedFilter(data);
                };
                
                // Set filter with new function reference to force re-evaluation
                window.table.setFilter(filterWrapper);
                
                // Update stats
                if (window.updateCampaignStats) {
                    setTimeout(function() {
                        window.updateCampaignStats();
                    }, 100);
                }
            }
        }
    </script>
@endsection

