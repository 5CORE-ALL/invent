@extends('layouts.vertical', ['title' => 'Amazon - Ad Running', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        .parent-row-bg{
            background-color: #c3efff !important;
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

        .tabulator-col-title {
            white-space: normal !important;
            overflow: visible !important;
            height: auto !important;
            line-height: 1.2em;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon - Budget',
        'sub_title' => 'Amazon - Budget',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Ad Running
                        </h4>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="nrl-filter" class="form-select form-select-md d-none">
                                        <option value="">Select NRL</option>
                                        <option value="NRL">NRL</option>
                                        <option value="RL">RL</option>
                                    </select>

                                    <select id="nra-filter" class="form-select form-select-md d-none">
                                        <option value="">Select NRA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="RA">RA</option>
                                        <option value="LATER">LATER</option>
                                    </select>

                                    <select id="fba-filter" class="form-select form-select-md d-none">
                                        <option value="">Select FBA</option>
                                        <option value="FBA">FBA</option>
                                        <option value="FBM">FBM</option>
                                        <option value="BOTH">BOTH</option>
                                    </select>

                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-success btn-md">
                                        <i class="fa fa-arrow-up me-1"></i>
                                        Need to increase bids: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
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
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search campaign...">
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
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
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
            ajaxURL: "/amazon/ad-running/data",
            layout: "fitDataFill",
            movableColumns: true,
            resizableColumns: true,
            height: "700px",             
            virtualDom: true,
            rowFormatter: function(row) {
                const data = row.getData();
                const sku = (data.sku || "").toLowerCase().trim();

                if (sku.includes("parent ")) {
                    row.getElement().classList.add("parent-row-bg");
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
                    title: 'SPEND L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "SPEND_L30",
                    formatter: function(cell) {
                        let SPEND_L30 = cell.getValue();
                        return `
                            <span>${SPEND_L30.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                            data-spend-l30="${SPEND_L30}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_spend_L30",
                    visible: false
                },
                {
                    title: 'PT Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_spend_L30",
                    visible: false
                },
                {
                    title: 'HL Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_spend_L30",
                    visible: false
                },
                {
                    title: 'SPEND L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "SPEND_L7",
                    formatter: function(cell) {
                        let SPEND_L7 = cell.getValue();
                        return `
                            <span>${SPEND_L7.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-spendL7-btn" 
                            data-spend-l7="${SPEND_L7}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_spend_L7",
                    visible: false
                },
                {
                    title: 'PT Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_spend_L7",
                    visible: false
                },
                {
                    title: 'HL Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_spend_L7",
                    visible: false
                },
                {
                    title: 'SOLD L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "SOLD_L30",
                    formatter: function(cell) {
                        let SOLD_L30 = cell.getValue();
                        return `
                            <span>${SOLD_L30.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-soldL30-btn" 
                            data-sold-l30="${SOLD_L30}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_sold_L30",
                    visible: false
                },
                {
                    title: 'PT Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_sold_L30",
                    visible: false
                },
                {
                    title: 'HL Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_sold_L30",
                    formatter: function(cell) {
                        let HL_SOLD_L30 = cell.getValue();
                        return `
                            <span>${HL_SOLD_L30.toFixed(0)}</span>`;
                    },
                    visible: false
                },
                {
                    title: 'SOLD L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "SOLD_L7",
                    formatter: function(cell) {
                        let SOLD_L7 = cell.getValue();
                        return `
                            <span>${SOLD_L7.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-soldL7-btn" 
                            data-sold-l7="${SOLD_L7}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_sold_L7",
                    visible: false
                },
                {
                    title: 'PT Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_sold_L7",
                    visible: false
                },
                {
                    title: 'HL Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_sold_L7",
                    formatter: function(cell) {
                        let HL_SOLD_L7 = cell.getValue();
                        return `
                            <span>${HL_SOLD_L7.toFixed(0)}</span>`;
                    },
                    visible: false
                },
                {
                    title: 'SALES L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "SALES_L30",
                    formatter: function(cell) {
                        let SALES_L30 = cell.getValue();
                        return `
                            <span>${SALES_L30.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-salesL30-btn" 
                            data-sales-l30="${SALES_L30}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_sales_L30",
                    visible: false
                },
                {
                    title: 'PT Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_sales_L30",
                    visible: false
                },
                {
                    title: 'HL Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_sales_L30",
                    formatter: function(cell) {
                        let HL_SALES_L30 = cell.getValue();
                        return `
                            <span>${HL_SALES_L30.toFixed(0)}</span>`;
                    },
                    visible: false
                },
                {
                    title: 'SALES L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "SALES_L7",
                    formatter: function(cell) {
                        let SALES_L7 = cell.getValue();
                        return `
                            <span>${SALES_L7.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-salesL7-btn" 
                            data-sales-l7="${SALES_L7}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_sales_L7",
                    visible: false
                },
                {
                    title: 'PT Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_sales_L7",
                    visible: false
                },
                {
                    title: 'HL Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_sales_L7",
                    formatter: function(cell) {
                        let HL_SALES_L7 = cell.getValue();
                        return `
                            <span>${HL_SALES_L7.toFixed(0)}</span>`;
                    },
                    visible: false
                },
                {
                    title: 'CLICKS L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "CLICKS_L30",
                    formatter: function(cell) {
                        let CLICKS_L30 = cell.getValue();
                        return `
                            <span>${CLICKS_L30.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-clicksL30-btn" 
                            data-clicks-l30="${CLICKS_L30}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_clicks_L30",
                    visible: false
                },
                {
                    title: 'PT Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_clicks_L30",
                    visible: false
                },
                {
                    title: 'HL Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_clicks_L30",
                    visible: false
                },
                {
                    title: 'CLICKS L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "CLICKS_L7",
                    formatter: function(cell) {
                        let CLICKS_L7 = cell.getValue();
                        return `
                            <span>${CLICKS_L7.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-clicksL7-btn" 
                            data-clicks-l7="${CLICKS_L7}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_clicks_L7",
                    visible: false
                },
                {
                    title: 'PT Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_clicks_L7",
                    visible: false
                },
                {
                    title: 'HL Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_clicks_L7",
                    visible: false
                },
                {
                    title: 'IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "IMP_L30",
                    formatter: function(cell) {
                        let IMP_L30 = cell.getValue();
                        return `
                            <span>${IMP_L30.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-impL30-btn" 
                            data-imp-l30="${IMP_L30}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_impr_L30",
                    visible: false,
                },
                {
                    title: 'PT IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_impr_L30",
                    visible: false
                },
                {
                    title: 'HL IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_impr_L30",
                    visible: false
                },
                {
                    title: 'IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "IMP_L7",
                    formatter: function(cell) {
                        let IMP_L7 = cell.getValue();
                        return `
                            <span>${IMP_L7.toFixed(0)}</span>
                            <i class="fa fa-info-circle text-primary toggle-impL7-btn" 
                            data-imp-l7="${IMP_L7}" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: 'KW IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "kw_impr_L7",
                    visible: false
                },
                {
                    title: 'PT IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pt-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "pt_impr_L7",
                    visible: false
                },
                {
                    title: 'HL IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="hl-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                    field: "hl_impr_L7",
                    visible: false
                },
                {
                    title: "START AD",
                    field: "start_ad",
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const sku = row.getData().sku;
                        const value = cell.getValue();

                        let bgColor = "";
                        if (value === "KW") {
                            bgColor = "background-color:#28a745;color:#fff;";
                        } else if (value === "HL") {
                            bgColor = "background-color:#28a745;color:#fff;";
                        } else if (value === "PT") {
                            bgColor = "background-color:#28a745;color:#000;";
                        } else if (value === "ALL") {
                            bgColor = "background-color:#28a745;color:#000;";
                        }

                        return `
                            <select class="form-select form-select-sm editable-select" 
                                    data-sku="${sku}" 
                                    data-field="start_ad"
                                    style="${bgColor}">
                                <option value=""></option>
                                <option value="KW" ${value === 'KW' ? 'selected' : ''}>KW</option>
                                <option value="HL" ${value === 'HL' ? 'selected' : ''}>HL</option>
                                <option value="PT" ${value === 'PT' ? 'selected' : ''}>PT</option>
                                <option value="ALL" ${value === 'ALL' ? 'selected' : ''}>ALL</option>
                            </select>
                        `;
                    },
                    hozAlign: "center"
                },
                {
                    title: "STOP AD",
                    field: "stop_ad",
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const sku = row.getData().sku;
                        const value = cell.getValue();

                        let bgColor = "";
                        if (value === "KW") {
                            bgColor = "background-color:#dc3545;color:#fff;";
                        } else if (value === "HL") {
                            bgColor = "background-color:#dc3545;color:#fff;";
                        } else if (value === "PT") {
                            bgColor = "background-color:#dc3545;color:#000;";
                        } else if (value === "ALL") {
                            bgColor = "background-color:#dc3545;color:#000;";
                        }

                        return `
                            <select class="form-select form-select-sm editable-select" 
                                    data-sku="${sku}" 
                                    data-field="stop_ad"
                                    style="${bgColor}">
                                <option value=""></option>
                                <option value="KW" ${value === 'KW' ? 'selected' : ''}>KW</option>
                                <option value="HL" ${value === 'HL' ? 'selected' : ''}>HL</option>
                                <option value="PT" ${value === 'PT' ? 'selected' : ''}>PT</option>
                                <option value="ALL" ${value === 'ALL' ? 'selected' : ''}>ALL</option>
                            </select>
                        `;
                    },
                    hozAlign: "center"
                },
            ],
            ajaxResponse: function(url, params, response) {
                // Convert string numbers to actual numbers in the response
                if (response.data && Array.isArray(response.data)) {
                    response.data = response.data.map(row => {
                        // List of fields that should be numbers
                        const numericFields = [
                            'SPEND_L30', 'kw_spend_L30', 'pt_spend_L30', 'hl_spend_L30',
                            'SPEND_L7', 'kw_spend_L7', 'pt_spend_L7', 'hl_spend_L7',
                            'CLICKS_L30', 'kw_clicks_L30', 'pt_clicks_L30', 'hl_clicks_L30',
                            'CLICKS_L7', 'kw_clicks_L7', 'pt_clicks_L7', 'hl_clicks_L7',
                            'IMP_L30', 'kw_impr_L30', 'pt_impr_L30', 'hl_impr_L30',
                            'IMP_L7', 'kw_impr_L7', 'pt_impr_L7', 'hl_impr_L7',
                            'INV', 'L30', 'A_L30'
                        ];
                        
                        numericFields.forEach(field => {
                            if (row[field] !== undefined && row[field] !== null) {
                                // Convert to number, if conversion fails keep original value
                                const numValue = parseFloat(row[field]);
                                row[field] = isNaN(numValue) ? 0 : numValue;
                            }
                        });
                        
                        return row;
                    });
                }
                return response.data;
            }
        });

        document.addEventListener("change", function(e){
            if(e.target.classList.contains("editable-select")){
                let sku   = e.target.getAttribute("data-sku");
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

        let initialSpendL30Data = {};

        table.on("dataLoaded", function(data) {
            data.forEach(row => {
            if (row.SPEND_L30 !== undefined) {
                initialSpendL30Data[row.sku] = row.SPEND_L30;

                fetch('/update-amazon-nr-nrl-fba', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    sku: row.sku,
                    field: 'Spend_L30', 
                    value: row.SPEND_L30
                })
                })
                .then(res => res.json())
                .then(data => {
                console.log('SPEND_L30 saved for SKU:', row.sku);
                })
                .catch(err => {
                console.error('Error saving SPEND_L30:', err);
                });
            }
            });
        });

        table.on("tableBuilt", function() {

            function combinedFilter(data) {

                let searchVal = ($("#global-search").val() || "").toLowerCase();
                if (searchVal && !(data.campaignName || "").toLowerCase().includes(searchVal)) {
                    return false;
                }

                // ⚙️ Status filter
                let statusVal = $("#status-filter").val();
                if (statusVal && data.campaignStatus !== statusVal) {
                    return false;
                }

                // 📦 INV filter
                let invFilterVal = $("#inv-filter").val();
                let inv = parseFloat(data.INV) || 0;
                if (!invFilterVal && inv === 0) return false;
                if (invFilterVal === "INV_0" && inv !== 0) return false;
                if (invFilterVal === "OTHERS" && inv === 0) return false;

                // 🟩 NRL filter
                let nrlFilterVal = $("#nrl-filter").val();
                if (nrlFilterVal && (data.NRL || "") !== nrlFilterVal) return false;

                // 🟧 NR filter
                let nraFilterVal = $("#nra-filter").val();
                if (nraFilterVal && (data.NR || "") !== nraFilterVal) return false;

                // 🟦 FBA filter
                let fbaFilterVal = $("#fba-filter").val();
                if (fbaFilterVal && (data.FBA || "") !== fbaFilterVal) return false;

                return true;
            }

            table.setFilter(combinedFilter);

            function updateCampaignStats() {
                let allRows = table.getData();
                let filteredRows = allRows.filter(combinedFilter);

                let total = allRows.length;
                let filtered = filteredRows.length;

                // Improved helper function to handle both numbers and strings
                const calculateTotal = (field) => {
                    return filteredRows.reduce((sum, row) => {
                        const sku = (row.sku || "").toLowerCase().trim();
                        if (!sku.includes("parent ")) {
                            let value = row[field];
                            // Ensure value is a number
                            if (typeof value === 'string') {
                                value = parseFloat(value) || 0;
                            }
                            return sum + (value || 0);
                        }
                        return sum;
                    }, 0);
                };

                // Calculate all totals
                let spendTotal = calculateTotal('SPEND_L30');
                let kwSpendL30Total = calculateTotal('kw_spend_L30');
                let spendL7Total = calculateTotal('SPEND_L7');
                let clicksL30Total = calculateTotal('CLICKS_L30');
                let clicksL7Total = calculateTotal('CLICKS_L7');
                let impL30Total = calculateTotal('IMP_L30');
                let impL7Total = calculateTotal('IMP_L7');
                
                // Additional metrics
                let kwSpendL7Total = calculateTotal('kw_spend_L7');
                let ptSpendL30Total = calculateTotal('pt_spend_L30');
                let ptSpendL7Total = calculateTotal('pt_spend_L7');
                let hlSpendL30Total = calculateTotal('hl_spend_L30');
                let hlSpendL7Total = calculateTotal('hl_spend_L7');
                
                let kwClicksL30Total = calculateTotal('kw_clicks_L30');
                let kwClicksL7Total = calculateTotal('kw_clicks_L7');
                let ptClicksL30Total = calculateTotal('pt_clicks_L30');
                let ptClicksL7Total = calculateTotal('pt_clicks_L7');
                let hlClicksL30Total = calculateTotal('hl_clicks_L30');
                let hlClicksL7Total = calculateTotal('hl_clicks_L7');
                
                let kwImpL30Total = calculateTotal('kw_impr_L30');
                let kwImpL7Total = calculateTotal('kw_impr_L7');
                let ptImpL30Total = calculateTotal('pt_impr_L30');
                let ptImpL7Total = calculateTotal('pt_impr_L7');
                let hlImpL30Total = calculateTotal('hl_impr_L30');
                let hlImpL7Total = calculateTotal('hl_impr_L7');

                let soldL30Total = calculateTotal('SOLD_L30');
                let soldL7Total = calculateTotal('SOLD_L7');
                let salesL30Total = calculateTotal('SALES_L30');
                let salesL7Total = calculateTotal('SALES_L7');
                let kwSoldL30Total = calculateTotal('kw_sold_L30');
                let kwSoldL7Total = calculateTotal('kw_sold_L7');
                let ptSoldL30Total = calculateTotal('pt_sold_L30');
                let ptSoldL7Total = calculateTotal('pt_sold_L7');
                let hlSoldL30Total = calculateTotal('hl_sold_L30');
                let hlSoldL7Total = calculateTotal('hl_sold_L7');
                let kwSalesL30Total = calculateTotal('kw_sales_L30');
                let kwSalesL7Total = calculateTotal('kw_sales_L7');
                let ptSalesL30Total = calculateTotal('pt_sales_L30');
                let ptSalesL7Total = calculateTotal('pt_sales_L7');
                let hlSalesL30Total = calculateTotal('hl_sales_L30');
                let hlSalesL7Total = calculateTotal('hl_sales_L7');


                $.ajax({
                    url: "{{ route('adv-amazon.ad-running.save-data') }}",
                    method: 'GET',
                    data: {
                        spendl30Total: spendTotal,
                        kwSpendL30Total: kwSpendL30Total,
                        ptSpendL30Total: ptSpendL30Total,
                        hlSpendL30Total:hlSpendL30Total,
                        clicksL30Total: clicksL30Total,
                        kwClicksL30Total: kwClicksL30Total,
                        ptClicksL30Total:ptClicksL30Total,
                        hlClicksL30Total: hlClicksL30Total,
                        salesL30Total : salesL30Total,
                        kwSalesL30Total:kwSalesL30Total,
                        ptSalesL30Total:ptSalesL30Total,
                        hlSalesL30Total:hlSalesL30Total,
                        soldL30Total:soldL30Total,
                        kwSoldL30Total:kwSoldL30Total,
                        ptSoldL30Total:ptSoldL30Total,
                        hlSoldL30Total:hlSoldL30Total
                    },
                    success: function(response) {
                    },
                    error: function(xhr) {
                    }
                });

                // Update SPEND totals
                document.getElementById("spend-l30-total").innerText = spendTotal > 0 ? ` (${spendTotal.toFixed(2)})` : "";
                document.getElementById("spend-l30-total").style.display = spendTotal > 0 ? "inline" : "none";

                document.getElementById("spend-l7-total").innerText = spendL7Total > 0 ? ` (${spendL7Total.toFixed(2)})` : "";
                document.getElementById("spend-l7-total").style.display = spendL7Total > 0 ? "inline" : "none";

                // Update KW SPEND totals
                document.getElementById("kw-spend-l30-total").innerText = kwSpendL30Total > 0 ? ` (${kwSpendL30Total.toFixed(2)})` : "";
                document.getElementById("kw-spend-l30-total").style.display = kwSpendL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-spend-l7-total").innerText = kwSpendL7Total > 0 ? ` (${kwSpendL7Total.toFixed(2)})` : "";
                document.getElementById("kw-spend-l7-total").style.display = kwSpendL7Total > 0 ? "inline" : "none";

                // Update PT SPEND totals
                document.getElementById("pt-spend-l30-total").innerText = ptSpendL30Total > 0 ? ` (${ptSpendL30Total.toFixed(2)})` : "";
                document.getElementById("pt-spend-l30-total").style.display = ptSpendL30Total > 0 ? "inline" : "none";

                document.getElementById("pt-spend-l7-total").innerText = ptSpendL7Total > 0 ? ` (${ptSpendL7Total.toFixed(2)})` : "";
                document.getElementById("pt-spend-l7-total").style.display = ptSpendL7Total > 0 ? "inline" : "none";

                // Update HL SPEND totals
                document.getElementById("hl-spend-l30-total").innerText = hlSpendL30Total > 0 ? ` (${hlSpendL30Total.toFixed(2)})` : "";
                document.getElementById("hl-spend-l30-total").style.display = hlSpendL30Total > 0 ? "inline" : "none";

                document.getElementById("hl-spend-l7-total").innerText = hlSpendL7Total > 0 ? ` (${hlSpendL7Total.toFixed(2)})` : "";
                document.getElementById("hl-spend-l7-total").style.display = hlSpendL7Total > 0 ? "inline" : "none";

                // Update CLICKS totals
                document.getElementById("clicks-l30-total").innerText = clicksL30Total > 0 ? ` (${clicksL30Total.toFixed(0)})` : "";
                document.getElementById("clicks-l30-total").style.display = clicksL30Total > 0 ? "inline" : "none";

                document.getElementById("clicks-l7-total").innerText = clicksL7Total > 0 ? ` (${clicksL7Total.toFixed(0)})` : "";
                document.getElementById("clicks-l7-total").style.display = clicksL7Total > 0 ? "inline" : "none";

                // Update KW CLICKS totals
                document.getElementById("kw-clicks-l30-total").innerText = kwClicksL30Total > 0 ? ` (${kwClicksL30Total.toFixed(0)})` : "";
                document.getElementById("kw-clicks-l30-total").style.display = kwClicksL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-clicks-l7-total").innerText = kwClicksL7Total > 0 ? ` (${kwClicksL7Total.toFixed(0)})` : "";
                document.getElementById("kw-clicks-l7-total").style.display = kwClicksL7Total > 0 ? "inline" : "none";

                // Update PT CLICKS totals
                document.getElementById("pt-clicks-l30-total").innerText = ptClicksL30Total > 0 ? ` (${ptClicksL30Total.toFixed(0)})` : "";
                document.getElementById("pt-clicks-l30-total").style.display = ptClicksL30Total > 0 ? "inline" : "none";

                document.getElementById("pt-clicks-l7-total").innerText = ptClicksL7Total > 0 ? ` (${ptClicksL7Total.toFixed(0)})` : "";
                document.getElementById("pt-clicks-l7-total").style.display = ptClicksL7Total > 0 ? "inline" : "none";

                // Update HL CLICKS totals
                document.getElementById("hl-clicks-l30-total").innerText = hlClicksL30Total > 0 ? ` (${hlClicksL30Total.toFixed(0)})` : "";
                document.getElementById("hl-clicks-l30-total").style.display = hlClicksL30Total > 0 ? "inline" : "none";

                document.getElementById("hl-clicks-l7-total").innerText = hlClicksL7Total > 0 ? ` (${hlClicksL7Total.toFixed(0)})` : "";
                document.getElementById("hl-clicks-l7-total").style.display = hlClicksL7Total > 0 ? "inline" : "none";

                // Update IMPRESSION totals
                document.getElementById("imp-l30-total").innerText = impL30Total > 0 ? ` (${impL30Total.toFixed(0)})` : "";
                document.getElementById("imp-l30-total").style.display = impL30Total > 0 ? "inline" : "none";

                document.getElementById("imp-l7-total").innerText = impL7Total > 0 ? ` (${impL7Total.toFixed(0)})` : "";
                document.getElementById("imp-l7-total").style.display = impL7Total > 0 ? "inline" : "none";

                // Update KW IMP totals
                document.getElementById("kw-imp-l30-total").innerText = kwImpL30Total > 0 ? ` (${kwImpL30Total.toFixed(0)})` : "";
                document.getElementById("kw-imp-l30-total").style.display = kwImpL30Total > 0 ? "inline" : "none";

                document.getElementById("kw-imp-l7-total").innerText = kwImpL7Total > 0 ? ` (${kwImpL7Total.toFixed(0)})` : "";
                document.getElementById("kw-imp-l7-total").style.display = kwImpL7Total > 0 ? "inline" : "none";

                // Update PT IMP totals
                document.getElementById("pt-imp-l30-total").innerText = ptImpL30Total > 0 ? ` (${ptImpL30Total.toFixed(0)})` : "";
                document.getElementById("pt-imp-l30-total").style.display = ptImpL30Total > 0 ? "inline" : "none";

                document.getElementById("pt-imp-l7-total").innerText = ptImpL7Total > 0 ? ` (${ptImpL7Total.toFixed(0)})` : "";
                document.getElementById("pt-imp-l7-total").style.display = ptImpL7Total > 0 ? "inline" : "none";

                // Update HL IMP totals
                document.getElementById("hl-imp-l30-total").innerText = hlImpL30Total > 0 ? ` (${hlImpL30Total.toFixed(0)})` : "";
                document.getElementById("hl-imp-l30-total").style.display = hlImpL30Total > 0 ? "inline" : "none";

                document.getElementById("hl-imp-l7-total").innerText = hlImpL7Total > 0 ? ` (${hlImpL7Total.toFixed(0)})` : "";
                document.getElementById("hl-imp-l7-total").style.display = hlImpL7Total > 0 ? "inline" : "none";

                // Update SOLD totals
                document.getElementById("sold-l30-total").innerText = soldL30Total > 0 ? ` (${soldL30Total.toFixed(0)})` : "";
                document.getElementById("sold-l30-total").style.display = soldL30Total > 0 ? "inline" : "none";

                document.getElementById("sold-l7-total").innerText = soldL7Total > 0 ? ` (${soldL7Total.toFixed(0)})` : "";
                document.getElementById("sold-l7-total").style.display = soldL7Total > 0 ? "inline" : "none";

                // Update SALES totals
                document.getElementById("sales-l30-total").innerText = salesL30Total > 0 ? ` (${salesL30Total.toFixed(0)})` : "";
                document.getElementById("sales-l30-total").style.display = salesL30Total > 0 ? "inline" : "none";

                document.getElementById("sales-l7-total").innerText = salesL7Total > 0 ? ` (${salesL7Total.toFixed(0)})` : "";
                document.getElementById("sales-l7-total").style.display = salesL7Total > 0 ? "inline" : "none";

                // Update KW SOLD totals
                document.getElementById("kw-sold-l30-total").innerText = kwSoldL30Total > 0 ? ` (${kwSoldL30Total.toFixed(0)})` : "";
                document.getElementById("kw-sold-l30-total").style.display = kwSoldL30Total > 0 ? "inline" : "none";
                document.getElementById("kw-sold-l7-total").innerText = kwSoldL7Total > 0 ? ` (${kwSoldL7Total.toFixed(0)})` : "";
                document.getElementById("kw-sold-l7-total").style.display = kwSoldL7Total > 0 ? "inline" : "none";

                // Update PT SOLD totals
                document.getElementById("pt-sold-l30-total").innerText = ptSoldL30Total > 0 ? ` (${ptSoldL30Total.toFixed(0)})  ` : "";
                document.getElementById("pt-sold-l30-total").style.display = ptSoldL30Total > 0 ? "inline" : "none";
                document.getElementById("pt-sold-l7-total").innerText = ptSoldL7Total > 0 ? ` (${ptSoldL7Total.toFixed(0)})` : "";
                document.getElementById("pt-sold-l7-total").style.display = ptSoldL7Total > 0 ? "inline" : "none";

                // Update HL SOLD totals
                document.getElementById("hl-sold-l30-total").innerText = hlSoldL30Total > 0 ? ` (${hlSoldL30Total.toFixed(0)})` : "";
                document.getElementById("hl-sold-l30-total").style.display = hlSoldL30Total > 0 ? "inline" : "none";
                document.getElementById("hl-sold-l7-total").innerText = hlSoldL7Total > 0 ? ` (${hlSoldL7Total.toFixed(0)})` : "";
                document.getElementById("hl-sold-l7-total").style.display = hlSoldL7Total > 0 ? "inline" : "none";

                // Update KW SALES totals
                document.getElementById("kw-sales-l30-total").innerText = kwSalesL30Total > 0 ? ` (${kwSalesL30Total.toFixed(0)})` : "";
                document.getElementById("kw-sales-l30-total").style.display = kwSalesL30Total > 0 ? "inline" : "none";
                document.getElementById("kw-sales-l7-total").innerText = kwSalesL7Total > 0 ? ` (${kwSalesL7Total.toFixed(0)})` : "";
                document.getElementById("kw-sales-l7-total").style.display = kwSalesL7Total > 0 ? "inline" : "none";

                // Update PT SALES totals
                document.getElementById("pt-sales-l30-total").innerText = ptSalesL30Total > 0 ? ` (${ptSalesL30Total.toFixed(0)})` : "";
                document.getElementById("pt-sales-l30-total").style.display = ptSalesL30Total > 0 ? "inline" : "none";
                document.getElementById("pt-sales-l7-total").innerText = ptSalesL7Total > 0 ? ` (${ptSalesL7Total.toFixed(0)})` : "";
                document.getElementById("pt-sales-l7-total").style.display = ptSalesL7Total > 0 ? "inline" : "none";

                // Update HL SALES totals
                document.getElementById("hl-sales-l30-total").innerText = hlSalesL30Total > 0 ? ` (${hlSalesL30Total.toFixed(0)})` : "";
                document.getElementById("hl-sales-l30-total").style.display = hlSalesL30Total > 0 ? "inline" : "none";
                document.getElementById("hl-sales-l7-total").innerText = hlSalesL7Total > 0 ? ` (${hlSalesL7Total.toFixed(0)})` : "";
                document.getElementById("hl-sales-l7-total").style.display = hlSalesL7Total > 0 ? "inline" : "none";

                // Update campaign count and percentage
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

            $("#status-filter,#inv-filter, #nrl-filter, #nra-filter, #fba-filter").on("change",
                function() {
                    table.setFilter(combinedFilter);
                });

            updateCampaignStats();
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-cols-btn")) {
                let btn = e.target;

                let colsToToggle = ["INV", "L30", "DIL %", "A_L30", "A DIL %", "NRL", "NRA", "FBA"];

                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-spendL30-btn")) {
                let colsToToggle = ["kw_spend_L30", "pt_spend_L30", "hl_spend_L30"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-spendL7-btn")) {
                let colsToToggle = ["kw_spend_L7", "pt_spend_L7", "hl_spend_L7"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-soldL30-btn")) {
                let colsToToggle = ["kw_sold_L30", "pt_sold_L30", "hl_sold_L30"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-soldL7-btn")) {
                let colsToToggle = ["kw_sold_L7", "pt_sold_L7", "hl_sold_L7"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-salesL30-btn")) {
                let colsToToggle = ["kw_sales_L30", "pt_sales_L30", "hl_sales_L30"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-salesL7-btn")) {
                let colsToToggle = ["kw_sales_L7", "pt_sales_L7", "hl_sales_L7"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-clicksL30-btn")) {
                let colsToToggle = ["kw_clicks_L30", "pt_clicks_L30", "hl_clicks_L30"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-clicksL7-btn")) {
                let colsToToggle = ["kw_clicks_L7", "pt_clicks_L7", "hl_clicks_L7"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-impL30-btn")) {
                let colsToToggle = ["kw_impr_L30", "pt_impr_L30", "hl_impr_L30"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            if (e.target.classList.contains("toggle-impL7-btn")) {
                let colsToToggle = ["kw_impr_L7", "pt_impr_L7", "hl_impr_L7"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
            }
            
        });

        document.body.style.zoom = "85%";
    });
</script>
@endsection
