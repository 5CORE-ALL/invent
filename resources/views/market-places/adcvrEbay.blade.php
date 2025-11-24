@extends('layouts.vertical', ['title' => 'Ebay Pricing KW', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        .price-cell input {
            color: #000 !important;
            background-color: #fff !important;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay - AD CVR',
        'sub_title' => 'Ebay - AD CVR',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Pricing KW
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="clicks-filter" class="form-select form-select-md" style="width: 175px;">
                                        <option value="">Select CLICKS L30</option>
                                        <option value="ALL">ALL</option>
                                        <option value="CLICKS_L30">CLICKS L30 > 25</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="nrl-filter" class="form-select form-select-md">
                                        <option value="">Select NRL</option>
                                        <option value="NRL">NRL</option>
                                        <option value="RL">RL</option>
                                    </select>

                                    <select id="nra-filter" class="form-select form-select-md">
                                        <option value="">Select NRA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="RA">RA</option>
                                        <option value="LATER">LATER</option>
                                    </select>

                                    <select id="fba-filter" class="form-select form-select-md">
                                        <option value="">Select FBA</option>
                                        <option value="FBA">FBA</option>
                                        <option value="FBM">FBM</option>
                                        <option value="BOTH">BOTH</option>
                                    </select>
                                    <select id="cvr-color-filter" class="form-select form-select-md">
                                        <option value="">Select CVR</option>
                                        <option value="red">Red (&lt; 5%)</option>
                                        <option value="green">Green (5% - 10%)</option>
                                        <option value="pink">Pink (&gt; 10%)</option>
                                    </select>
                                    <select id="pft-color-filter" class="form-select form-select-md">
                                        <option value="">Select PFT</option>
                                        <option value="red">Red (&lt; 10%)</option>
                                        <option value="yellow">Yellow (10% - 15%)</option>
                                        <option value="blue">Blue (15% - 20%)</option>
                                        <option value="green">Green (20% - 40%)</option>
                                        <option value="pink">Pink (&gt; 40%)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none">
                                        APR ALL SBID
                                    </button>
                                    <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success d-flex align-items-center justify-content-center">
                                        <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                    </a>
                                    <button class="btn btn-success btn-md">
                                        <i class="fa fa-arrow-up me-1"></i>
                                        Count bids: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
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
                                        <option value="ENABLED">Enabled</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
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
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        function fmtPct(v) {
            if (v === null || v === undefined || v === "") return "-";
            const num = parseFloat(v);
            if (isNaN(num)) return "-";

            
            return Math.round(num * 100) + "%";
        }

        document.addEventListener("DOMContentLoaded", function() {

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/ad-cvr-ebay-data",
                layout: "fitDataFill",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                initialSort:[
                    {column:"cvr_l30", dir:"asc"}
                ],
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
                        title: "A L30",
                        field: "A_L30",
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "CVR L30",
                        field: "cvr_l30",
                        formatter: function(cell){
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

                            let row = cell.getRow();
                            let rowData = row.getData();
                            let basePrice = parseFloat(rowData.ebay_price) || 0;

                            if (value < 5) {
                                let newPrice = basePrice * 0.99;
                                row.update({ ebay_price: newPrice.toFixed(2) });
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
                        }
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0)
                    },
                    {
                        title: "ACOS L30",
                        field: "acos_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let acos_L30 = parseFloat(cell.getValue() || 0).toFixed(0);
                            return `
                                <span>${acos_L30 + "%"}</span>
                                <i class="fa fa-info-circle text-primary toggle-acos-cols-btn" 
                                data-lmp="${acos_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                            
                        }
                    },
                    {
                        title: "ACOS L7",
                        field: "acos_L7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0) + "%"}</span>
                            `;
                            
                        },
                        visible: false
                    },
                    {
                        title: "SPEND L30",
                        field: "spend_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let spend_l30 = parseFloat(cell.getValue() || 0).toFixed(0);
                            return `
                                <span>${spend_l30}</span>
                                <i class="fa fa-info-circle text-primary toggle-spend-cols-btn" 
                                data-lmp="${spend_l30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "SPEND L7",
                        field: "spend_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "SALES L30",
                        field: "ad_sales_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let ad_sales_l30 = parseFloat(cell.getValue() || 0).toFixed(0);
                            return `
                                <span>${ad_sales_l30}</span>
                                <i class="fa fa-info-circle text-primary toggle-sales-cols-btn" 
                                data-lmp="${ad_sales_l30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "SALES L7",
                        field: "ad_sales_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CLK L30",
                        field: "clicks_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let clicks_L30 = parseFloat(cell.getValue() || 0).toFixed(0);
                            return `
                                <span>${clicks_L30}</span>
                                <i class="fa fa-info-circle text-primary toggle-clicks-cols-btn" 
                                data-lmp="${clicks_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CLK L7",
                        field: "clicks_L7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ADS SOLD",
                        field: "A_L30",
                    },
                    {
                        title: "Price",
                        field: "price",
                        formatter: function(cell) {
                            let price = cell.getValue();
                            let uniqueId = "popup-" + Math.random().toString(36).substr(2, 9);

                            return `
                                <span>${price}</span>
                                <i class="fa fa-info-circle text-primary info-icon" 
                                    data-id="${uniqueId}" 
                                    data-price="${price}" 
                                    style="cursor:pointer; margin-left:8px;">
                                </i>
                            `;
                        },
                        cellClick: function (e, cell) {
                            let target = e.target;
                            if (target.classList.contains("info-icon")) {
                                let price = parseFloat(target.getAttribute("data-price"));
                                let popupId = target.getAttribute("data-id");
                                let existingPopup = document.getElementById(popupId);

                                if (existingPopup) {
                                    existingPopup.remove();
                                    return;
                                }

                                document.querySelectorAll(".draggable-popup").forEach(p => p.remove());
                                let rowData = cell.getRow().getData();

                                const ebayAdUpdates = {{ $ebayAdUpdates ?? 0 }};
                                const percentage = {{ $ebayPercentage ?? 0 }};
                                const costPercentage = percentage + ebayAdUpdates;

                                let ship = Number(rowData.SHIP) || 0;
                                let lp = Number(rowData.LP) || 0;
                                let gPft = ((price * costPercentage) - ship - lp) / price;
                                if (isNaN(gPft) || !isFinite(gPft)) gPft = 0;
                                gPft = (gPft).toFixed(0);

                                const getColorPFT = (val) => {
                                    if (val < 10) return "red";
                                    if (val >= 10 && val < 15) return "yellow";
                                    if (val >= 15 && val < 20) return "blue";
                                    if (val >= 20 && val <= 40) return "green";
                                    return "pink";
                                };
                                const getColorProfit = (val) => {
                                    if (val <= 0) return '#ff0000';
                                    if (val > 0 && val <= 10) return '#ff0000';
                                    if (val > 10 && val <= 14) return '#fd7e14';
                                    if (val > 14 && val <= 19) return '#0d6efd';
                                    if (val > 19 && val <= 40) return '#198754';
                                    if (val > 40) return '#800080';
                                    return '#000';
                                };
                                const fmtPct = (v) => isNaN(v) ? '' : `${parseFloat(v).toFixed(0)}%`;
                                const fmtColored = (val, color) => 
                                    `<span style="font-weight:600; color:${color};">${fmtPct(val)}</span>`;

                                let popupTableHTML = `
                                    <table class="popup-inner-table">
                                        <tr><th>LMP</th><td>${rowData.lmp ?? ''}</td></tr>
                                        ${Array.from({ length: 11 }, (_, i) => {
                                            let idx = i + 1;
                                            return `<tr><th>LMP ${idx+1}</th><td>${rowData["lmp_" + idx] ?? ''}</td></tr>`;
                                        }).join("")}
                                        <tr><th>PFT%</th><td>${fmtColored(rowData.PFT_percentage, getColorProfit(rowData.PFT_percentage))}</td></tr>
                                        <tr><th>GPFT%</th><td>${fmtColored(gPft, getColorProfit(gPft))}</td></tr>
                                        <tr><th>TPFT%</th><td>${fmtColored(rowData.TPFT, getColorProfit(rowData.TPFT))}</td></tr>
                                        <tr>
                                            <th>SPRICE</th>
                                            <td>
                                                <input type="number" id="sprice-input" value="${parseFloat(rowData.ebay_price ?? 0).toFixed(2)}" style="width:80px; margin-right:6px;" />
                                                <button id="approve-sprice" style="padding:2px 8px; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer;">✔</button>
                                            </td>
                                        </tr>
                                        <tr><th>SPFT%</th><td>${fmtColored(rowData.ebay_pft, getColorProfit(rowData.ebay_pft))}</td></tr>
                                        <tr><th>SROI%</th><td>${fmtColored(rowData.ebay_roi, getColorProfit(rowData.ebay_roi))}</td></tr>
                                    </table>
                                `;

                                let popup = document.createElement("div");
                                popup.id = popupId;
                                popup.className = "draggable-popup";
                                popup.innerHTML = `
                                    <div class="popup-header">
                                        <span>PRICE</span>
                                        <button class="popup-close">&times;</button>
                                    </div>
                                    <div class="popup-body">
                                        ${popupTableHTML}
                                    </div>
                                `;
                                document.body.appendChild(popup);

                                const popupRect = popup.getBoundingClientRect();
                                popup.style.left = `calc(50% - ${popupRect.width / 2}px)`;
                                popup.style.top = `calc(50% - ${popupRect.height / 2}px)`;

                                popup.querySelector(".popup-close").onclick = () => popup.remove();
                                makeDraggable(popup);

                                const spriceInput = popup.querySelector("#sprice-input");

                                let spftCell;
                                popup.querySelectorAll("tr").forEach(row => {
                                    const label = row.querySelector("th")?.innerText?.trim();
                                    if (label === "SPFT%") {
                                        spftCell = row.querySelector("td span");
                                    }
                                });

                                spriceInput.addEventListener("blur", function () {
                                    let enteredPrice = parseFloat(spriceInput.value);
                                    if (isNaN(enteredPrice) || enteredPrice <= 0) return;

                                    let ship = Number(rowData.SHIP) || 0;
                                    let lp   = Number(rowData.LP) || 0;

                                    let raw = ((enteredPrice * percentage) - lp - ship) / enteredPrice;
                                    let spft = (raw).toFixed(2);

                                    let color = getColorProfit(spft);

                                    spftCell.innerHTML = `<span style="font-weight:600; color:${color};">${spft}%</span>`;
                                });

                                popup.querySelector("#approve-sprice").addEventListener("click", async function () {
                                    const btn = this;
                                    const input = document.getElementById("sprice-input");
                                    let newPrice = parseFloat(input.value);

                                    if (isNaN(newPrice) || newPrice <= 0) {
                                        alert("Please enter a valid price.");
                                        return;
                                    }

                                    const originalText = btn.innerHTML;
                                    btn.disabled = true;
                                    btn.innerHTML = `<span class="spinner" style="
                                        display:inline-block;
                                        width:14px;
                                        height:14px;
                                        border:2px solid #fff;
                                        border-top:2px solid transparent;
                                        border-radius:50%;
                                        animation: spin 0.8s linear infinite;
                                    "></span>`;

                                    try {
                                        const res = await fetch("/push-ebay-price", {
                                            method: "POST",
                                            headers: {
                                                "Content-Type": "application/json",
                                                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                                            },
                                            body: JSON.stringify({
                                                sku: rowData.sku,
                                                price: newPrice
                                            })
                                        });

                                        let data = null;
                                        try {
                                            data = await res.json();
                                        } catch (e) {
                                            // non-JSON response
                                            data = null;
                                        }

                                        const okFromBody = data && (data.status === 200 || data.success === true || data.success === 'true');
                                        const success = okFromBody || res.ok;

                                        if (success) {
                                            btn.innerHTML = "✔";
                                            btn.style.background = "#198754";
                                            // update visible row price so UI reflects change immediately
                                            try {
                                                const rowComp = table.getRow(rowData.sku);
                                                if (rowComp) {
                                                    rowComp.update({ ebay_price: parseFloat(newPrice).toFixed(2), price: parseFloat(newPrice).toFixed(2) });
                                                }
                                            } catch (e) {
                                                // ignore table update errors
                                            }

                                            alert("Price updated successfully! Please refresh if values don't update automatically.");
                                            popup.remove();
                                        } else {
                                            const msg = (data && (data.message || data.error || data.msg)) || `HTTP ${res.status}`;
                                            throw new Error(msg || "Failed to update price.");
                                        }

                                    } catch (err) {
                                        console.error(err);
                                        alert("Error while updating price. Please try again.");
                                        btn.disabled = false;
                                        btn.innerHTML = originalText;
                                    }
                                });
                            }
                        }
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            function makeDraggable(el) {
                const header = el.querySelector(".popup-header");
                let offsetX, offsetY, isDown = false;

                header.style.cursor = "move";

                header.addEventListener("mousedown", (e) => {
                    isDown = true;
                    offsetX = e.clientX - el.offsetLeft;
                    offsetY = e.clientY - el.offsetTop;
                    header.style.userSelect = "none";
                });

                document.addEventListener("mouseup", () => { isDown = false; });
                document.addEventListener("mousemove", (e) => {
                    if (!isDown) return;
                    el.style.left = e.clientX - offsetX + "px";
                    el.style.top = e.clientY - offsetY + "px";
                });
            }

            const style = document.createElement("style");
            style.textContent = `
            .draggable-popup {
                position: fixed;
                z-index: 9999;
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 8px;
                width: 500px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                font-family: sans-serif;
            }
            .popup-header {
                background: #007bff;
                color: #fff;
                padding: 6px 10px;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .popup-header button {
                background: none;
                border: none;
                color: white;
                font-size: 16px;
                cursor: pointer;
            }
            .popup-body {
                padding: 10px;
            }
            `;

            style.textContent += `
                .popup-inner-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 6px;
                    font-size: 15px;
                }
                .popup-inner-table th {
                    background: #f8f9fa;
                    text-align: left;
                    padding: 4px 6px;
                    border: 1px solid #ddd;
                    width: 45%;
                    color: #000;
                }
                .popup-inner-table td {
                    padding: 4px 6px;
                    border: 1px solid #ddd;
                    text-align: right;
                    color: #000;
                }
                .popup-inner-table tr:nth-child(even) {
                    background: #f9f9f9;
                }
                `;

            document.head.appendChild(style);

            // table.on("cellEdited", function(cell) {
            //     const field = cell.getField();

            //     if (field === "ebay_price") {
            //         const row = cell.getRow();
            //         const data = row.getData();

            //         const ebay_price = Number(data.ebay_price) || 0;
            //         const lp   = Number(data.LP) || 0;
            //         const ship = Number(data.SHIP) || 0;

            //         const ebay_pft = ebay_price > 0
            //             ? ((ebay_price * 0.70 - lp - ship) / ebay_price)
            //             : 0;

            //         const ebay_roi = (lp > 0 && ebay_price > 0)
            //             ? ((ebay_price * 0.70 - lp - ship) / lp)
            //             : 0;

            //         row.update({
            //             ebay_pft: ebay_pft,
            //             ebay_roi: ebay_roi
            //         });

            //         fetch('/update-ebay-price', {
            //             method: 'POST',
            //             headers: {
            //                 'Content-Type': 'application/json',
            //                 'X-CSRF-TOKEN': document
            //                     .querySelector('meta[name="csrf-token"]')
            //                     .getAttribute('content')
            //             },
            //             body: JSON.stringify({
            //                 sku: data.sku,
            //                 price: ebay_price
            //             })
            //         })
            //         .then(res => {
            //             if (!res.ok) throw new Error(`HTTP ${res.status}`);
            //             return res.json();
            //         })
            //         .then(result => {
            //             console.log('✅ Ebay price updated successfully:', result.message || result);
            //         })
            //         .catch(err => {
            //             console.error('❌ Update failed:', err);
            //         });
            //     }
            // });

            document.addEventListener("change", function(e){
                if(e.target.classList.contains("editable-select")){
                    let sku   = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-ebay-nr-nrl-fba', {
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

            table.on("tableBuilt", function() {

                function combinedFilter(data) {

                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    let clicksFilterVal = $("#clicks-filter").val();
                    let clicks_L30 = parseFloat(data.clicks_L30) || 0;

                    if (!clicksFilterVal) {
                        if (clicks_L30 <= 25) return false;
                    } else {
                        // When user selects a filter from dropdown
                        if (clicksFilterVal === "CLICKS_L30") {
                            if (clicks_L30 <= 25) return false;
                        } else if (clicksFilterVal === "ALL") {
                            // Show all rows
                        } else if (clicksFilterVal === "OTHERS") {
                            if (clicks_L30 > 25) return false;
                        }
                    }

                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal) {
                        if (parseFloat(data.INV) === 0) return false;
                    } else if (invFilterVal === "INV_0") {
                        if (parseFloat(data.INV) !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        if (parseFloat(data.INV) === 0) return false;
                    }

                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="NRL"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NRL || "";

                        if (rowVal !== nrlFilterVal) return false;
                    }

                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="NR"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NR || "";

                        if (rowVal !== nraFilterVal) return false;
                    }

                    let fbaFilterVal = $("#fba-filter").val();
                    if (fbaFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="FBA"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.FBA || "";

                        if (rowVal !== fbaFilterVal) return false;
                    }

                    let cvrColorFilterVal = $("#cvr-color-filter").val();
                    if (cvrColorFilterVal) {
                        let cvrValue = parseFloat(data.cvr_l30) || 0;

                        let color = "";
                        if (cvrValue < 5) {
                            color = "red";
                        } else if (cvrValue >= 5 && cvrValue <= 10) {
                            color = "green";
                        } else if (cvrValue > 10) {
                            color = "pink";
                        }

                        if (color !== cvrColorFilterVal) return false;
                    }

                    let pftColorFilterVal = $("#pft-color-filter").val();
                    if (pftColorFilterVal) {
                        let pftValue = parseFloat(data.PFT_percentage) || 0;
                        let color = "";
                        if (pftValue < 10) {
                            color = "red";
                        } else if (pftValue >= 10 && pftValue < 15) {
                            color = "yellow";
                        } else if (pftValue >= 15 && pftValue < 20) {
                            color = "blue";
                        } else if (pftValue >= 20 && pftValue <= 40) {
                            color = "green";
                        } else if (pftValue > 40) {
                            color = "pink";
                        }

                        if (color !== pftColorFilterVal) return false;
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

                $("#status-filter, #clicks-filter, #inv-filter, #nrl-filter, #nra-filter, #fba-filter, #cvr-color-filter, #pft-color-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "A DIL %", "NRL", "NRA", "FBA"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-lmp-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["lmp_1", "lmp_2", "lmp_3", "lmp_4", "lmp_5", "lmp_6", "lmp_7", "lmp_8", "lmp_9", "lmp_10", "lmp_11"];

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
                    let btn = e.target;

                    let colsToToggle = ["acos_L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spend-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["spend_l7"];

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
                    let btn = e.target;

                    let colsToToggle = ["ad_sales_l7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-clicks-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["clicks_L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function(){

                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows(); 
                
                var campaignIds = [];
                var bgts = [];

                filteredData.forEach(function(row){
                    var rowEl = row.getElement();
                    if(rowEl && rowEl.offsetParent !== null){  
                        var rowData = row.getData();
                        var acos = parseFloat(rowData.acos_L30) || 0;

                        if(acos > 0){
                            var sbgtInput = rowEl.querySelector('.sbgt-input');
                            var sbgtValue = sbgtInput ? parseFloat(sbgtInput.value) || 0 : 0;

                            campaignIds.push(rowData.campaign_id);
                            bgts.push(sbgtValue);
                        }
                    }
                });

                console.log("Campaign IDs:", campaignIds);
                console.log("Bids:", bgts);

                fetch('/update-ebay-campaign-bgt-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        bgts: bgts
                    })
                })
                .then(res => res.json())
                .then(data => {
                    console.log("Backend response:", data);
                    if(data.status === 200){
                        alert("Campaign budget updated successfully!");
                    } else {
                        alert("Something went wrong: " + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Request failed: " + err.message);
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            });

            function updateBid(sbgtValue, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                console.log("Updating bid for Campaign ID:", campaignId, "New Bid:", sbgtValue);

                fetch('/update-ebay-campaign-bgt-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: [campaignId],
                        bgts: [sbgtValue]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    console.log("Backend response:", data);
                    if(data.status === 200){
                        alert("Campaign budget updated successfully!");
                    } else {
                        alert("Something went wrong: " + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Request failed: " + err.message);
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            }

            document.getElementById("export-btn").addEventListener("click", function () {
                let allData = table.getData("active"); 

                if (allData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let exportData = allData.map(row => {
                    let formattedPrice = row.ebay_price ? `$${parseFloat(row.ebay_price).toFixed(2)}` : '';
                    return {
                        ...row,
                        SPRICE: formattedPrice,
                    };
                });

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_acos_kw_ads.xlsx");
            });

            document.body.style.zoom = "78%";
        });
    </script>
@endsection
