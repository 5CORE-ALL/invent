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

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            display: flex;
            align-items: center;
            justify-content: center;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            line-height: 1.5;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            line-height: 1.5;
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

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-dot.green {
            background-color: #28a745;
        }

        .status-dot.red {
            background-color: #dc3545;
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
                        <!-- TOTAL SKU -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">TOTAL SKU</div>
                                <div class="h3 mb-0 fw-bold text-primary" id="total-campaign-count">0</div>
                            </div>
                        </div>

                        <!-- Total Spend -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Total Spend</div>
                                <div class="h3 mb-0 fw-bold text-success" id="total-spend">$0.00</div>
                            </div>
                        </div>

                        <!-- Total Sales -->
                        <div class="col-md-3 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="text-muted small">Total Sales</div>
                                <div class="h3 mb-0 fw-bold text-info" id="total-sales">$0.00</div>
                            </div>
                        </div>

                        <!-- Avg ACOS -->
                        <div class="col-md-3">
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
                                    <button id="zero-inv-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); color: white; border: none; min-width: 150px; height: 60px; padding: 8px 12px;">
                                        <div>0 INV</div>
                                        <div id="zero-inv-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="over-utilized-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #ff01d0 0%, #ff6ec7 100%); color: white; border: none; min-width: 150px; height: 60px; padding: 8px 12px;">
                                        <div>OVER UTILIZED</div>
                                        <div id="over-utilized-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="under-utilized-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #ff2727 0%, #ff6b6b 100%); color: white; border: none; min-width: 150px; height: 60px; padding: 8px 12px;">
                                        <div>UNDER UTILIZED</div>
                                        <div id="under-utilized-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="correctly-utilized-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #28a745 0%, #5cb85c 100%); color: white; border: none; min-width: 150px; height: 60px; padding: 8px 12px;">
                                        <div>CORRECTLY UTILIZED</div>
                                        <div id="correctly-utilized-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="missing-ads-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; min-width: 150px; height: 60px; padding: 8px 12px;">
                                        <div>MISSING ADS</div>
                                        <div id="missing-ads-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="running-ads-btn" class="btn btn-sm" style="background: linear-gradient(135deg, #28a745 0%, #5cb85c 100%); color: white; border: none; min-width: 150px; height: 60px; padding: 8px 12px;">
                                        <div>RUNNING ADS</div>
                                        <div id="running-ads-count" style="font-size: 1.2rem; font-weight: bold;">0</div>
                                    </button>
                                    <button id="show-all-btn" class="btn btn-sm btn-secondary" style="min-width: 150px; height: 60px; padding: 8px 12px; margin-left: 10px;">
                                        <i class="fa fa-refresh me-1"></i>
                                        <div>SHOW ALL</div>
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
                                    <button id="refresh-sheet-btn" class="btn btn-warning btn-md" title="Refresh data from Walmart source sheet">
                                        <i class="fa fa-refresh me-1"></i>
                                        Refresh Sheet
                                    </button>
                                    <button id="export-btn" class="btn btn-success btn-md">
                                        <i class="fa fa-download me-1"></i>
                                        Export
                                    </button>
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
                                    <select id="acos-filter" class="form-select form-select-md" style="width: 180px;">
                                        <option value="">All ACOS</option>
                                        <option value="gt25">ACOS > 25% (ALD BGT = 1)</option>
                                        <option value="20-25">ACOS 20%-25% (ALD BGT = 2)</option>
                                        <option value="15-20">ACOS 15%-20% (ALD BGT = 4)</option>
                                        <option value="10-15">ACOS 10%-15% (ALD BGT = 6)</option>
                                        <option value="5-10">ACOS 5%-10% (ALD BGT = 8)</option>
                                        <option value="0.01-5">ACOS 0.01%-5% (ALD BGT = 10)</option>
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

            // Global filter states
            var avgAcosValue = 0;
            window.currentUtilizationFilter = null;
            window.showMissingOnly = false;
            window.showZeroInvOnly = false;
            window.showRunningAdsOnly = false;
            window.showRunningAdsOnly = false;

            // Helper function to calculate ALD BGT from ACOS
            function calculateAldBgt(acos) {
                // ACOS > 25% → ALD BGT = 1
                if (acos > 25) {
                    return 1;
                }
                // ACOS 20%-25% → ALD BGT = 2
                else if (acos >= 20 && acos <= 25) {
                    return 2;
                }
                // ACOS 15%-20% → ALD BGT = 4
                else if (acos >= 15 && acos < 20) {
                    return 4;
                }
                // ACOS 10%-15% → ALD BGT = 6
                else if (acos >= 10 && acos < 15) {
                    return 6;
                }
                // ACOS 5%-10% → ALD BGT = 8
                else if (acos >= 5 && acos < 10) {
                    return 8;
                }
                // ACOS 0.01%-5% → ALD BGT = 10
                else if (acos >= 0.01 && acos < 5) {
                    return 10;
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
                        title: "SKU",
                        field: "sku",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-sku="${sku}" 
                                style="cursor:pointer; margin-left:8px; pointer-events: auto;"></i>
                            `;
                        },
                        cellClick: function(e, cell) {
                            const target = e.target;
                            if (target.classList.contains("toggle-cols-btn") || target.closest(".toggle-cols-btn")) {
                                e.stopPropagation();
                                let colsToToggle = ["INV", "L30", "DIL %", "WA_L30", "NRL"];
                                colsToToggle.forEach(colName => {
                                    let col = window.table.getColumn(colName);
                                    if (col) {
                                        col.toggle();
                                    }
                                });
                            }
                        }
                    },
                    {
                        title: "MISSING",
                        field: "hasCampaign",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.INV || 0);
                            
                            // Don't show red dot for 0 INV items
                            if (inv === 0) {
                                return `<div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot green" title="0 INV - Not applicable"></span>
                                </div>`;
                            }
                            
                            const hasCampaign = row.hasCampaign ?? (row.campaignName?.trim() !== '');
                            const dotColor = hasCampaign ? 'green' : 'red';
                            const title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                            return `<div style="display: flex; align-items: center; justify-content: center;">
                                <span class="status-dot ${dotColor}" title="${title}"></span>
                            </div>`;
                        }
                    },
                    {
                        title: "Price",
                        field: "price",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = parseFloat(cell.getValue() || 0);
                            var gpft = parseFloat(row.GPFT || 0);
                            var pft = parseFloat(row.PFT || 0);
                            var roi = parseFloat(row.ROI || 0);
                            var tooltipText = "GPFT%: " + Math.round(gpft) + "%\nPFT%: " + Math.round(pft) + "%\nROI%: " + Math.round(roi) + "%";
                            
                            return `<div class="text-center">$${value.toFixed(2)}<i class="fa fa-info-circle ms-1 info-icon-price-toggle" style="cursor: pointer; color: #0d6efd;" title="${tooltipText}"></i></div>`;
                        },
                        sorter: "number",
                        width: 100
                    },
                    {
                        title: "GPFT%",
                        field: "GPFT",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            const percent = Math.round(value);
                            let color = '';
                            
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent}%</span>`;
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "PFT%",
                        field: "PFT",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            const percent = Math.round(value);
                            let color = '';
                            
                            // Same color logic as GPFT%
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent}%</span>`;
                        },
                        sorter: "number",
                        width: 80
                    },
                    {
                        title: "ROI%",
                        field: "ROI",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            const percent = Math.round(value);
                            let color = '';
                            
                            // ROI% color logic from walmart tabulator view
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent}%</span>`;
                        },
                        sorter: "number",
                        width: 80
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
                                <span>${Math.round(value) + "%"}</span>
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
                            var acos = parseFloat(row.acos_l30) || 0;
                            var aldBgt = calculateAldBgt(acos);
                            
                            // 7 UB% = (L7 spend/(ald bgt*7))*100
                            var ub7 = (aldBgt > 0 && aldBgt * 7 > 0) ? (spend_l7 / (aldBgt * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            td.style.backgroundColor = '';
                            
                            var value = Math.round(ub7) + "%";
                            
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
                        title: "1 UB%",
                        field: "spend_l1",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_l1 = parseFloat(row.spend_l1) || 0;
                            var acos = parseFloat(row.acos_l30) || 0;
                            var aldBgt = calculateAldBgt(acos);
                            
                            // 1 UB% = (L1 spend/(ald bgt))*100
                            var ub1 = aldBgt > 0 ? (spend_l1 / aldBgt) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            td.style.backgroundColor = '';
                            
                            var value = Math.round(ub1) + "%";
                            
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
                            
                            // Check if campaign is missing (red dot)
                            const hasCampaign = row.hasCampaign ?? (row.campaignName?.trim() !== '');
                            if (!hasCampaign) {
                                return ''; // Return blank for missing ads
                            }
                            
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
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = (cell.getValue() || '').toString();
                            return `<span>${value}</span>`;
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

            // Helper: Check if campaign exists
            function hasCampaign(row) {
                return row.hasCampaign ?? (row.campaignName?.trim() !== '');
            }

            // Helper: Calculate 7UB percentage
            function calculate7UB(row) {
                const spend_l7 = parseFloat(row.spend_l7 || 0);
                const acos = parseFloat(row.acos_l30 || 0);
                const aldBgt = calculateAldBgt(acos);
                // 7 UB% = (L7 spend/(ald bgt*7))*100
                return (aldBgt > 0 && aldBgt * 7 > 0) ? (spend_l7 / (aldBgt * 7)) * 100 : 0;
            }

            window.table.on("tableBuilt", function () {
                // Combined filter function
                function combinedFilter(data) {
                    // Filter out parent rows
                    const sku = data.sku || '';
                    if (sku.toUpperCase().includes("PARENT")) return false;
                    
                    // 0 INV filter
                    if (window.showZeroInvOnly && parseFloat(data.INV || 0) !== 0) return false;
                    
                    // Missing ads filter (red dots only, exclude 0 INV items)
                    if (window.showMissingOnly) {
                        const inv = parseFloat(data.INV || 0);
                        if (inv === 0 || hasCampaign(data)) return false;
                    }
                    
                    // Running ads filter (green dots only - campaigns that exist)
                    if (window.showRunningAdsOnly && !hasCampaign(data)) return false;
                    
                    // Utilization filter (7UB)
                    if (window.currentUtilizationFilter) {
                        const ub7 = calculate7UB(data);
                        const filter = window.currentUtilizationFilter;
                        if (filter === 'pink' && ub7 <= 90) return false;
                        if (filter === 'red' && ub7 >= 70) return false;
                        if (filter === 'green' && (ub7 < 70 || ub7 > 90)) return false;
                    }

                    // Search filter
                    const searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal) {
                        const matches = data.campaignName?.toLowerCase().includes(searchVal) || 
                                       data.sku?.toLowerCase().includes(searchVal);
                        if (!matches) return false;
                    }

                    // Status filter
                    const statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) return false;

                    // Inventory filter
                    const invFilterVal = $("#inv-filter").val();
                    const invVal = parseFloat(data.INV || 0);
                    if (invFilterVal === "INV_0" && invVal !== 0) return false;
                    if (invFilterVal === "OTHERS" && invVal === 0) return false;

                    // NRL filter
                    const nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        const rowSelect = getRowSelectBySkuAndField(data.sku, "NRL");
                        const rowVal = rowSelect?.value || data.NRL || "";
                        if (rowVal !== nrlFilterVal) return false;
                    }

                    // ACOS filter (based on ALD BGT ranges)
                    const acosFilterVal = $("#acos-filter").val();
                    if (acosFilterVal) {
                        const acos = parseFloat(data.acos_l30 || 0);
                        let shouldInclude = false;
                        
                        switch(acosFilterVal) {
                            case 'gt25':
                                // ACOS > 25% → ALD BGT = 1
                                shouldInclude = acos > 25;
                                break;
                            case '20-25':
                                // ACOS 20%-25% → ALD BGT = 2
                                shouldInclude = acos >= 20 && acos <= 25;
                                break;
                            case '15-20':
                                // ACOS 15%-20% → ALD BGT = 4
                                shouldInclude = acos >= 15 && acos < 20;
                                break;
                            case '10-15':
                                // ACOS 10%-15% → ALD BGT = 6
                                shouldInclude = acos >= 10 && acos < 15;
                                break;
                            case '5-10':
                                // ACOS 5%-10% → ALD BGT = 8
                                shouldInclude = acos >= 5 && acos < 10;
                                break;
                            case '0.01-5':
                                // ACOS 0.01%-5% → ALD BGT = 10
                                shouldInclude = acos >= 0.01 && acos < 5;
                                break;
                        }
                        
                        if (!shouldInclude) return false;
                    }

                    return true;
                }

                // Update campaign statistics
                function updateCampaignStats() {
                    if (!window.table) return;
                    const total = window.table.getDataCount();
                    const filtered = window.table.getDataCount("active");
                    const percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                    document.getElementById("total-campaign-count").innerText = total;
                    document.getElementById("total-campaigns").innerText = filtered;
                    document.getElementById("percentage-campaigns").innerText = percentage + "%";
                    updateUtilizationCounts();
                }
                
                // Update filter button counts
                function updateUtilizationCounts() {
                    if (!window.table) return;
                    
                    const allData = window.table.getData();
                    let counts = { pink: 0, red: 0, green: 0, missing: 0, zeroInv: 0, running: 0 };
                    
                    allData.forEach(row => {
                        // Skip parent rows
                        const sku = row.sku || '';
                        if (sku.toUpperCase().includes("PARENT")) return;
                        
                        // Count 0 INV
                        if (parseFloat(row.INV || 0) === 0) counts.zeroInv++;
                        
                        // Count missing campaigns (exclude 0 INV items)
                        const inv = parseFloat(row.INV || 0);
                        if (inv !== 0 && !hasCampaign(row)) counts.missing++;
                        
                        // Count running campaigns (green dots - campaigns that exist)
                        if (hasCampaign(row)) counts.running++;
                        
                        // Count utilization types
                        const ub7 = calculate7UB(row);
                        if (ub7 > 90) counts.pink++;
                        else if (ub7 < 70) counts.red++;
                        else if (ub7 >= 70 && ub7 <= 90) counts.green++;
                    });
                    
                    // Update button counts
                    const elements = {
                        'over-utilized-count': counts.pink,
                        'under-utilized-count': counts.red,
                        'correctly-utilized-count': counts.green,
                        'missing-ads-count': counts.missing,
                        'zero-inv-count': counts.zeroInv,
                        'running-ads-count': counts.running
                    };
                    
                    Object.entries(elements).forEach(([id, count]) => {
                        const el = document.getElementById(id);
                        if (el) el.innerText = count;
                    });
                }

                // Refresh table filters
                function refreshFilters() {
                    if (window.table) {
                        window.table.setFilter(combinedFilter);
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
                $("#status-filter, #nrl-filter, #inv-filter, #acos-filter").on("change", refreshFilters);

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                // Check if clicked element or its parent has the toggle-cols-btn class
                const toggleBtn = e.target.closest(".toggle-cols-btn") || 
                                 (e.target.classList.contains("toggle-cols-btn") ? e.target : null);
                
                if (toggleBtn) {
                    let colsToToggle = ["INV", "L30", "DIL %", "WA_L30", "NRL"];

                    colsToToggle.forEach(colName => {
                        let col = window.table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                
                // Price info icon toggle for GPFT%, PFT%, ROI%
                if (e.target.classList.contains('info-icon-price-toggle')) {
                    e.stopPropagation();
                    var gpftCol = window.table.getColumn('GPFT');
                    var pftCol = window.table.getColumn('PFT');
                    var roiCol = window.table.getColumn('ROI');
                    
                    // Toggle visibility
                    if (gpftCol && gpftCol.isVisible()) {
                        gpftCol.hide();
                        pftCol.hide();
                        roiCol.hide();
                    } else {
                        gpftCol.show();
                        pftCol.show();
                        roiCol.show();
                    }
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

            // Initialize Show All button as active (no filter on page load)
            const showAllBtn = document.getElementById("show-all-btn");
            if (showAllBtn) {
                showAllBtn.classList.remove('btn-secondary');
                showAllBtn.classList.add('btn-primary');
            }

            // Refresh Sheet Button Handler
            document.getElementById("refresh-sheet-btn").addEventListener("click", function() {
                refreshWalmartSheet();
            });

            // Export Button Handler
            document.getElementById("export-btn").addEventListener("click", function() {
                exportTableData();
            });

            // 7UB Chart Button Handler
            document.getElementById("7ub-chart-btn").addEventListener("click", function() {
                show7ubChart();
            });

            // Helper: Toggle filter button state
            function toggleFilterButton(btnId, isActive, shadowColor) {
                const btn = document.getElementById(btnId);
                if (!btn) return;
                if (isActive) {
                    btn.style.transform = 'scale(1.05)';
                    btn.style.boxShadow = `0 4px 12px ${shadowColor}`;
                } else {
                    btn.style.transform = 'scale(1)';
                    btn.style.boxShadow = 'none';
                }
            }

            // 0 INV Button Handler
            document.getElementById("zero-inv-btn").addEventListener("click", function() {
                window.showZeroInvOnly = !window.showZeroInvOnly;
                
                if (window.showZeroInvOnly) {
                    window.currentUtilizationFilter = null;
                    window.showMissingOnly = false;
                    window.showRunningAdsOnly = false;
                    updateUtilizationButtonStates();
                    toggleFilterButton("missing-ads-btn", false);
                    toggleFilterButton("running-ads-btn", false);
                }
                
                toggleFilterButton("zero-inv-btn", window.showZeroInvOnly, 'rgba(255, 193, 7, 0.5)');
                if (window.refreshFilters) window.refreshFilters();
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

            // Missing Ads Button Handler
            document.getElementById("missing-ads-btn").addEventListener("click", function() {
                window.showMissingOnly = !window.showMissingOnly;
                
                if (window.showMissingOnly) {
                    window.currentUtilizationFilter = null;
                    window.showZeroInvOnly = false;
                    window.showRunningAdsOnly = false;
                    updateUtilizationButtonStates();
                    toggleFilterButton("zero-inv-btn", false);
                    toggleFilterButton("running-ads-btn", false);
                }
                
                toggleFilterButton("missing-ads-btn", window.showMissingOnly, 'rgba(220, 53, 69, 0.5)');
                if (window.refreshFilters) window.refreshFilters();
            });

            // Running Ads Button Handler
            document.getElementById("running-ads-btn").addEventListener("click", function() {
                window.showRunningAdsOnly = !window.showRunningAdsOnly;
                
                if (window.showRunningAdsOnly) {
                    window.currentUtilizationFilter = null;
                    window.showZeroInvOnly = false;
                    window.showMissingOnly = false;
                    updateUtilizationButtonStates();
                    toggleFilterButton("zero-inv-btn", false);
                    toggleFilterButton("missing-ads-btn", false);
                }
                
                toggleFilterButton("running-ads-btn", window.showRunningAdsOnly, 'rgba(40, 167, 69, 0.5)');
                if (window.refreshFilters) window.refreshFilters();
            });

            // Show All Button Handler
            document.getElementById("show-all-btn").addEventListener("click", function() {
                filterByUtilization(null);
                window.showMissingOnly = false;
                window.showZeroInvOnly = false;
                window.showRunningAdsOnly = false;
                toggleFilterButton("missing-ads-btn", false);
                toggleFilterButton("zero-inv-btn", false);
                toggleFilterButton("running-ads-btn", false);
                if (window.refreshFilters) window.refreshFilters();
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

        function refreshWalmartSheet() {
            const refreshBtn = document.getElementById("refresh-sheet-btn");
            const originalHtml = refreshBtn.innerHTML;
            
            // Disable button and show loading state
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Refreshing...';
            
            // Show progress overlay
            const progressOverlay = document.getElementById("progress-overlay");
            if (progressOverlay) {
                progressOverlay.style.display = 'block';
            }
            
            // First refresh product sheet
            fetch('/walmart/utilized/bgt/refresh-sheet', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    // Then refresh campaign data (L30, L7, L1)
                    return fetch('/walmart/utilized/bgt/refresh-campaign-data', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                } else {
                    throw new Error(data.message || 'Error refreshing product sheet');
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 200) {
                    // Show success message
                    alert('Walmart data refreshed successfully! Synced ' + (data.synced_count || 0) + ' campaign records.');
                    
                    // Reload table data
                    if (window.table) {
                        window.table.replaceData();
                    }
                } else {
                    alert('Error refreshing campaign data: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error refreshing data: ' + error.message);
            })
            .finally(() => {
                // Re-enable button and restore original state
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = originalHtml;
                
                // Hide progress overlay
                if (progressOverlay) {
                    progressOverlay.style.display = 'none';
                }
            });
        }

        function exportTableData() {
            if (!window.table) {
                alert('Table not initialized');
                return;
            }

            // Get filtered/visible data
            const data = window.table.getData("active");
            
            if (!data || data.length === 0) {
                alert('No data to export');
                return;
            }

            // Prepare data for export - get visible columns only
            const visibleColumns = window.table.getColumns().filter(col => col.isVisible());
            const headers = visibleColumns.map(col => col.getDefinition().title || col.getField());
            
            // Create worksheet data
            const wsData = [];
            
            // Add headers
            wsData.push(headers);
            
            // Add rows
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    // Format values based on column type
                    if (value === null || value === undefined) {
                        value = '';
                    } else if (typeof value === 'number') {
                        value = value;
                    } else {
                        value = String(value);
                    }
                    
                    rowData.push(value);
                });
                wsData.push(rowData);
            });
            
            // Create workbook
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(wsData);
            
            // Set column widths
            const colWidths = visibleColumns.map(() => ({ wch: 15 }));
            ws['!cols'] = colWidths;
            
            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(wb, ws, 'Walmart BGT Util');
            
            // Generate filename with timestamp
            const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
            const filename = `walmart-bgt-util-${timestamp}.xlsx`;
            
            // Download file
            XLSX.writeFile(wb, filename);
        }

        function updateUtilizationButtonStates() {
            const overBtn = document.getElementById("over-utilized-btn");
            const underBtn = document.getElementById("under-utilized-btn");
            const correctlyBtn = document.getElementById("correctly-utilized-btn");
            const showAllBtn = document.getElementById("show-all-btn");
            
            // Reset all button styles
            [overBtn, underBtn, correctlyBtn].forEach(btn => {
                btn.style.opacity = '1';
                btn.style.transform = 'scale(1)';
                btn.style.boxShadow = 'none';
            });
            
            const type = window.currentUtilizationFilter;
            
            // Highlight active filter button or show all button
            if (type === 'pink') {
                overBtn.style.opacity = '1';
                overBtn.style.transform = 'scale(1.05)';
                overBtn.style.boxShadow = '0 4px 12px rgba(255, 1, 208, 0.5)';
                showAllBtn.classList.remove('btn-primary');
                showAllBtn.classList.add('btn-secondary');
            } else if (type === 'red') {
                underBtn.style.opacity = '1';
                underBtn.style.transform = 'scale(1.05)';
                underBtn.style.boxShadow = '0 4px 12px rgba(255, 39, 39, 0.5)';
                showAllBtn.classList.remove('btn-primary');
                showAllBtn.classList.add('btn-secondary');
            } else if (type === 'green') {
                correctlyBtn.style.opacity = '1';
                correctlyBtn.style.transform = 'scale(1.05)';
                correctlyBtn.style.boxShadow = '0 4px 12px rgba(40, 167, 69, 0.5)';
                showAllBtn.classList.remove('btn-primary');
                showAllBtn.classList.add('btn-secondary');
            } else {
                // No filter active - highlight show all button
                showAllBtn.classList.remove('btn-secondary');
                showAllBtn.classList.add('btn-primary');
            }
        }

        // Filter by utilization type
        function filterByUtilization(type) {
            window.currentUtilizationFilter = type;
            
            if (type !== null) {
                window.showMissingOnly = false;
                window.showZeroInvOnly = false;
                window.showRunningAdsOnly = false;
                toggleFilterButton("missing-ads-btn", false);
                toggleFilterButton("zero-inv-btn", false);
                toggleFilterButton("running-ads-btn", false);
            }
            
            updateUtilizationButtonStates();
            if (window.refreshFilters) window.refreshFilters();
        }
    </script>
@endsection


